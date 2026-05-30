# Newsletter System – Installation & Setup Guide

## How it works (brief)

1. Visitor sees a modal → chooses email or push → subscribes
2. Data is saved to database (language, categories, context)
3. You run the matcher script → it finds relevant blog posts
4. You run the sender script → notifications go to subscribers

## Project Structure

```
newsletter/
├── newsletter.php      — include this on your website (shows modal)
├── newsletter.css      — styles
├── newsletter.js       — modal logic (subscription, Service Worker)
├── config.php          — all settings (DB, languages, SMTP, VAPID)
├── bootstrap.php       — autoloader (loads config and functions/)
├── functions/          — core functions (database, subscribers, matching)
├── matching/           — matcher.php (finds relevant blogs)
├── senders/            — send_email.php, send_push.php
├── api/                — subscribe.php, unsubscribe-email.php
├── rss/                — rss.php (RSS feed generator)
├── sitemap/            — parser.php (imports blogs from sitemap)
├── webpush/            — sw.js (Service Worker), vapid.php (key generator)
└── sql/                — schema.sql (database tables)
```

## Requirements

- PHP 8.4 or higher
- MySQL / MariaDB
- Composer
- OpenSSL
- HTTPS (for push notifications in production)

## Installation

### 1. Copy files to your server

Copy the entire `newsletter` folder to your website root.

### 2. Install Composer dependencies

```bash
cd newsletter
composer install
```

### 3. Create database

```bash
mysql -u username -p database_name < sql/schema.sql
```

### 4. Configure `config.php`

Edit `config.php` and set:
- Database credentials (`db` section)
- Website name and domain (`website` section)
- Supported languages (de, en, fr, it, ru)
- SMTP settings for email
- Matching rules (`matching` section)

### 5. Generate VAPID keys (for push notifications)

```bash
cd newsletter
php webpush/vapid.php
```

If keys is dont copy automatically copy the generated keys to `config.php` in the `webpush` section.

### 6. Include on your website

Add this line to your website template (anywhere, preferably before `</body>`):

```php
<?php include_once '/path/to/newsletter/newsletter.php'; ?>
```

## Usage

### 1. Import blogs from sitemap

```bash
cd newsletter
php sitemap/parser.php https://yoursite.com/sitemap.xml yoursite.com
```

### 2. Find matches (see what will be sent)

```bash
php matching/matcher.php
```

### 3. Send email newsletter

```bash
php senders/send_email.php
# Type 'send' to confirm
```

For auto mode (cron):

```bash
php senders/send_email.php --auto
```

### 4. Send push notifications

```bash
php senders/send_push.php
# Type 'send' to confirm
```

For auto mode (cron):

```bash
php senders/send_push.php --auto
```

### 5. RSS feed

```
https://yoursite.com/newsletter/rss/rss.php?lang=en
https://yoursite.com/newsletter/rss/rss.php?lang=ru&limit=20
https://yoursite.com/newsletter/rss/rss.php?format=json
```

## Languages Supported

- German (de)
- English (en)
- French (fr)
- Italian (it)
- Russian (ru)

## Database Tables

| Table | Purpose |
|-------|---------|
| `nl_subscribers` | Stores subscribers (email + push) |
| `nl_blogs` | Blog cache from sitemap |
| `nl_blog_groups` | Groups same article across languages |
| `nl_sent_log` | Prevents sending same blog twice |

## Troubleshooting

### Push notifications not working

1. Make sure you're on HTTPS (or localhost for testing)
2. Check that `/sw.js` is accessible
3. Verify VAPID keys in `config.php`
4. Run `php webpush/vapid.php` to regenerate keys

### Email not sending

1. Check SMTP settings in `config.php`
2. For Gmail, use an App Password (not your regular password)
3. Check spam folder

### Modal not showing

1. Verify `newsletter.php` is included correctly
2. Check browser console for JavaScript errors
3. Make sure CSS and JS files are loaded (check Network tab)

## Security Notes

- Set `NL_DEBUG = false` in production
- Keep `config.php` secure (it contains passwords)
- Use HTTPS in production
- Regular backups of database