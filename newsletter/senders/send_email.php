<?php

// email Newsletter Sender
// usage: php send_email.php [--auto]

require_once __DIR__ . '/../bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$isAuto = in_array('--auto', $argv);

// texts for letters in different languages
$email_texts = [
    'de' => [
        'subject_single' => 'Beitrag: %s',
        'subject_multiple' => '%d Beiträge für Sie',
        'header_title' => 'Beiträge aus unserem Blog',
        'header_subtitle' => 'Basierend auf Ihren Interessen',
        'read_more' => 'Weiterlesen',
        'footer_text' => 'Sie erhalten diese E-Mail, weil Sie unseren Newsletter abonniert haben.',
        'unsubscribe' => 'Abbestellen',
        'date_format' => 'd.m.Y'
    ],
    'en' => [
        'subject_single' => 'Post: %s',
        'subject_multiple' => '%d posts for you',
        'header_title' => 'Posts from our blog',
        'header_subtitle' => 'Based on your interests',
        'read_more' => 'Read more',
        'footer_text' => 'You received this email because you subscribed to our newsletter.',
        'unsubscribe' => 'Unsubscribe',
        'date_format' => 'F j, Y'
    ],
    'fr' => [
        'subject_single' => 'Article: %s',
        'subject_multiple' => '%d articles pour vous',
        'header_title' => 'Articles de notre blog',
        'header_subtitle' => 'Basé sur vos intérêts',
        'read_more' => 'Lire la suite',
        'footer_text' => 'Vous recevez cet email car vous êtes abonné à notre newsletter.',
        'unsubscribe' => 'Se désabonner',
        'date_format' => 'd/m/Y'
    ],
    'it' => [
        'subject_single' => 'Articolo: %s',
        'subject_multiple' => '%d articoli per te',
        'header_title' => 'Articoli dal nostro blog',
        'header_subtitle' => 'Basati sui tuoi interessi',
        'read_more' => 'Leggi tutto',
        'footer_text' => 'Hai ricevuto questa email perché sei iscritto alla nostra newsletter.',
        'unsubscribe' => 'Annulla iscrizione',
        'date_format' => 'd/m/Y'
    ],
    'ru' => [
        'subject_single' => 'Статья: %s',
        'subject_multiple' => '%d статей для вас',
        'header_title' => 'Статьи из нашего блога',
        'header_subtitle' => 'На основе ваших интересов',
        'read_more' => 'Читать далее',
        'footer_text' => 'Вы получили это письмо, потому что подписаны на нашу рассылку.',
        'unsubscribe' => 'Отписаться',
        'date_format' => 'd.m.Y'
    ]
];

echo "[EMAIL] Starting...\n";

if ($isAuto) {
    echo "[AUTO] Automatic mode enabled\n";
}

try {
    global $nl_config;

    if (empty($nl_config['smtp']['host'])) {
        echo "[ERROR] SMTP not configured in config.php\n";
        exit(1);
    }

    echo "[INFO] Loading email subscribers...\n";
    $subscribers = nl_get_email_subscribers();

    if (empty($subscribers)) {
        echo "[INFO] No active email subscribers found.\n";
        exit(0);
    }
    echo "[INFO] Found " . count($subscribers) . " email subscribers\n";

    echo "[INFO] Loading available blogs...\n";
    $blogs = nl_get_available_blogs();
    echo "[INFO] Found " . count($blogs) . " blogs in cache\n";

    echo "[INFO] Matching blogs to subscribers...\n";
    $matches = [];

    foreach ($subscribers as $subscriber) {
        $matchedBlogs = nl_find_best_matches($subscriber, $blogs);

        if (!empty($matchedBlogs)) {
            $matches[] = [
                'subscriber' => $subscriber,
                'blogs' => $matchedBlogs
            ];
            echo "[MATCH] " . $subscriber['email'] . ": " . count($matchedBlogs) . " blogs\n";
        }
    }

    if (empty($matches)) {
        echo "[INFO] No matches found for any subscriber.\n";
        exit(0);
    }

    echo "\n[PREVIEW] Emails to send:\n";
    foreach ($matches as $index => $match) {
        $sub = $match['subscriber'];
        echo "  " . ($index + 1) . ". " . $sub['email'] . " (" . $sub['language'] . ")\n";
        foreach ($match['blogs'] as $blog) {
            echo "     - " . $blog['title'] . "\n";
        }
    }

    // skip confirmation in auto mode
    if (!$isAuto) {
        echo "\n[CONFIRM] Send " . count($matches) . " emails?\n";
        echo "Type 'send' to confirm, 'cancel' to abort: ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        if (strtolower($input) !== 'send') {
            echo "[CANCELLED]\n";
            exit(0);
        }
    } else {
        echo "[AUTO] Sending without confirmation...\n";
    }

    echo "\n[SENDING] Processing...\n";

    $sent = 0;
    $failed = 0;

    foreach ($matches as $match) {
        $subscriber = $match['subscriber'];
        $blogs = $match['blogs'];
        $lang = $subscriber['language'];

        try {
            send_newsletter_email($subscriber, $blogs, $email_texts[$lang] ?? $email_texts['en']);

            foreach ($blogs as $blog) {
                nl_mark_sent($subscriber['id'], $blog, 'email');
            }

            $sent++;
            echo "[SENT] " . $subscriber['email'] . "\n";

        } catch (Exception $e) {
            $failed++;
            echo "[FAILED] " . $subscriber['email'] . ": " . $e->getMessage() . "\n";
        }

        usleep(500000);
    }

    echo "\n[DONE] Sent: $sent, Failed: $failed\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

function send_newsletter_email($subscriber, $blogs, $texts) {
    global $nl_config;

    $mail = new PHPMailer(true);

    $smtp = $nl_config['smtp'];
    if ($smtp['enabled']) {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];

        if ($smtp['auth']) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
        }

        if ($smtp['encryption']) {
            $mail->SMTPSecure = $smtp['encryption'];
        }
    }

    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
    $mail->addAddress($subscriber['email']);

    if (count($blogs) == 1) {
        $mail->Subject = sprintf($texts['subject_single'], $blogs[0]['title']);
    } else {
        $mail->Subject = sprintf($texts['subject_multiple'], count($blogs));
    }

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Body = build_email_html($subscriber, $blogs, $texts);
    $mail->AltBody = strip_tags($mail->Body);

    $mail->send();
}

function build_email_html($subscriber, $blogs, $texts) {
    $unsubscribe_url = "http://{$subscriber['website']}/newsletter/api/unsubscribe-email.php?token={$subscriber['unsubscribe_token']}";

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a1a2e; color: white; padding: 30px 20px; text-align: center; }
            .header h2 { margin: 0; font-size: 24px; font-weight: 500; }
            .header p { margin: 8px 0 0; opacity: 0.7; font-size: 14px; }
            .blog-item { margin: 24px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
            .blog-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
            .blog-title a { color: #1a1a2e; text-decoration: none; }
            .blog-title a:hover { text-decoration: underline; }
            .blog-meta { font-size: 12px; color: #888; margin-bottom: 12px; }
            .blog-excerpt { color: #555; margin-bottom: 12px; }
            .read-more { color: #667eea; text-decoration: none; font-size: 14px; font-weight: 500; }
            .read-more:hover { text-decoration: underline; }
            .footer { margin-top: 32px; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            .unsubscribe { color: #999; text-decoration: none; }
            .unsubscribe:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>' . htmlspecialchars($texts['header_title']) . '</h2>
                <p>' . htmlspecialchars($texts['header_subtitle']) . '</p>
            </div>';

    foreach ($blogs as $blog) {
        $html .= '
            <div class="blog-item">
                <div class="blog-title">
                    <a href="' . $blog['url'] . '">' . htmlspecialchars($blog['title']) . '</a>
                </div>
                <div class="blog-meta">
                    ' . date($texts['date_format'], strtotime($blog['published_at'])) .
            ($blog['category'] ? ' | ' . htmlspecialchars($blog['category']) : '') . '
                </div>
                <div class="blog-excerpt">
                    ' . htmlspecialchars(substr($blog['content_preview'] ?? $blog['title'], 0, 150)) . '...
                </div>
                <a href="' . $blog['url'] . '" class="read-more">' . htmlspecialchars($texts['read_more']) . ' →</a>
            </div>';
    }

    $html .= '
            <div class="footer">
                <p>' . htmlspecialchars($texts['footer_text']) . '</p>
                <p><a href="' . $unsubscribe_url . '" class="unsubscribe">' . htmlspecialchars($texts['unsubscribe']) . '</a></p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}