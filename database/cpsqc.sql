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
-- Table: volunteers
-- Description: Volunteer registration data
-- =====================================================
CREATE TABLE IF NOT EXISTS `volunteers` (
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
    `photo_data` LONGTEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Under Review',
    `outcome` VARCHAR(100) DEFAULT 'No Outcome Yet',
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tip_id` (`tip_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: patrols
-- Description: Patrol officers information
-- =====================================================
CREATE TABLE IF NOT EXISTS `patrols` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `badge_number` VARCHAR(50) NOT NULL UNIQUE,
    `officer_name` VARCHAR(255) NOT NULL,
    `contact_number` VARCHAR(50) NOT NULL,
    `schedule` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_badge_number` (`badge_number`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: members
-- Description: Neighborhood watch members
-- =====================================================
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `age` INT NOT NULL,
    `address` TEXT NOT NULL,
    `gender` VARCHAR(20) NOT NULL,
    `photo_data` LONGTEXT NULL,
    `id_data` LONGTEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`)
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
    `complaint_type` VARCHAR(100) NOT NULL,
    `location` TEXT NOT NULL,
    `description` TEXT NOT NULL,
    `priority` VARCHAR(20) NOT NULL DEFAULT 'Low',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `assigned_to` VARCHAR(255) DEFAULT 'Pending Assignment',
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

