<?php

// RSS feed generator
// usage: /newsletter/rss/rss.php?lang=en&limit=20

require_once __DIR__ . '/../bootstrap.php';

// request parameters
$language = isset($_GET['lang']) ? $_GET['lang'] : null;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
$format = isset($_GET['format']) && $_GET['format'] === 'json' ? 'json' : 'rss';

// language validation
$allowed_langs = ['de', 'en', 'fr', 'it', 'ru'];
if ($language && !in_array($language, $allowed_langs)) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid language parameter. Supported: de, en, fr, it, ru";
    exit();
}

try {
    $blogs = nl_get_rss_blogs($language, $limit);

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'language' => $language ?: 'all',
            'count' => count($blogs),
            'blogs' => $blogs
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit();
    }

    header('Content-Type: application/rss+xml; charset=utf-8');
    echo generate_rss_feed($blogs, $language);

} catch (Exception $e) {
    if (NL_DEBUG) {
        header('HTTP/1.0 500 Internal Server Error');
        echo "Error: " . $e->getMessage();
    } else {
        header('HTTP/1.0 500 Internal Server Error');
        echo "Unable to generate RSS feed at this time.";
    }
}
