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
            array_unshift($replace, $val);
            $val = call_user_func_array('sprintf', $replace);
        }
        return $val;
    }
}