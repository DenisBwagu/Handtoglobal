<?php

if (!function_exists('available_languages')) {
    function available_languages() {
        return [
            'english' => 'English',
            'chinese' => 'Chinese',
            'german' => 'German',
            'greek' => 'Greek',
            'ukrainian' => 'Ukrainian',
        ];
    }
}

if (!function_exists('normalize_language_code')) {
    function normalize_language_code($language) {
        $language = strtolower(trim((string)$language));
        return array_key_exists($language, available_languages()) ? $language : 'english';
    }
}

if (!function_exists('current_language')) {
    function current_language() {
        if (!empty($_SESSION['language'])) {
            return normalize_language_code($_SESSION['language']);
        }

        $contextKey = !empty($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin'
            ? 'admin_locale'
            : 'user_locale';

        if (function_exists('get_setting')) {
            return normalize_language_code(get_setting($contextKey, 'english'));
        }

        return 'english';
    }
}

if (!function_exists('load_language_pack')) {
    function load_language_pack($language = null) {
        static $packs = [];

        $language = normalize_language_code($language ?: current_language());
        if (isset($packs[$language])) {
            return $packs[$language];
        }

        $file = __DIR__ . '/../languages/' . $language . '.php';
        $englishFile = __DIR__ . '/../languages/english.php';
        $english = is_file($englishFile) ? require $englishFile : [];
        $pack = is_file($file) ? require $file : [];

        if (!is_array($english)) {
            $english = [];
        }
        if (!is_array($pack)) {
            $pack = [];
        }

        $packs[$language] = array_merge($english, $pack);
        return $packs[$language];
    }
}

if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        $translations = load_language_pack();
        if (isset($translations[$key]) && $translations[$key] !== '') {
            return $translations[$key];
        }

        return $fallback !== '' ? $fallback : $key;
    }
}

if (!function_exists('get_translation')) {
    function get_translation($key, $fallback = '') {
        return __t($key, $fallback);
    }
}

if (!function_exists('get_current_language')) {
    function get_current_language() {
        return current_language();
    }
}

if (!function_exists('set_user_language')) {
    function set_user_language($language) {
        $language = normalize_language_code($language);
        $_SESSION['language'] = $language;

        try {
            if (!empty($_SESSION['user_id'])) {
                $conn = getConnection();
                $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
                $stmt->execute([$language, $_SESSION['user_id']]);
            }
            if (function_exists('update_setting')) {
                update_setting('user_locale', $language);
            }
        } catch (Throwable $e) {
            // Session language still applies immediately.
        }

        return $language;
    }
}
