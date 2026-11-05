<?php
function get_admin_lang() {
    return $_COOKIE['admin_lang'] ?? 'ru';
}

function __($key) {
    static $translations = null;
    if ($translations === null) {
        $lang = get_admin_lang();
        $file = __DIR__ . "/../../js/lang/{$lang}.json";
        if (!file_exists($file)) $file = __DIR__ . "/../../js/lang/ru.json";
        
        $json = file_get_contents($file);
        $translations = json_decode($json, true);
        if (!is_array($translations)) $translations = [];
    }
    
    // Fall back to Russian for keys a language file doesn't have yet, then to the key itself
    static $fallback = null;
    if (!isset($translations[$key]) && $fallback === null) {
        $fallback = json_decode((string) file_get_contents(__DIR__ . '/../../js/lang/ru.json'), true) ?: [];
    }
    return $translations[$key] ?? $fallback[$key] ?? $key;
}
?>
