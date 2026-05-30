<?php

function nl_calculate_relevance(array $subscriber, array $blog): float {
    $score = 0.5;

    $subCats = $subscriber['categories'] ?? [];
    if (is_string($subCats)) {
        $subCats = json_decode($subCats, true) ?: [];
    }

    if (!empty($subCats) && in_array($blog['category'], $subCats)) {
        $score += 0.3;
    }

    // freshness
    $daysOld = (time() - strtotime($blog['published_at'])) / 86400;
    if ($daysOld < 7) {
        $score += 0.2;
    } elseif ($daysOld < 30) {
        $score += 0.1;
    } elseif ($daysOld < 90) {
        $score += 0.05;
    }

    // website match
    if (isset($blog['website']) && isset($subscriber['website']) && $blog['website'] === $subscriber['website']) {
        $score += 0.1;
    }

    return min($score, 1.0);
}

function nl_find_best_matches(array $subscriber, array $blogs): array {
    global $nl_config;

    // filter by subscribers language
    $filtered = array_filter($blogs, function($blog) use ($subscriber) {
        return isset($blog['language']) && $blog['language'] === $subscriber['language'];
    });

    // exclude already sent
    $sent = nl_get_sent_groups($subscriber['id']);
    $filtered = array_filter($filtered, function($blog) use ($sent) {
        return !in_array($blog['group_id'], $sent);
    });

    // calculate relevance
    foreach ($filtered as &$blog) {
        $blog['relevance_score'] = nl_calculate_relevance($subscriber, $blog);
    }

    // sort by relevance
    usort($filtered, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });

    $limit = ($subscriber['subscription_type'] ?? 'push') === 'push'
        ? ($nl_config['matching']['max_blogs_per_push'] ?? 1)
        : ($nl_config['matching']['max_blogs_per_email'] ?? 4);

    return array_slice($filtered, 0, $limit);
}