<?php
// LanguageManager: loads/saves user language preference and resolves active language
class LanguageManager
{
    protected static $available = ['en', 'fr'];
    protected static $default = 'en';

    public static function setAvailable(array $langs)
    {
        self::$available = $langs;
    }

    public static function getAvailable(): array
    {
        return self::$available;
    }

    public static function setDefault(string $lang)
    {
        self::$default = $lang;
    }

    public static function getDefault(): string
    {
        return self::$default;
    }

    public static function resolve(array $options = []): string
    {
        // precedence: explicit GET/POST 'lang' -> session -> cookie -> DB (if user) -> Accept-Language -> default
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        if (!empty($options['override'])) {
            $lang = $options['override'];
            if (in_array($lang, self::$available)) return $lang;
        }

        if (!empty($_REQUEST['lang']) && in_array($_REQUEST['lang'], self::$available)) {
            return $_REQUEST['lang'];
        }

        if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], self::$available)) {
            return $_SESSION['lang'];
        }

        if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::$available)) {
            return $_COOKIE['lang'];
        }

        // if user id provided and DB connection provided
        if (!empty($options['pdo']) && !empty($options['user_id'])) {
            $stmt = $options['pdo']->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
            if ($stmt->execute([$options['user_id']])) {
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($r['language']) && in_array($r['language'], self::$available)) return $r['language'];
            }
        }

        // HTTP Accept-Language (optional — skipped on public auth pages)
        if (empty($options['skip_browser']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $l) {
                $code = substr($l, 0, 2);
                if (in_array($code, self::$available)) return $code;
            }
        }

        return self::$default;
    }

    public static function apply(string $lang, $pdo = null, $userId = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (in_array($lang, self::$available)) {
            $_SESSION['lang'] = $lang;
            setcookie('lang', $lang, time() + 60 * 60 * 24 * 365, '/');
            if ($pdo && $userId) {
                $stmt = $pdo->prepare('UPDATE users SET language = ? WHERE id = ?');
                $stmt->execute([$lang, $userId]);
            }
            return true;
        }
        return false;
    }
}
