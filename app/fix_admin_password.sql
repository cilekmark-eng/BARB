-- =====================================================
-- fix_admin_password.sql
-- Используй reset_admin.php вместо этого файла!
-- Открой в браузере: http://localhost/shop/public/reset_admin.php
-- =====================================================

USE `shop_db`;

-- Удаляем старого админа
DELETE FROM `users` WHERE `email` = 'admin@test.com';

-- Временно ставим хеш от password_hash('admin123')
-- Если не работает — используй reset_admin.php
INSERT INTO `users` (`name`, `email`, `password`, `role`, `created_at`) VALUES (
  'Администратор',
  'admin@test.com',
  '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm',
  'admin',
  NOW()
);
