-- =========================================
-- CRM SCHEMA FOR BARBERSHOP STORE
-- =========================================

USE `shop_db`;

-- -------------------------
-- LEADS (Потенциальные клиенты)
-- -------------------------
CREATE TABLE IF NOT EXISTS `crm_leads` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `email`       VARCHAR(191) DEFAULT NULL,
  `phone`       VARCHAR(30) DEFAULT NULL,
  `source`      VARCHAR(100) DEFAULT NULL COMMENT 'Источник: сайт, реклама, рекомендация и т.д.',
  `status`      ENUM('new','contacted','qualified','lost','customer') NOT NULL DEFAULT 'new',
  `notes`       TEXT DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Если лид стал пользователем',
  `assigned_to` INT UNSIGNED DEFAULT NULL COMMENT 'Менеджер, ответственный за лид',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_lead_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lead_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_lead_status` (`status`),
  INDEX `idx_lead_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- INTERACTIONS (Взаимодействия с клиентами)
-- -------------------------
CREATE TABLE IF NOT EXISTS `crm_interactions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `lead_id`     INT UNSIGNED DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Если взаимодействие с существующим пользователем',
  `type`        ENUM('call','meeting','email','note') NOT NULL DEFAULT 'note',
  `description` TEXT NOT NULL,
  `created_by`  INT UNSIGNED NOT NULL COMMENT 'Кто создал запись',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_interaction_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_interaction_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_interaction_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_interaction_lead` (`lead_id`),
  INDEX `idx_interaction_user` (`user_id`),
  INDEX `idx_interaction_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- TASKS (Задачи и напоминания)
-- -------------------------
CREATE TABLE IF NOT EXISTS `crm_tasks` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status`      ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `priority`    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `due_date`    DATETIME DEFAULT NULL,
  `lead_id`     INT UNSIGNED DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Связанная с пользователем',
  `assigned_to` INT UNSIGNED DEFAULT NULL COMMENT 'Кому назначена задача',
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_task_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_task_status` (`status`),
  INDEX `idx_task_priority` (`priority`),
  INDEX `idx_task_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
