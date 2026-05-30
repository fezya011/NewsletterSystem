
-- subscribers
CREATE TABLE IF NOT EXISTS `nl_subscribers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NULL,
    `push_endpoint` TEXT NULL,
    `push_auth` VARCHAR(255) NULL,
    `push_p256dh` VARCHAR(255) NULL,
    `subscription_type` ENUM('email', 'push', 'both') NOT NULL,
    `status` ENUM('pending', 'active', 'unsubscribed', 'bounced') DEFAULT 'pending',
    `website` VARCHAR(100) NOT NULL,
    `language` VARCHAR(10) NOT NULL,
    `categories` TEXT NULL COMMENT 'JSON array of interested categories',
    `unsubscribe_token` VARCHAR(64) UNIQUE NOT NULL,
    `subscribed_at` DATETIME NOT NULL,
    `confirmed_at` DATETIME NULL,
    `unsubscribed_at` DATETIME NULL,
    INDEX idx_email (`email`),
    INDEX idx_website_lang (`website`, `language`, `status`),
    INDEX idx_token (`unsubscribe_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- blog cache from sitemap
CREATE TABLE IF NOT EXISTS `nl_blogs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NULL COMMENT 'Connection with other language versions',
    `url` VARCHAR(500) NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `content_preview` TEXT NULL,
    `category` VARCHAR(100) NULL,
    `keywords` TEXT NULL COMMENT 'JSON array',
    `language` VARCHAR(10) NOT NULL,
    `website` VARCHAR(100) NOT NULL,
    `published_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,
    `first_seen_at` DATETIME NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    UNIQUE KEY uk_url (`url`(191)),
    INDEX idx_website_lang (`website`, `language`, `is_active`),
    INDEX idx_group (`group_id`),
    INDEX idx_published (`published_at` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- blog groups (different languages of the same article)
CREATE TABLE IF NOT EXISTS `nl_blog_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_key` VARCHAR(255) NOT NULL COMMENT 'slug или уникальный идентификатор',
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY uk_group_key (`group_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- sending log (for deduplication)
CREATE TABLE IF NOT EXISTS `nl_sent_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `blog_group_id` INT UNSIGNED NOT NULL,
    `blog_id` INT UNSIGNED NOT NULL,
    `sent_via` ENUM('email', 'push') NOT NULL,
    `sent_at` DATETIME NOT NULL,
    `opened_at` DATETIME NULL,
    `clicked_at` DATETIME NULL,
    INDEX idx_subscriber (`subscriber_id`),
    INDEX idx_blog_group (`blog_group_id`),
    UNIQUE KEY uk_subscriber_blog_group (`subscriber_id`, `blog_group_id`, `sent_via`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `nl_subscribers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- add a foreign key for nl_blogs.group_id after creating the groups table
ALTER TABLE `nl_blogs`
    ADD FOREIGN KEY (`group_id`) REFERENCES `nl_blog_groups`(`id`) ON DELETE SET NULL;