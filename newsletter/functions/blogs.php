<?php

function nl_get_available_blogs(): array {
    global $nl_config;
    $db = nl_db();

    $sql = "SELECT b.*, bg.group_key 
            FROM nl_blogs b
            LEFT JOIN nl_blog_groups bg ON b.group_id = bg.id
            WHERE b.is_active = 1 
            ORDER BY b.published_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function nl_get_sent_groups(int $subscriberId): array {
    $db = nl_db();
    $stmt = $db->prepare("SELECT DISTINCT blog_group_id FROM nl_sent_log WHERE subscriber_id = ?");
    $stmt->execute([$subscriberId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function nl_mark_sent(int $subscriberId, array $blog, string $type = 'push'): void {
    $db = nl_db();
    $stmt = $db->prepare("
        INSERT IGNORE INTO nl_sent_log 
        (subscriber_id, blog_group_id, blog_id, sent_via, sent_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$subscriberId, $blog['group_id'], $blog['id'], $type]);
}

// get blogs for RSS feed
function nl_get_rss_blogs(?string $language = null, int $limit = 50): array {
    $db = nl_db();

    $sql = "
        SELECT 
            b.id,
            b.url,
            b.title,
            b.content_preview,
            b.category,
            b.language,
            b.published_at,
            b.updated_at,
            bg.group_key
        FROM nl_blogs b
        LEFT JOIN nl_blog_groups bg ON b.group_id = bg.id
        WHERE b.is_active = 1
    ";

    $params = [];

    if ($language) {
        $sql .= " AND b.language = ?";
        $params[] = $language;
    }

    $sql .= " ORDER BY b.published_at DESC LIMIT " . (int)$limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // enrich with preview content
    foreach ($blogs as &$blog) {
        if (empty($blog['content_preview'])) {
            $blog['content_preview'] = nl_fetch_blog_preview($blog['url']);
            if ($blog['content_preview']) {
                nl_save_blog_preview($blog['id'], $blog['content_preview']);
            }
        }
    }

    return $blogs;
}

// fetch blog preview from URL
function nl_fetch_blog_preview(string $url): ?string {
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Newsletter-System/1.0');

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) {
        return null;
    }

    $preview = '';

    // try meta description
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
        $preview = strip_tags($matches[1]);
    }

    // try first paragraph
    if (empty($preview) && preg_match('/<p[^>]*>([^<]+)<\/p>/i', $html, $matches)) {
        $preview = strip_tags($matches[1]);
    }

    return mb_substr($preview, 0, 500) ?: null;
}

// save blog preview to cache
function nl_save_blog_preview(int $blogId, string $content): void {
    $db = nl_db();
    $stmt = $db->prepare("
        UPDATE nl_blogs 
        SET content_preview = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$content, $blogId]);
}