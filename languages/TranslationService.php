<?php
// TranslationService: loads translation arrays from language files and caches them in-memory/APCu
class TranslationService
{
    protected static $cache = [];
    protected static $basePath;

    public static function setBasePath(string $path)
    {
        self::$basePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function load(string $lang, string $section)
    {
        $k = $lang . ':' . $section;
        if (isset(self::$cache[$k])) return self::$cache[$k];

        // try APCu
        if (function_exists('apcu_fetch')) {
            $ac = apcu_fetch('i18n_' . $k, $success);
            if ($success) {
                self::$cache[$k] = $ac;
                return $ac;
            }
        }

        $base = self::$basePath ?? (__DIR__ . DIRECTORY_SEPARATOR);
        $file = $base . $lang . DIRECTORY_SEPARATOR . $section . '.php';
        $translations = [];
        if (is_file($file)) {
            $translations = include $file;
        }

        self::$cache[$k] = $translations;
        if (function_exists('apcu_store')) {
            apcu_store('i18n_' . $k, $translations, 3600);
        }
        return $translations;
    }

    public static function get(string $key, string $section, string $lang, $replace = [])
    {
        $t = self::load($lang, $section);
        if (!isset($t[$key])) {
            // fallback to default language (LanguageManager)
            $default = defined('I18N_DEFAULT') ? I18N_DEFAULT : 'en';
            if ($lang !== $default) {
                $t = self::load($default, $section);
            }
        }

        $val = $t[$key] ?? $key;
        if (!empty($replace)) {
            $val = self::applyReplacements($val, $replace);
        }
        return $val;
    }

    /**
     * @param array<int|string, mixed> $replace
     */
    private static function applyReplacements(string $val, array $replace): string
    {
        $isPositional = true;
        foreach (array_keys($replace) as $key) {
            if (!is_int($key)) {
                $isPositional = false;
                break;
            }
        }

        if ($isPositional) {
            $args = array_values($replace);
            array_unshift($args, $val);
            return (string) call_user_func_array('sprintf', $args);
        }

        foreach ($replace as $name => $value) {
            if (!is_string($name) && !is_int($name)) {
                continue;
            }
            $val = str_replace(':' . $name, (string) $value, $val);
        }

        return $val;
    }
}