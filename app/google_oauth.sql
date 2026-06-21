-- =====================================================
-- google_oauth.sql
-- Выполни в phpMyAdmin → база shop_db → вкладка SQL
-- =====================================================

USE `shop_db`;

-- Добавить колонку avatar в таблицу users (если нет)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `avatar` VARCHAR(500) DEFAULT NULL AFTER `address`;

-- Таблица для социальных аккаунтов
CREATE TABLE IF NOT EXISTS `social_accounts` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT UNSIGNED NOT NULL,
  `provider`         VARCHAR(50)  NOT NULL,
  `provider_user_id` VARCHAR(255) NOT NULL,
  `provider_email`   VARCHAR(255) DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_provider_user` (`provider`, `provider_user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_social_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
