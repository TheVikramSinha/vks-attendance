-- ============================================================
-- VKS ATTENDANCE SYSTEM - COMPLETE DATABASE SCHEMA
-- Version: 1.0
-- Date: 2026-02-15
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- TABLE: users
-- Stores all system users with role-based access
-- ============================================================
CREATE TABLE `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
  `is_manager` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Manager can also be a regular user',
  `manager_id` INT(11) UNSIGNED NULL COMMENT 'Reports to this manager',
  `profile_image` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_manager` (`manager_id`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: attendance
-- Core attendance tracking with 6/8/10 hour business logic
-- ============================================================
CREATE TABLE `attendance` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `punch_in` DATETIME NOT NULL,
  `punch_in_location` VARCHAR(255) NULL COMMENT 'Lat,Lon format',
  `punch_out` DATETIME NULL,
  `punch_out_location` VARCHAR(255) NULL COMMENT 'Lat,Lon format',
  `total_hours` DECIMAL(5,2) NULL COMMENT 'Auto-calculated on punch out',
  `status` ENUM('full_day', 'short_day', 'half_day', 'pending') NOT NULL DEFAULT 'pending',
  `auto_logged_out` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if system forced logout at 10hrs',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_date` (`user_id`, `attendance_date`),
  KEY `idx_date` (`attendance_date`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: breaks
-- Multiple breaks per attendance session
-- ============================================================
CREATE TABLE `breaks` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` INT(11) UNSIGNED NOT NULL,
  `break_start` DATETIME NOT NULL,
  `break_end` DATETIME NULL,
  `duration_minutes` INT(11) NULL COMMENT 'Auto-calculated on break end',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance` (`attendance_id`),
  FOREIGN KEY (`attendance_id`) REFERENCES `attendance`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: leave_categories
-- Admin-configurable leave types with custom quotas
-- ============================================================
CREATE TABLE `leave_categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `category_code` VARCHAR(20) NOT NULL UNIQUE,
  `has_monthly_quota` TINYINT(1) NOT NULL DEFAULT 0,
  `monthly_quota_days` DECIMAL(4,1) NULL,
  `has_quarterly_quota` TINYINT(1) NOT NULL DEFAULT 0,
  `quarterly_quota_days` DECIMAL(4,1) NULL,
  `has_annual_quota` TINYINT(1) NOT NULL DEFAULT 0,
  `annual_quota_days` DECIMAL(4,1) NULL,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: leave_balances
-- User-specific leave balances (quotas reset on Dec 31)
-- ============================================================
CREATE TABLE `leave_balances` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `leave_category_id` INT(11) UNSIGNED NOT NULL,
  `monthly_balance` DECIMAL(4,1) NULL,
  `quarterly_balance` DECIMAL(4,1) NULL,
  `annual_balance` DECIMAL(4,1) NULL,
  `comp_off_balance` DECIMAL(4,1) NOT NULL DEFAULT 0 COMMENT 'Manager can add comp-offs',
  `last_reset_date` DATE NULL COMMENT 'Last quota reset (Dec 31)',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_category` (`user_id`, `leave_category_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_category_id`) REFERENCES `leave_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: leave_requests
-- Leave application and approval workflow
-- ============================================================
CREATE TABLE `leave_requests` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `leave_category_id` INT(11) UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_half_day` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Half day deducts 0.5',
  `total_days` DECIMAL(4,1) NOT NULL COMMENT 'Including half-day calculation',
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT(11) UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_category_id`) REFERENCES `leave_categories`(`id`),
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications
-- System notifications for violations, approvals, etc.
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `type` ENUM('break_violation', 'leave_approved', 'leave_rejected', 'auto_logout', 'comp_off_added', 'general') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `action_url` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_type` (`type`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: system_settings
-- Dynamic branding and configuration
-- ============================================================
CREATE TABLE `system_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_type` ENUM('text', 'color', 'file', 'number', 'boolean') NOT NULL DEFAULT 'text',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: offline_queue
-- Store offline punch-in/out requests for sync when online
-- ============================================================
CREATE TABLE `offline_queue` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `action_type` ENUM('punch_in', 'punch_out', 'break_start', 'break_end') NOT NULL,
  `action_data` JSON NOT NULL COMMENT 'Stores location, timestamp, etc.',
  `synced` TINYINT(1) NOT NULL DEFAULT 0,
  `synced_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_synced` (`user_id`, `synced`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: daily_reports
-- End-of-day break violation reports
-- ============================================================
CREATE TABLE `daily_reports` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_date` DATE NOT NULL,
  `manager_id` INT(11) UNSIGNED NOT NULL,
  `report_data` JSON NOT NULL COMMENT 'Contains all violations for the day',
  `is_viewed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_manager_date` (`manager_id`, `report_date`),
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: audit_logs
-- Track all critical system actions
-- ============================================================
CREATE TABLE `audit_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(50) NULL,
  `record_id` INT(11) UNSIGNED NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT DATA INSERTION
-- ============================================================

-- Default Admin User (Password: Admin@123)
INSERT INTO `users` (`employee_id`, `email`, `password`, `full_name`, `role`, `is_active`) 
VALUES ('ADMIN001', 'admin@vks.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1);

-- Default System Settings (Branding)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('company_name', 'VKS Solutions', 'text'),
('company_logo', NULL, 'file'),
('theme_bg_color', '#121212', 'color'),
('theme_primary_color', '#BFC6C4', 'color'),
('theme_text_color', '#E8E2D8', 'color'),
('theme_success_color', '#6F8F72', 'color'),
('theme_alert_color', '#F2A65A', 'color'),
('enable_geolocation', '1', 'boolean'),
('enable_pwa', '1', 'boolean'),
('max_break_minutes', '75', 'number'),
('auto_logout_hours', '10', 'number'),
('timezone', 'Asia/Kolkata', 'text');

-- Default Leave Categories
INSERT INTO `leave_categories` (`category_name`, `category_code`, `has_annual_quota`, `annual_quota_days`, `requires_approval`, `is_paid`, `description`) VALUES
('Casual Leave', 'CL', 1, 12.0, 1, 1, 'For personal work or emergencies'),
('Sick Leave', 'SL', 1, 12.0, 1, 1, 'For medical reasons'),
('Earned Leave', 'EL', 1, 15.0, 1, 1, 'Annual planned leave'),
('Compensatory Off', 'CO', 0, NULL, 1, 1, 'Compensation for working on holidays');

-- ============================================================
-- STORED PROCEDURES & TRIGGERS
-- ============================================================

-- Trigger: Auto-calculate total hours and status on punch out
DELIMITER //
CREATE TRIGGER `calculate_attendance_status` 
BEFORE UPDATE ON `attendance`
FOR EACH ROW
BEGIN
  IF NEW.punch_out IS NOT NULL AND OLD.punch_out IS NULL THEN
    -- Calculate total hours
    SET NEW.total_hours = TIMESTAMPDIFF(MINUTE, NEW.punch_in, NEW.punch_out) / 60.0;
    
    -- Apply 6/8/10 hour business logic
    IF NEW.total_hours < 6.0 THEN
      SET NEW.status = 'half_day';
    ELSEIF NEW.total_hours >= 6.0 AND NEW.total_hours < 8.0 THEN
      SET NEW.status = 'short_day';
    ELSEIF NEW.total_hours >= 8.0 THEN
      SET NEW.status = 'full_day';
    END IF;
  END IF;
END//
DELIMITER ;

-- Trigger: Auto-calculate break duration
DELIMITER //
CREATE TRIGGER `calculate_break_duration` 
BEFORE UPDATE ON `breaks`
FOR EACH ROW
BEGIN
  IF NEW.break_end IS NOT NULL AND OLD.break_end IS NULL THEN
    SET NEW.duration_minutes = TIMESTAMPDIFF(MINUTE, NEW.break_start, NEW.break_end);
  END IF;
END//
DELIMITER ;

-- Trigger: Check for overlapping leave requests
DELIMITER //
CREATE TRIGGER `check_overlapping_leaves` 
BEFORE INSERT ON `leave_requests`
FOR EACH ROW
BEGIN
  DECLARE overlap_count INT;
  
  SELECT COUNT(*) INTO overlap_count
  FROM `attendance`
  WHERE `user_id` = NEW.user_id
    AND `attendance_date` BETWEEN NEW.start_date AND NEW.end_date
    AND `punch_in` IS NOT NULL;
  
  IF overlap_count > 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Cannot request leave for dates with existing attendance records';
  END IF;
END//
DELIMITER ;

-- ============================================================
-- END OF SCHEMA
-- ============================================================
