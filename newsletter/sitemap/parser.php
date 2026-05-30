<?php

// sitemap Parser
// usage: php sitemap/parser.php [sitemap_url] [website]
// sample: php sitemap/parser.php https://regaro.ch/sitemap.xml regaro.ch

require_once __DIR__ . '/../bootstrap.php';

// parameters
$sitemap_url = $argv[1] ?? 'http://localhost:8000/sitemap.xml';
$website = $argv[2] ?? parse_url($sitemap_url, PHP_URL_HOST);

echo "SITEMAP PARSER\n";
echo "Website: $website\n";
echo "Sitemap: $sitemap_url\n\n";

try {
    $db = nl_db();

    // get sitemap
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sitemap_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $xml_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$xml_content) {
        throw new Exception("Failed to fetch sitemap (HTTP $http_code)");
    }

    $sitemap = simplexml_load_string($xml_content);
    if ($sitemap === false) {
        throw new Exception("Invalid XML in sitemap");
    }

    $processed = 0;
    $new_blogs = 0;
    $updated_blogs = 0;
    $group_cache = [];

    foreach ($sitemap->url as $url_entry) {
        $loc = (string)$url_entry->loc;
        $lastmod = isset($url_entry->lastmod) ? (string)$url_entry->lastmod : date('Y-m-d');

        // only blogs
        if (strpos($loc, '/blog/') === false && strpos($loc, '/news/') === false) {
            continue;
        }

        // extracting metadata
        $language = 'en';
        $category = '';
        $slug = '';

        if (preg_match('#/(de|en|fr|it|ru)/blog/([^/]+)/([^/]+)/?$#', $loc, $matches)) {
            $language = $matches[1];
            $category = $matches[2];
            $slug = $matches[3];
        } else {
            // fallback: extract the last segment as a slug
            $slug = basename($loc);
            $category = 'general';
        }

        // forming a title
        $title = ucfirst(str_replace(['-', '_'], ' ', $slug));

        // grouping: category + slug (no language)
        $group_key = md5($category . '_' . $slug);

        // getting or creating a group
        if (!isset($group_cache[$group_key])) {
            $stmt = $db->prepare("SELECT id FROM nl_blog_groups WHERE group_key = ?");
            $stmt->execute([$group_key]);
            $group_id = $stmt->fetchColumn();

            if (!$group_id) {
                $stmt = $db->prepare("INSERT INTO nl_blog_groups (group_key, created_at) VALUES (?, NOW())");
                $stmt->execute([$group_key]);
                $group_id = $db->lastInsertId();
            }

            $group_cache[$group_key] = $group_id;
        } else {
            $group_id = $group_cache[$group_key];
        }

        // saving a blog
        $published_at = date('Y-m-d H:i:s', strtotime($lastmod));

        $stmt = $db->prepare("
            INSERT INTO nl_blogs 
            (group_id, url, title, category, language, website, published_at, first_seen_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            published_at = VALUES(published_at),
            updated_at = NOW(),
            is_active = 1
        ");

        $stmt->execute([$group_id, $loc, $title, $category, $language, $website, $published_at]);

        $processed++;
        if ($stmt->rowCount() > 0) {
            $new_blogs++;
        } else {
            $updated_blogs++;
        }

        if ($processed % 10 == 0) {
            echo "  Progress: $processed URLs processed\n";
        }
    }

    // deactivate blogs that are not in the new sitemap
    $stmt = $db->prepare("
        UPDATE nl_blogs 
        SET is_active = 0 
        WHERE website = ? AND first_seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND url NOT IN (SELECT url FROM (SELECT url FROM nl_blogs WHERE is_active = 1 LIMIT 10000) AS tmp)
    ");
    $stmt->execute([$website]);
    $deactivated = $stmt->rowCount();

    echo "RESULTS\n";
    echo "Processed:  $processed\n";
    echo "New blogs:  $new_blogs\n";
    echo "Updated:    $updated_blogs\n";
    echo "Deactivated: $deactivated\n";

    $total_blogs = $db->query("SELECT COUNT(*) FROM nl_blogs WHERE is_active = 1")->fetchColumn();
    $total_groups = $db->query("SELECT COUNT(*) FROM nl_blog_groups")->fetchColumn();

    echo "\nTotal active blogs: $total_blogs\n";
    echo "Total groups: $total_groups\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}