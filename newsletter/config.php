<?php

// debug mode - must be false in production
define('NL_DEBUG', false);
define('NL_VERSION', '1.0');

$nl_config = [
    // database
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'newsletter_prod',
        'user' => 'newsletter_user',
        'pass' => 'CHANGE_THIS_TO_STRONG_PASSWORD',
        'charset' => 'utf8mb4',
        'prefix' => 'nl_',
    ],

    // website
    'website' => [
        'domain' => $_SERVER['HTTP_HOST'] ?? 'zerlo.net',
        'name' => 'Zerlo',
        'timezone' => 'Europe/Zurich',
    ],

    // languages
    'languages' => [
        'de' => ['name' => 'Deutsch', 'default' => true],
        'en' => ['name' => 'English', 'default' => false],
        'fr' => ['name' => 'Français', 'default' => false],
        'it' => ['name' => 'Italiano', 'default' => false],
        'ru' => ['name' => 'Русский', 'default' => false],
    ],

    // modal texts
    'modal_texts' => [
        'de' => [
            'title' => 'Newsletter',
            'description' => 'Erhalten Sie relevante Blogbeiträge per E-Mail oder als Browser-Benachrichtigung.',
            'email_btn' => 'E-Mail Newsletter',
            'push_btn' => 'Web-Benachrichtigungen',
            'no_thanks' => 'Nein, danke',
            'email_placeholder' => 'Ihre E-Mail-Adresse',
            'subscribe_btn' => 'Abonnieren',
            'success_msg' => 'Vielen Dank für Ihr Abonnement!',
            'already_subscribed' => 'Sie sind bereits angemeldet!',
        ],
        'en' => [
            'title' => 'Newsletter',
            'description' => 'Receive relevant blog posts via email or browser notifications.',
            'email_btn' => 'Email Newsletter',
            'push_btn' => 'Web Notifications',
            'no_thanks' => 'No thanks',
            'email_placeholder' => 'Your email address',
            'subscribe_btn' => 'Subscribe',
            'success_msg' => 'Thank you for subscribing!',
            'already_subscribed' => 'You are already subscribed!',
        ],
        'fr' => [
            'title' => 'Newsletter',
            'description' => 'Recevez des articles de blog pertinents par e-mail ou notifications push.',
            'email_btn' => 'Newsletter par e-mail',
            'push_btn' => 'Notifications push',
            'no_thanks' => 'Non merci',
            'email_placeholder' => 'Votre adresse e-mail',
            'subscribe_btn' => "S'abonner",
            'success_msg' => 'Merci pour votre abonnement !',
            'already_subscribed' => 'Vous êtes déjà abonné !',
        ],
        'it' => [
            'title' => 'Newsletter',
            'description' => 'Ricevi articoli pertinenti via email o notifiche push del browser.',
            'email_btn' => 'Newsletter email',
            'push_btn' => 'Notifiche push',
            'no_thanks' => 'No grazie',
            'email_placeholder' => 'Il tuo indirizzo email',
            'subscribe_btn' => 'Iscriviti',
            'success_msg' => 'Grazie per esserti iscritto!',
            'already_subscribed' => 'Sei già iscritto!',
        ],
        'ru' => [
            'title' => 'Новостная рассылка',
            'description' => 'Получайте релевантные статьи блога по электронной почте или через push-уведомления браузера.',
            'email_btn' => 'Email рассылка',
            'push_btn' => 'Push уведомления',
            'no_thanks' => 'Нет, спасибо',
            'email_placeholder' => 'Ваш email адрес',
            'subscribe_btn' => 'Подписаться',
            'success_msg' => 'Спасибо за подписку!',
            'already_subscribed' => 'Вы уже подписаны!',
        ],
    ],

    // display rules
    'display_rules' => [
        'delay_ms' => 3000,
        'max_display_per_session' => 2,
        'debug_force_show' => false,
        'show_on_pages' => ['*'],
        'hide_on_pages' => ['/admin', '/wp-admin'],
        'scroll_percent' => 30,
    ],

    // SMTP settings
    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'auth' => true,
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        'encryption' => 'tls',
        'from_email' => 'newsletter@zerlo.net',
        'from_name' => 'Zerlo Newsletter',
    ],

    // webpush VAPID
    'webpush' => [
        'vapid_public_key' => '',
        'vapid_private_key' => '',
        'vapid_subject' => 'mailto:newsletter@zerlo.net',
    ],

    // matching settings
    'matching' => [
        'max_blogs_per_email' => 4,
        'max_blogs_per_push' => 1,
        'max_age_days' => 365,
        'min_relevance_score' => 0.3,
    ],
];

// security: dont expose config in browser
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === 'config.php') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}