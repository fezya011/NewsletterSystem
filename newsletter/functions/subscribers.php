<?php

function nl_get_active_subscribers($subscriberId = null) {
    $db = nl_db();

    $sql = "SELECT id, email, push_endpoint, subscription_type, status, website, language, categories 
            FROM nl_subscribers 
            WHERE status = 'active'";

    if ($subscriberId) {
        $sql .= " AND id = " . (int)$subscriberId;
    }

    $result = $db->query($sql);
    $subscribers = $result->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscribers as &$sub) {
        $sub['categories'] = json_decode($sub['categories'] ?? '[]', true);
    }

    return $subscribers;
}

function nl_get_push_subscribers(): array {
    $db = nl_db();
    $sql = "SELECT id, email, push_endpoint, push_auth, push_p256dh, language, website, categories, subscription_type
            FROM nl_subscribers 
            WHERE status = 'active' 
              AND subscription_type IN ('push', 'both')
              AND push_endpoint IS NOT NULL";
    return $db->query($sql)->fetchAll();
}

function nl_get_email_subscribers(): array {
    $db = nl_db();
    $sql = "SELECT id, email, language, website, categories, unsubscribe_token, subscription_type
            FROM nl_subscribers 
            WHERE status = 'active' 
              AND subscription_type IN ('email', 'both')
              AND email IS NOT NULL";
    return $db->query($sql)->fetchAll();
}

function nl_save_push_subscription(string $endpoint, string $auth, string $p256dh, string $language, string $website, array $categories = []): bool {
    $db = nl_db();
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("
        INSERT INTO nl_subscribers 
        (push_endpoint, push_auth, push_p256dh, subscription_type, status, website, language, categories, unsubscribe_token, subscribed_at, confirmed_at)
        VALUES (?, ?, ?, 'push', 'active', ?, ?, ?, ?, NOW(), NOW())
    ");
    return $stmt->execute([$endpoint, $auth, $p256dh, $website, $language, json_encode($categories), $token]);
}