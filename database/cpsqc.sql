-- =====================================================
-- CPSQC Database Schema
-- AlerTara QC - Community Policing and Surveillance System
-- Database: LGU
-- =====================================================

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `LGU` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `LGU`;

-- =====================================================
-- Table: admins
-- Description: Administrator and user accounts
-- =====================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `full_name` VARCHAR(255) DEFAULT NULL,
    `role` VARCHAR(50) DEFAULT 'User',
    `status` VARCHAR(20) DEFAULT 'Active',
    `failed_attempts` INT DEFAULT 0,
    `last_failed_at` DATETIME DEFAULT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: nw_members (formerly volunteers)
-- Description: Neighborhood Watch member applications and active members
-- =====================================================
CREATE TABLE IF NOT EXISTS `nw_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `contact` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `address` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `skills` TEXT NOT NULL,
    `availability` VARCHAR(50) NOT NULL DEFAULT 'Flexible',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `notes` TEXT DEFAULT NULL,
    `photo_data` LONGTEXT NULL,
    `photo_id_data` LONGTEXT NULL,
    `certifications_data` LONGTEXT NULL,
    `certifications_description` TEXT DEFAULT NULL,
    `emergency_contact_name` VARCHAR(255) NOT NULL,
    `emergency_contact_number` VARCHAR(50) NOT NULL,
    `volunteer_code` VARCHAR(50) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_category` (`category`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: tips
-- Description: Anonymous tips submitted by the public
-- =====================================================
CREATE TABLE IF NOT EXISTS `tips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tip_id` VARCHAR(255) UNIQUE NOT NULL,
    `location` TEXT NOT NULL,
    `description` TEXT NOT NULL,
    `contact_number` VARCHAR(50) DEFAULT NULL,
    `photo_data` LONGTEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Under Review',
    `outcome` VARCHAR(100) DEFAULT 'No Outcome Yet',
    `police_backup_reason` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tip_id` (`tip_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: patrols
-- Description: BPSO personnel information
-- =====================================================
CREATE TABLE IF NOT EXISTS `patrols` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bpso_personnel_id` VARCHAR(50) NOT NULL UNIQUE,
    `personnel_name` VARCHAR(255) NOT NULL,
    `contact_number` VARCHAR(50) NOT NULL,
    `schedule` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Available',
    `email` VARCHAR(255) NULL UNIQUE,
    `password_hash` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bpso_personnel_id` (`bpso_personnel_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: patrol_logs
-- Description: BPSO patrol activity logs
-- =====================================================
CREATE TABLE IF NOT EXISTS `patrol_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patrol_id` INT NULL,
    `schedule_id` INT NULL,
    `personnel_name` VARCHAR(255) NOT NULL,
    `route` VARCHAR(255) NOT NULL DEFAULT '',
    `date` DATE NOT NULL,
    `time` VARCHAR(50) DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
    `incidents` TEXT DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `location` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_patrol_id` (`patrol_id`),
    INDEX `idx_schedule_id` (`schedule_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: patrol_schedules
-- Description: Admin-assigned patrol schedules for BPSO personnel
-- =====================================================
CREATE TABLE IF NOT EXISTS `patrol_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patrol_id` INT NOT NULL,
    `personnel_name` VARCHAR(255) NOT NULL,
    `route` VARCHAR(255) NOT NULL,
    `location` TEXT DEFAULT NULL,
    `schedule_date` DATE NOT NULL,
    `schedule_time` VARCHAR(50) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_patrol_id` (`patrol_id`),
    INDEX `idx_schedule_date` (`schedule_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: complaints
-- Description: Complaints submitted by the public
-- =====================================================
CREATE TABLE IF NOT EXISTS `complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` VARCHAR(50) NOT NULL UNIQUE,
    `complainant_name` VARCHAR(255) NOT NULL,
    `contact_number` VARCHAR(50) NOT NULL,
    `address` TEXT NOT NULL,
    `incident_date` DATE NOT NULL,
    `incident_time` TIME DEFAULT NULL,
    `defendant_name` VARCHAR(255) NOT NULL DEFAULT '',
    `defendant_address` TEXT NOT NULL DEFAULT '',
    `defendant_contact_number` VARCHAR(50) NOT NULL DEFAULT '',
    `complaint_type` VARCHAR(100) NOT NULL,
    `location` TEXT NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `priority` VARCHAR(20) NOT NULL DEFAULT 'Low',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `assigned_to` VARCHAR(255) DEFAULT 'Pending Assignment',
    `assigned_patrol_id` INT NULL,
    `resolution_report` TEXT DEFAULT NULL,
    `assigned_at` DATETIME DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint_id` (`complaint_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_submitted_at` (`submitted_at`),
    INDEX `idx_incident_date` (`incident_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: events
-- Description: Events and activities (referenced in dashboard)
-- =====================================================
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_name` VARCHAR(255) NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME DEFAULT NULL,
    `location` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'Scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_event_date` (`event_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: notifications
-- Description: Stores user notifications
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL, -- e.g., 'complaint', 'tip', 'volunteer'
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: login_history
-- Description: Stores login history for all users
-- =====================================================
CREATE TABLE IF NOT EXISTS `login_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `login_time` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Success',
    `logout_time` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_username` (`username`),
    INDEX `idx_login_time` (`login_time`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Default Admin Account
-- Username: admin
-- Password: admin123 (should be changed after first login)
-- Note: The password_hash is generated using PHP's password_hash('admin123', PASSWORD_DEFAULT)
-- If you need to regenerate it, use: password_hash('admin123', PASSWORD_DEFAULT)
-- =====================================================
INSERT INTO `admins` (`username`, `password_hash`, `email`, `full_name`, `role`, `status`) 
VALUES ('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', NULL, 'Default Administrator', 'Admin', 'Active')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- =====================================================
-- End of Schema
-- =====================================================

