<?php

// blog matcher - finds relevant blogs for subscribers
// usage: php matcher.php [--subscriber=ID]

require_once __DIR__ . '/../bootstrap.php';

// parse arguments
$subscriberId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--subscriber=') === 0) {
        $subscriberId = (int)substr($arg, 13);
    }
}

echo "BLOG MATCHER\n";

try {
    $db = nl_db();

    // load subscribers
    echo "[1] Loading subscribers...\n";
    $subscribers = nl_get_active_subscribers($subscriberId);

    if (empty($subscribers)) {
        echo "No active subscribers found.\n";
        exit(0);
    }
    echo "Found " . count($subscribers) . " active subscribers\n\n";

    // load blogs
    echo "[2] Loading available blogs...\n";
    $blogs = nl_get_available_blogs();

    if (empty($blogs)) {
        echo "No blogs found. Run sitemap/parser.php first.\n";
        exit(0);
    }
    echo "Found " . count($blogs) . " blogs in cache\n\n";

    // match
    echo "[3] Matching blogs to subscribers...\n";
    $results = [];
    $totalMatches = 0;

    foreach ($subscribers as $subscriber) {
        $matches = nl_find_best_matches($subscriber, $blogs);

        if (!empty($matches)) {
            $results[] = [
                'subscriber' => $subscriber,
                'matches' => $matches
            ];
            $totalMatches += count($matches);
            echo sprintf("  Subscriber %d (%s): %d matches\n",
                $subscriber['id'],
                $subscriber['language'],
                count($matches)
            );
        }
    }

    if ($totalMatches == 0) {
        echo "\nNo matches found.\n";
        exit(0);
    }

    echo "\n[4] Total matches: $totalMatches\n\n";

    echo "MATCH DETAILS\n";

    foreach ($results as $result) {
        $sub = $result['subscriber'];
        echo sprintf("Subscriber ID: %d\n", $sub['id']);
        echo sprintf("  Email: %s\n", $sub['email'] ?: '[push only]');
        echo sprintf("  Language: %s\n", $sub['language']);
        echo sprintf("  Type: %s\n", $sub['subscription_type']);
        echo "  Matches:\n";

        foreach ($result['matches'] as $match) {
            echo sprintf("    - %s", $match['title']);
            if ($match['category']) {
                echo sprintf(" (%s)", $match['category']);
            }
            echo sprintf(" [score: %.2f]\n", $match['relevance_score']);
            echo sprintf("      URL: %s\n", $match['url']);
        }
        echo "\n";
    }

    echo "NEXT STEPS\n";
    echo "To send these matches, run:\n";
    echo "  php senders/send_email.php\n";
    echo "  php senders/send_push.php\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}