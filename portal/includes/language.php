<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/includes/language.php
/**
 * Language utilities for Musabaqa application
 */

// Supported languages with directionality
define('SUPPORTED_LANGUAGES', [
    'en' => ['name' => 'English', 'dir' => 'ltr'],
    'ar' => ['name' => 'العربية', 'dir' => 'rtl']
]);

// Default language
define('DEFAULT_LANGUAGE', 'en');

// Global variables
$lang = [];
$current_lang = DEFAULT_LANGUAGE; // Initialize with default
$is_rtl = false; // Initialize directionality

/**
 * Sets the current language based on GET parameter, session, or browser preference.
 * Loads the corresponding language file into the global $lang array.
 * MUST be called after session_start().
 *
 * @return string The determined language code (e.g., 'en', 'ar').
 */
function set_current_language(): string
{
    global $lang, $current_lang, $is_rtl; // Make globals accessible

    // Ensure session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        error_log("Warning: Session not active when set_current_language() was called.");
    }

    $determined_lang = DEFAULT_LANGUAGE;

    // 1. Check GET parameter
    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
        $determined_lang = $_GET['lang'];
        $_SESSION['language'] = $determined_lang; // Store in session
        // Redirect to same page without language parameter to avoid resubmission
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    // 2. Check Session variable
    elseif (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], SUPPORTED_LANGUAGES)) {
        $determined_lang = $_SESSION['language'];
    }
    // 3. Check Accept-Language header (Basic implementation)
    elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browser_langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($browser_langs as $browser_lang) {
            $lang_code = substr(trim(explode(';', $browser_lang)[0]), 0, 2);
            if (array_key_exists($lang_code, SUPPORTED_LANGUAGES)) {
                $determined_lang = $lang_code;
                $_SESSION['language'] = $determined_lang; // Store in session
                break; // Use the first supported language found
            }
        }
    }

    // Set the global current language variable
    $current_lang = $determined_lang;
    $is_rtl = (SUPPORTED_LANGUAGES[$current_lang]['dir'] === 'rtl'); // Set directionality

    // Load the language file
    $lang_file = __DIR__ . '/../language/' . $current_lang . '.php';
    if (file_exists($lang_file)) {
        $lang = require $lang_file; // Load language strings into global $lang
    } else {
        // Fallback or error handling if language file is missing
        error_log("Language file not found: " . $lang_file);
        // Load default language file as fallback
        $default_lang_file = __DIR__ . '/../language/' . DEFAULT_LANGUAGE . '.php';
        if (file_exists($default_lang_file)) {
            $lang = require $default_lang_file;
        } else {
            $lang = []; // No language files found
            error_log("Default language file not found: " . $default_lang_file);
        }
    }

    return $current_lang; // Return the determined language code
}

/**
 * Translation function. Gets a string from the loaded language array.
 * Supports variable substitution using sprintf format.
 *
 * @param string $key The key of the string to translate.
 * @param mixed ...$args Optional arguments for sprintf substitution.
 * @return string The translated string or the key if not found.
 */
function __($key, ...$args): string
{
    global $lang; // Access the global language array

    $translation = $lang[$key] ?? $key; // Return key if translation not found

    // If arguments are provided, use sprintf for substitution
    if (!empty($args)) {
        // Basic sanitization for arguments being inserted into HTML context later
        $sanitized_args = array_map(function($arg) {
            return is_scalar($arg) ? htmlspecialchars((string)$arg, ENT_QUOTES, 'UTF-8') : $arg;
        }, $args);
        return vsprintf($translation, $sanitized_args);
    }

    return $translation;
}

/**
 * Generates HTML for a language switcher.
 *
 * @return string HTML for the language switcher.
 */
function language_switcher(): string
{
    global $current_lang;
    $html = '<form action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="post" class="language-switcher">';
    $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
    $html .= '<div class="flex flex-col gap-1">';
    $html .= '<label for="language" class="form-label font-normal text-gray-900">' . __('language_label') . '</label>';
    $html .= '<select name="language" id="language" class="input" onchange="this.form.submit()">';
    foreach (SUPPORTED_LANGUAGES as $code => $details) {
        $selected = ($current_lang === $code) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($code) . '" ' . $selected . '>' . __($code === 'en' ? 'english_option' : 'arabic_option') . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</form>';

    return $html;
}

// Initialize the current language
set_current_language();
?>