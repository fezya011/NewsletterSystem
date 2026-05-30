<?php
// Настройки демо-сайта
define('DEMO_SITE', true);
define('SITE_NAME', 'Candle Blog Demo');
define('SITE_DOMAIN', $_SERVER['HTTP_HOST'] ?? 'localhost:8000');



// Подключаем нашу newsletter систему (путь от корня)
$newsletter_path = __DIR__ . '/newsletter/newsletter.php';

require_once $newsletter_path;

// Демо-блоги по языкам
$demo_blogs = [
    'de' => [
        ['title' => 'Wie man die perfekte Hochzeitskerze macht', 'category' => 'hochzeit', 'date' => '15. März 2024', 'slug' => 'perfekte-hochzeitskerze'],
        ['title' => 'Die 10 besten Duftkerzen für Zuhause', 'category' => 'duftkerzen', 'date' => '10. März 2024', 'slug' => 'beste-duftkerzen'],
        ['title' => 'Kerzen-Deko Ideen für die Weihnachtszeit', 'category' => 'deko', 'date' => '5. März 2024', 'slug' => 'kerzen-deko-ideen'],
        ['title' => 'Nachhaltige Kerzen aus Sojawachs', 'category' => 'nachhaltig', 'date' => '28. Februar 2024', 'slug' => 'nachhaltige-kerzen'],
    ],
    'en' => [
        ['title' => 'How to Make the Perfect Wedding Candle', 'category' => 'wedding', 'date' => 'March 15, 2024', 'slug' => 'perfect-wedding-candle'],
        ['title' => 'Top 10 Scented Candles for Your Home', 'category' => 'scented', 'date' => 'March 10, 2024', 'slug' => 'top-scented-candles'],
        ['title' => 'Christmas Candle Decoration Ideas', 'category' => 'decor', 'date' => 'March 5, 2024', 'slug' => 'christmas-candle-decor'],
        ['title' => 'Eco-Friendly Soy Wax Candles', 'category' => 'eco', 'date' => 'February 28, 2024', 'slug' => 'eco-soy-candles'],
    ],
    'fr' => [
        ['title' => 'Comment créer la bougie de mariage parfaite', 'category' => 'mariage', 'date' => '15 mars 2024', 'slug' => 'bougie-mariage-parfaite'],
        ['title' => 'Les 10 meilleures bougies parfumées', 'category' => 'parfumees', 'date' => '10 mars 2024', 'slug' => 'meilleures-bougies-parfumees'],
        ['title' => 'Idées de décoration de bougies pour Noël', 'category' => 'deco', 'date' => '5 mars 2024', 'slug' => 'deco-bougies-noel'],
        ['title' => 'Bougies écologiques en cire de soja', 'category' => 'ecolo', 'date' => '28 février 2024', 'slug' => 'bougies-cire-soja'],
    ],
    'it' => [
        ['title' => 'Come fare la candela per matrimonio perfetta', 'category' => 'matrimonio', 'date' => '15 marzo 2024', 'slug' => 'candela-matrimonio-perfetta'],
        ['title' => 'Le 10 migliori candele profumate per casa', 'category' => 'profumate', 'date' => '10 marzo 2024', 'slug' => 'migliori-candele-profumate'],
        ['title' => 'Idee per decorazioni natalizie con candele', 'category' => 'decorazioni', 'date' => '5 marzo 2024', 'slug' => 'decorazioni-natalizie-candele'],
        ['title' => 'Candele ecologiche in cera di soia', 'category' => 'ecologiche', 'date' => '28 febbraio 2024', 'slug' => 'candele-cera-soia'],
    ],
    'ru' => [
        ['title' => 'Как сделать идеальную свадебную свечу', 'category' => 'свадьба', 'date' => '15 марта 2024', 'slug' => 'idealnaya-svadebnaya-svecha'],
        ['title' => '10 лучших ароматических свечей для дома', 'category' => 'ароматические', 'date' => '10 марта 2024', 'slug' => 'luchshie-aromaticheskie-svechi'],
        ['title' => 'Идеи новогоднего декора со свечами', 'category' => 'декор', 'date' => '5 марта 2024', 'slug' => 'novogodniy-dekor-so-svechami'],
        ['title' => 'Экологичные свечи из соевого воска', 'category' => 'эко', 'date' => '28 февраля 2024', 'slug' => 'ekologichnye-svechi-iz-soevogo-voska'],
    ],
];

// Получаем текущий язык
$current_lang = isset($_GET['lang']) ? $_GET['lang'] : 'de';
$allowed_langs = ['de', 'en', 'fr', 'it', 'ru'];
if (!in_array($current_lang, $allowed_langs)) {
    $current_lang = 'de';
}

// Текущая категория
$current_category = $_GET['category'] ?? 'all';

// Фильтруем блоги по языку и категории
$blogs = $demo_blogs[$current_lang];
if ($current_category !== 'all') {
    $blogs = array_filter($blogs, function($blog) use ($current_category) {
        return $blog['category'] === $current_category;
    });
}

// Получаем уникальные категории для текущего языка
$categories = array_unique(array_column($demo_blogs[$current_lang], 'category'));

// Тексты для текущего языка
$texts = [
    'de' => ['title' => 'Kerzen Blog', 'subtitle' => 'Entdecken Sie die Welt der handgemachten Kerzen', 'welcome' => 'Willkommen auf unserem Blog!', 'latest' => 'Neueste Beiträge', 'footer' => 'Test-Umgebung für das Newsletter-System'],
    'en' => ['title' => 'Candle Blog', 'subtitle' => 'Discover the world of handmade candles', 'welcome' => 'Welcome to our blog!', 'latest' => 'Latest Posts', 'footer' => 'Test Environment for Newsletter System'],
    'fr' => ['title' => 'Blog Bougies', 'subtitle' => 'Découvrez le monde des bougies faites main', 'welcome' => 'Bienvenue sur notre blog!', 'latest' => 'Derniers articles', 'footer' => 'Environnement de test pour le système de newsletter'],
    'it' => ['title' => 'Blog Candele', 'subtitle' => 'Scopri il mondo delle candele fatte a mano', 'welcome' => 'Benvenuto sul nostro blog!', 'latest' => 'Ultimi post', 'footer' => 'Ambiente di test per il sistema di newsletter'],
    'ru' => [
        'title' => 'Блог о свечах',
        'subtitle' => 'Откройте для себя мир свечей ручной работы',
        'welcome' => 'Добро пожаловать в наш блог!',
        'latest' => 'Последние посты',
        'footer' => 'Тестовая среда для системы рассылки',
    ],
];

$current_texts = $texts[$current_lang];

?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Demo website for testing newsletter system - <?php echo $current_texts['title']; ?>">
    <meta name="keywords" content="candles, blog, newsletter, test, <?php echo $current_lang; ?>">
    <title><?php echo $current_texts['title']; ?> - Test Newsletter System</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
        }

        /* Header */
        .site-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .site-title {
            font-size: 48px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .site-description {
            font-size: 18px;
            opacity: 0.95;
        }

        /* Language Bar */
        .lang-bar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 12px 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .lang-link {
            display: inline-block;
            padding: 8px 24px;
            margin: 0 5px;
            background: #f0f0f0;
            color: #667eea;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .lang-link.active {
            background: #667eea;
            color: white;
        }

        .lang-link:hover:not(.active) {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        /* Category Bar */
        .category-bar {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
        }

        .category-link {
            display: inline-block;
            padding: 6px 18px;
            margin: 3px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 20px;
            font-size: 14px;
            border: 1px solid #667eea;
            transition: all 0.2s;
        }

        .category-link.active {
            background: #667eea;
            color: white;
        }

        .category-link:hover:not(.active) {
            background: #f0f0ff;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-box {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .welcome-box h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 28px;
        }

        .welcome-box p {
            color: #666;
            line-height: 1.6;
        }

        /* Blog Grid */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .blog-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .blog-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
        }

        .blog-content {
            padding: 20px;
        }

        .blog-category {
            display: inline-block;
            padding: 4px 12px;
            background: #f0f0f0;
            border-radius: 20px;
            font-size: 12px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .blog-title {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
            line-height: 1.4;
        }

        .blog-date {
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }

        .blog-excerpt {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        .read-more {
            display: inline-block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        /* Footer */
        .site-footer {
            background: #2d3748;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 60px;
        }

        /* Test Panel */
        .test-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.85);
            color: #0f0;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 10px;
            z-index: 10000;
            max-width: 280px;
            backdrop-filter: blur(5px);
        }

        .test-panel h4 {
            margin-bottom: 8px;
            color: #fff;
            font-size: 11px;
        }

        .test-panel pre {
            margin: 3px 0;
            font-size: 9px;
            overflow-x: auto;
            color: #0f0;
        }

        .site-footer {
            background: #1a1a2e;
            color: #a0aec0;
            padding: 40px 20px;
            margin-top: 60px;
            border-top: 1px solid #2d3748;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .footer-links {
            margin-bottom: 15px;
        }

        .footer-link {
            color: #a0aec0;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-link:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .footer-separator {
            margin: 0 12px;
            color: #2d3748;
        }

        .footer-copyright {
            font-size: 12px;
            opacity: 0.6;
        }

        .status-ok { color: #0f0; }
        .status-error { color: #f00; }

        @media (max-width: 768px) {
            .blog-grid {
                grid-template-columns: 1fr;
            }

            .site-title {
                font-size: 32px;
            }

            .lang-link {
                padding: 6px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="site-header">
    <div class="site-title">🕯️ <?php echo htmlspecialchars($current_texts['title']); ?></div>
    <div class="site-description"><?php echo htmlspecialchars($current_texts['subtitle']); ?></div>
</div>

<div class="lang-bar">
    <a href="?lang=de" class="lang-link <?= $current_lang === 'de' ? 'active' : '' ?>">🇩🇪 Deutsch</a>
    <a href="?lang=en" class="lang-link <?= $current_lang === 'en' ? 'active' : '' ?>">🇬🇧 English</a>
    <a href="?lang=fr" class="lang-link <?= $current_lang === 'fr' ? 'active' : '' ?>">🇫🇷 Français</a>
    <a href="?lang=it" class="lang-link <?= $current_lang === 'it' ? 'active' : '' ?>">🇮🇹 Italiano</a>
    <a href="?lang=ru" class="lang-link <?= $current_lang === 'ru' ? 'active' : '' ?>">🇷🇺 Русский</a>
</div>

<div class="category-bar">
    <a href="?lang=<?php echo $current_lang; ?>&category=all" class="category-link <?php echo $current_category === 'all' ? 'active' : ''; ?>">📚 Alle / All / Tous / Tutti</a>
    <?php foreach ($categories as $cat):
        $cat_labels = [
            'hochzeit' => '💍 Hochzeit', 'duftkerzen' => '🕯️ Duftkerzen', 'deko' => '🎄 Deko', 'nachhaltig' => '🌱 Nachhaltig',
            'wedding' => '💍 Wedding', 'scented' => '🕯️ Scented', 'decor' => '🎄 Decor', 'eco' => '🌱 Eco',
            'mariage' => '💍 Mariage', 'parfumees' => '🕯️ Parfumées', 'deco' => '🎄 Déco', 'ecolo' => '🌱 Écolo',
            'matrimonio' => '💍 Matrimonio', 'profumate' => '🕯️ Profumate', 'decorazioni' => '🎄 Decorazioni', 'ecologiche' => '🌱 Ecologiche'
        ];
        $label = $cat_labels[$cat] ?? $cat;
        ?>
        <a href="?lang=<?php echo $current_lang; ?>&category=<?php echo $cat; ?>" class="category-link <?php echo $current_category === $cat ? 'active' : ''; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
</div>

<div class="container">
    <div class="welcome-box">
        <h2><?php echo htmlspecialchars($current_texts['welcome']); ?></h2>
        <p>
            <?php if ($current_lang === 'de'): ?>
                Hier finden Sie Artikel über Kerzen, Hochzeitsdekoration und vieles mehr.
                Abonnieren Sie unseren Newsletter, um keine Neuigkeiten zu verpassen!
            <?php elseif ($current_lang === 'en'): ?>
                Here you will find articles about candles, wedding decorations and more.
                Subscribe to our newsletter to stay updated!
            <?php elseif ($current_lang === 'fr'): ?>
                Vous trouverez ici des articles sur les bougies, la décoration de mariage et plus encore.
                Abonnez-vous à notre newsletter pour rester informé!
            <?php else: ?>
                Qui troverai articoli su candele, decorazioni per matrimoni e altro ancora.
                Iscriviti alla nostra newsletter per rimanere aggiornato!
            <?php endif; ?>
        </p>
    </div>

    <h2 style="margin-bottom: 20px; color: #333;">📝 <?php echo htmlspecialchars($current_texts['latest']); ?></h2>

    <div class="blog-grid">
        <?php foreach ($blogs as $blog):
            $blog_url = "/{$current_lang}/blog/{$blog['category']}/{$blog['slug']}";
            ?>
            <article class="blog-card" onclick="window.location.href='<?php echo $blog_url; ?>'">
                <div class="blog-image">🕯️✨</div>
                <div class="blog-content">
                    <span class="blog-category"><?php echo htmlspecialchars($blog['category']); ?></span>
                    <h3 class="blog-title"><?php echo htmlspecialchars($blog['title']); ?></h3>
                    <div class="blog-date">📅 <?php echo $blog['date']; ?></div>
                    <p class="blog-excerpt">
                        <?php
                        $excerpts = [
                            'de' => 'Entdecken Sie die Kunst der Kerzenherstellung und lassen Sie sich von unseren kreativen Ideen inspirieren...',
                            'en' => 'Discover the art of candle making and get inspired by our creative ideas...',
                            'fr' => 'Découvrez l\'art de la fabrication de bougies et laissez-vous inspirer par nos idées créatives...',
                            'it' => 'Scopri l\'arte della fabbricazione di candele e lasciati ispirare dalle nostre idee creative...'
                        ];
                        echo $excerpts[$current_lang];
                        ?>
                    </p>
                    <a href="<?php echo $blog_url; ?>" class="read-more">→
                        <?php echo $current_lang === 'de' ? 'Weiterlesen' : ($current_lang === 'en' ? 'Read more' : ($current_lang === 'fr' ? 'Lire la suite' : 'Leggi di più')); ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-links">
            <a href="/newsletter/rss/rss.php?lang=<?php echo $current_lang; ?>" class="footer-link">RSS Feed</a>
            <span class="footer-separator">|</span>
            <a href="/newsletter/api/unsubscribe-email.php" class="footer-link">Unsubscribe</a>
            <span class="footer-separator">|</span>
            <a href="/" class="footer-link">Home</a>
        </div>
        <p class="footer-copyright">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($current_texts['title']); ?>. All rights reserved.</p>
    </div>
</footer>


<!-- Тестовая панель отладки -->
<div class="test-panel">
    <h4>🔧 Debug Info</h4>
    <pre>
Lang: <?php echo $current_lang; ?>
Category: <?php echo $current_category; ?>
Session ID: <?php echo session_id(); ?>
Modal shown: <?php echo $_SESSION['nl_modal_shown_count'] ?? 0; ?>x
Debug: <?php echo defined('NL_DEBUG') && NL_DEBUG ? 'ON' : 'OFF'; ?>
Time: <?php echo date('H:i:s'); ?>
        </pre>
    <pre id="db-status">Checking DB...</pre>
</div>

<script>
    // Проверка API доступности
    fetch('/newsletter/api/subscribe.php', {
        method: 'OPTIONS'
    })
        .then(() => {
            document.getElementById('db-status').innerHTML = 'API: <span class="status-ok">✅ Available</span>';
        })
        .catch(() => {
            document.getElementById('db-status').innerHTML = 'API: <span class="status-error">❌ Not available</span>';
        });

    // Логируем загрузку
    if (window.nlConfig) {
        console.log('Newsletter Config:', window.nlConfig);
    } else {
        console.log('Newsletter not loaded yet');
    }
</script>
</body>
</html>