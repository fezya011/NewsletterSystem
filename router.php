<?php
// router.php
$request_uri = $_SERVER['REQUEST_URI'];

// Разрешаем реальные файлы (CSS, JS, изображения)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|xml|json|php)$/', $request_uri)) {
    return false;
}

// API запросы - обрабатываем как есть
if (strpos($request_uri, '/newsletter/api/') === 0) {
    return false;
}

// Service Worker
if ($request_uri === '/sw.js') {
    return false;
}

// Демо страница
if ($request_uri === '/demo.php') {
    return false;
}

// Убираем trailing slash
$request_uri = rtrim($request_uri, '/');

// Маршруты для блогов
if (preg_match('#^/(de|en|fr|it|ru)/blog/([^/]+)/([^/]+)$#', $request_uri, $matches)) {
    $lang = $matches[1];
    $category = $matches[2];
    $slug = $matches[3];

    $file = __DIR__ . "/{$lang}/blog/{$category}/{$slug}.php";

    if (file_exists($file)) {
        $_GET['lang'] = $lang;
        include $file;
        exit();
    }
}

// Статические HTML файлы в папках блога
$static_file = __DIR__ . $request_uri;
if (file_exists($static_file) && !is_dir($static_file)) {
    return false;
}

// 404
http_response_code(404);
echo "<h1>404 - Page not found</h1>";
echo "<p>The requested resource {$request_uri} was not found.</p>";