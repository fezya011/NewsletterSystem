<?php

if (!defined('ABSPATH') && !defined('NL_INCLUDED')) {
    define('NL_INCLUDED', true);
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nl_modal_shown_count'])) {
    $_SESSION['nl_modal_shown_count'] = 0;
}

function nl_get_current_language() {
    global $nl_config;

    if (isset($_GET['lang'])) {
        $lang = $_GET['lang'];
        if (in_array($lang, ['de', 'en', 'fr', 'it', 'ru'])) {
            return $lang;
        }
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/(de|en|fr|it|ru)/#', $uri, $matches)) {
        return $matches[1];
    }

    if (isset($_SESSION['nl_language'])) {
        return $_SESSION['nl_language'];
    }

    $preferred_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    if (in_array($preferred_lang, ['de', 'en', 'fr', 'it', 'ru'])) {
        return $preferred_lang;
    }

    foreach ($nl_config['languages'] as $code => $lang) {
        if (isset($lang['default']) && $lang['default']) {
            return $code;
        }
    }

    return 'en';
}

function nl_get_text($key, $language = null) {
    global $nl_config;

    if (!$language) {
        $language = nl_get_current_language();
    }

    if (!isset($nl_config['modal_texts'][$language])) {
        foreach ($nl_config['languages'] as $code => $lang) {
            if (isset($lang['default']) && $lang['default']) {
                $language = $code;
                break;
            }
        }
    }

    return $nl_config['modal_texts'][$language][$key] ?? $key;
}

function nl_should_show_modal() {
    global $nl_config;

    if (NL_DEBUG && $nl_config['display_rules']['debug_force_show']) {
        return true;
    }

    if ($_SESSION['nl_modal_shown_count'] >= $nl_config['display_rules']['max_display_per_session']) {
        return false;
    }

    $current_url = $_SERVER['REQUEST_URI'] ?? '/';
    $hide_on_pages = $nl_config['display_rules']['hide_on_pages'];

    foreach ($hide_on_pages as $pattern) {
        if ($pattern !== '*' && strpos($current_url, $pattern) !== false) {
            return false;
        }
    }

    return true;
}

function nl_enqueue_assets() {
    echo '<link rel="stylesheet" href="/newsletter/newsletter.css">';
    echo '<script src="/newsletter/newsletter.js" defer></script>';

    $current_lang = nl_get_current_language();
    $modal_texts = $GLOBALS['nl_config']['modal_texts'][$current_lang] ?? $GLOBALS['nl_config']['modal_texts']['en'];

    echo '<script>';
    echo 'window.nlConfig = ' . json_encode([
            'apiUrl' => '/newsletter/api/subscribe.php',
            'currentLang' => $current_lang,
            'website' => $GLOBALS['nl_config']['website']['domain'],
            'texts' => $modal_texts,
            'debug' => NL_DEBUG,
            'delayMs' => $GLOBALS['nl_config']['display_rules']['delay_ms'],
            'vapidPublicKey' => $GLOBALS['nl_config']['webpush']['vapid_public_key'] ?? '',
        ]) . ';';
    echo '</script>';
}

function nl_handle_service_worker() {
    $sw_source = __DIR__ . '/webpush/sw.js';
    $sw_target = $_SERVER['DOCUMENT_ROOT'] . '/sw.js';

    if (file_exists($sw_source) && !file_exists($sw_target)) {
        copy($sw_source, $sw_target);
    }

    echo '<script>
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/sw.js")
            .then(function(reg) { console.log("Newsletter: Service Worker registered"); })
            .catch(function(err) { console.error("Newsletter: Service Worker registration failed", err); });
    }
    </script>';
}

function nl_render_modal() {
    if (!nl_should_show_modal()) {
        return;
    }

    $_SESSION['nl_modal_shown_count']++;
    $current_lang = nl_get_current_language();
    ?>
    <div id="nl-modal-overlay" style="display: none;">
        <div id="nl-modal-container">
            <h2 id="nl-title"><?php echo htmlspecialchars(nl_get_text('title', $current_lang)); ?></h2>
            <p id="nl-description"><?php echo htmlspecialchars(nl_get_text('description', $current_lang)); ?></p>

            <div id="nl-options">
                <button class="nl-option-btn" data-type="email"><?php echo htmlspecialchars(nl_get_text('email_btn', $current_lang)); ?></button>
                <button class="nl-option-btn" data-type="push"><?php echo htmlspecialchars(nl_get_text('push_btn', $current_lang)); ?></button>
                <button class="nl-option-btn nl-no-thanks" data-type="no"><?php echo htmlspecialchars(nl_get_text('no_thanks', $current_lang)); ?></button>
            </div>

            <div id="nl-email-form" style="display: none;">
                <input type="email" id="nl-email" placeholder="<?php echo htmlspecialchars(nl_get_text('email_placeholder', $current_lang)); ?>" required>
                <button id="nl-subscribe-email"><?php echo htmlspecialchars(nl_get_text('subscribe_btn', $current_lang)); ?></button>
                <div id="nl-error-msg"></div>
            </div>

            <div id="nl-loading" style="display: none;">
                <div class="nl-spinner"></div>
                <p>Processing...</p>
            </div>

            <div id="nl-success" style="display: none;">
                <p><?php echo htmlspecialchars(nl_get_text('success_msg', $current_lang)); ?></p>
            </div>
        </div>
    </div>

    <script>
        setTimeout(function() {
            var overlay = document.getElementById('nl-modal-overlay');
            if (overlay) overlay.style.display = 'flex';
        }, <?php echo $GLOBALS['nl_config']['display_rules']['delay_ms'] ?? 1500; ?>);
    </script>
    <?php
}

function nl_init() {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return;
    }

    nl_enqueue_assets();
    nl_handle_service_worker();
    nl_render_modal();
}

nl_init();