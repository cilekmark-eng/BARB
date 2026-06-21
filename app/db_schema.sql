-- =========================================
-- BARBERSHOP INTERNET STORE - DB SCHEMA
-- Encoding: utf8mb4
-- =========================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `shop_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `shop_db`;

-- -------------------------
-- USERS
-- -------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(191) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('guest','user','admin') NOT NULL DEFAULT 'user',
  `phone`      VARCHAR(30) DEFAULT NULL,
  `address`    TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- CATEGORIES
-- -------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(110) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- TAGS
-- -------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(60) NOT NULL UNIQUE,
  `slug` VARCHAR(70) NOT NULL UNIQUE,
  INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- PRODUCTS
-- -------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `slug`        VARCHAR(220) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'percent 0-100',
  `stock`       INT UNSIGNED NOT NULL DEFAULT 0,
  `views`       INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_category`  (`category_id`),
  INDEX `idx_is_active` (`is_active`),
  INDEX `idx_created`   (`created_at`),
  INDEX `idx_price`     (`price`),
  FULLTEXT INDEX `ft_search` (`name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- PRODUCT IMAGES
-- -------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `filename`   VARCHAR(255) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- PRODUCT TAGS (many-to-many)
-- -------------------------
CREATE TABLE IF NOT EXISTS `product_tags` (
  `product_id` INT UNSIGNED NOT NULL,
  `tag_id`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `tag_id`),
  CONSTRAINT `fk_pt_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_tag`     FOREIGN KEY (`tag_id`)     REFERENCES `tags`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- CARTS
-- -------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED DEFAULT NULL UNIQUE,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- CART ITEMS
-- -------------------------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `qty`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT `fk_ci_cart`    FOREIGN KEY (`cart_id`)    REFERENCES `carts`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_ci_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_cart_product` (`cart_id`, `product_id`),
  INDEX `idx_cart_id` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- ORDERS
-- -------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(191) NOT NULL,
  `phone`      VARCHAR(30)  NOT NULL,
  `address`    TEXT NOT NULL,
  `comment`    TEXT DEFAULT NULL,
  `total`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`     ENUM('new','paid','shipped','delivered','canceled') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status`  (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- ORDER ITEMS
-- -------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`   INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL,
  `qty`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  INDEX `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- REVIEWS
-- -------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5',
  `text`       TEXT DEFAULT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL,
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- WISHLIST
-- -------------------------
CREATE TABLE IF NOT EXISTS `wishlist` (
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `added_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `product_id`),
  CONSTRAINT `fk_wl_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_wl_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- =========================================
-- SEED DATA
-- =========================================

-- Admin user (password: admin123)
INSERT INTO `users` (`name`,`email`,`password`,`role`) VALUES
('Администратор','admin@test.com','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');

-- Categories
INSERT INTO `categories` (`name`,`slug`,`description`,`sort_order`) VALUES
('Машинки для стрижки','mashinka-dlya-strizhki','Профессиональные машинки для стрижки волос',1),
('Триммеры','trimmery','Триммеры для окантовки и бороды',2),
('Бритвы','britvy','Опасные и безопасные бритвы',3),
('Ножницы','nozhnicy','Парикмахерские ножницы',4),
('Средства для укладки','sredstva-ukladki','Помады, воски, гели',5),
('Уход за бородой','uhod-za-borodoj','Масла, бальзамы, кремы',6),
('Аксессуары','aksessuary','Расчёски, кисти, фартуки',7);

-- Tags
INSERT INTO `tags` (`name`,`slug`) VALUES
('Профессиональный','professionalnyj'),
('Беспроводной','besprovodnoy'),
('Для дома','dlya-doma'),
('Японская сталь','yaponskaya-stal'),
('Натуральный','naturalnyj'),
('Хит продаж','hit-prodazh'),
('Новинка','novinka'),
('Скидка','skidka');

-- Products
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`discount`,`stock`) VALUES
(1,'Машинка Wahl Magic Clip','wahl-magic-clip','Профессиональная машинка для стрижки с регулировкой длины. Мощный мотор, тихая работа. Идеально для fade и taper стрижек.',389.00,10,15),
(1,'Машинка Andis Master','andis-master','Легендарная машинка Andis Master — стандарт профессиональных барберов. Надёжный электромагнитный мотор.',490.00,0,8),
(1,'Машинка Gamma+ Absolute Hitter','gamma-absolute-hitter','Беспроводная машинка с литиевым аккумулятором 4400 мАч. До 3 часов работы без подзарядки.',570.00,15,12),
(2,'Триммер Wahl Detailer','wahl-detailer','Триммер для чёткой окантовки и детальной работы. Тонкий нож T-blade, 0 мм.',149.00,0,20),
(2,'Триммер BabylissPro FX787','babyliss-fx787','Профессиональный триммер для окантовки. Сверхмощный роторный мотор.',259.00,5,14),
(3,'Опасная бритва Dovo Bergischer Löwe','dovo-bergischer-lowe','Немецкая опасная бритва Dovo с лезвием 6/8 дюйма. Углеродистая сталь, рукоять из натурального рога.',220.00,0,6),
(3,'Т-образная бритва Merkur 34C','merkur-34c','Классическая двухсторонняя безопасная бритва Merkur. Тяжёлая рукоять, агрессивная агрессия.',95.00,0,18),
(4,'Ножницы Kasho ZAD 6.0','kasho-zad-60','Японские парикмахерские ножницы из хирургической стали. Лезвие 6.0 дюйма.',680.00,0,5),
(4,'Ножницы Joewell Classic 5.5','joewell-classic-55','Ножницы из стали Hitachi. Идеально сбалансированы, удобная эргономичная ручка.',450.00,10,7),
(5,'Помада Uppercut Deluxe','uppercut-deluxe-pomade','Помада с сильной фиксацией и средним блеском. Аромат кокоса, легко смывается водой.',38.00,0,35),
(5,'Воск Layrite Original','layrite-original','Крем-помада средней фиксации. Классический аромат ванили, легко наносится и распределяется.',34.00,0,40),
(5,'Глина Reuzel Matte Clay','reuzel-matte-clay','Глина сильной фиксации с матовым финишем. Текстурирующий эффект, не склеивает волосы.',29.00,0,28),
(6,'Масло для бороды Suavecito','suavecito-beard-oil','Питательное масло для бороды. Смягчает, увлажняет, придаёт блеск. Аромат сандала.',27.00,0,22),
(6,'Бальзам для бороды Bearded Bastard','bearded-bastard-balm','Натуральный бальзам с маслом ши и пчелиным воском. Укрощает и укладывает бороду.',32.00,20,19),
(7,'Расчёска парикмахерская Hercules','hercules-comb','Классическая парикмахерская расчёска Hercules Sägemann из вулканита. Антистатическая.',19.00,0,50),
(7,'Кисть для сметания волос','kist-dlya-smetaniya','Натуральная щётина, деревянная рукоять. Мягко удаляет остриженные волосы.',14.00,0,30);

-- Product tags
INSERT INTO `product_tags` (`product_id`,`tag_id`) VALUES
(1,1),(1,6),
(2,1),(2,6),
(3,1),(3,2),(3,7),
(4,1),(4,6),
(5,1),(5,8),
(6,4),(6,1),
(7,3),
(8,4),(8,1),
(9,4),(9,1),
(10,3),(10,6),
(11,3),(11,6),
(12,3),
(13,5),(13,3),
(14,5),(14,3),(14,8),
(15,1),
(16,5),(16,3);
