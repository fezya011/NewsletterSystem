<?php

// push Notification Sender
// usage: php send_push.php [--auto]

require_once __DIR__ . '/../bootstrap.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$isAuto = in_array('--auto', $argv);

// texts for push in different languages
$push_texts = [
    'de' => [
        'title' => 'Aktuelles',
        'body' => '%s',
        'click_action' => 'Öffnen'
    ],
    'en' => [
        'title' => 'Update',
        'body' => '%s',
        'click_action' => 'Open'
    ],
    'fr' => [
        'title' => 'Actualité',
        'body' => '%s',
        'click_action' => 'Ouvrir'
    ],
    'it' => [
        'title' => 'Attualità',
        'body' => '%s',
        'click_action' => 'Apri'
    ],
    'ru' => [
        'title' => 'Актуальное',
        'body' => '%s',
        'click_action' => 'Открыть'
    ]
];

echo "[PUSH] Starting...\n";

if ($isAuto) {
    echo "[AUTO] Automatic mode enabled\n";
}

try {
    global $nl_config;

    if (empty($nl_config['webpush']['vapid_public_key']) || empty($nl_config['webpush']['vapid_private_key'])) {
        echo "[ERROR] VAPID keys not configured in config.php\n";
        exit(1);
    }

    $subject = $nl_config['webpush']['vapid_subject'] ?? '';
    if (empty($subject) || (!str_starts_with($subject, 'mailto:') && !str_starts_with($subject, 'https://'))) {
        $nl_config['webpush']['vapid_subject'] = 'mailto:newsletter@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        echo "[WARNING] Fixed VAPID subject: {$nl_config['webpush']['vapid_subject']}\n";
    }

    $subscribers = nl_get_push_subscribers();
    if (empty($subscribers)) {
        echo "[INFO] No subscribers\n";
        exit(0);
    }
    echo "[INFO] Found " . count($subscribers) . " subscribers\n";

    $blogs = nl_get_available_blogs();
    if (empty($blogs)) {
        echo "[INFO] No blogs\n";
        exit(0);
    }
    echo "[INFO] Found " . count($blogs) . " blogs\n";

    // Match
    $matches = [];
    foreach ($subscribers as $sub) {
        $best = nl_find_best_matches($sub, $blogs);
        if (!empty($best)) {
            $matches[] = [
                'subscriber' => $sub,
                'blog' => $best[0],
                'language' => $sub['language'] ?? 'en'
            ];
            echo "[MATCH] " . $best[0]['title'] . " (" . ($sub['language'] ?? 'en') . ")\n";
        }
    }

    if (empty($matches)) {
        echo "[INFO] No matches found\n";
        exit(0);
    }

    // skip confirmation in auto mode
    if (!$isAuto) {
        echo "\n[CONFIRM] Send " . count($matches) . " notifications? (send/cancel): ";
        $input = trim(fgets(STDIN));

        if ($input !== 'send') {
            echo "[CANCELLED]\n";
            exit(0);
        }
    } else {
        echo "[AUTO] Sending without confirmation...\n";
    }

    // send
    $auth = [
        'VAPID' => [
            'subject' => $nl_config['webpush']['vapid_subject'],
            'publicKey' => $nl_config['webpush']['vapid_public_key'],
            'privateKey' => $nl_config['webpush']['vapid_private_key']
        ]
    ];

    $webPush = new WebPush($auth);
    $sent = 0;
    $failed = 0;

    foreach ($matches as $match) {
        $sub = $match['subscriber'];
        $blog = $match['blog'];
        $lang = $match['language'];

        // we receive texts for the subscriber's language
        $texts = $push_texts[$lang] ?? $push_texts['en'];

        try {
            $subscription = Subscription::create([
                'endpoint' => $sub['push_endpoint'],
                'authToken' => $sub['push_auth'],
                'publicKey' => $sub['push_p256dh'],
                'contentEncoding' => 'aesgcm'
            ]);

            $payload = json_encode([
                'title' => $texts['title'],
                'body' => sprintf($texts['body'], $blog['title']),
                'url' => $blog['url'],
                'icon' => '/favicon.ico',
                'timestamp' => time(),
                'language' => $lang
            ]);

            $webPush->queueNotification($subscription, $payload);
            nl_mark_sent($sub['id'], $blog, 'push');
            $sent++;
            echo "[QUEUED] " . substr($blog['title'], 0, 50) . "... ($lang)\n";

        } catch (Exception $e) {
            $failed++;
            echo "[ERROR] " . $e->getMessage() . "\n";
        }
    }

    echo "[SENDING] Processing...\n";

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            $failed++;
            echo "[FAILED] " . $report->getReason() . "\n";
            if ($report->isSubscriptionExpired()) {
                echo "[INFO] Subscription expired\n";
            }
        } else {
            echo "[SUCCESS] Notification sent\n";
        }
    }

    echo "\n[DONE] Sent: $sent, Failed: $failed\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}