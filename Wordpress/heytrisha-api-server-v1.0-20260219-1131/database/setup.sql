-- =====================================================
-- HeyTrisha API Database Setup Script
-- =====================================================
-- This script creates the database and all required tables
-- Run this script in your MySQL/MariaDB database
-- =====================================================

-- Create database (adjust name as needed)
CREATE DATABASE IF NOT EXISTS `heytrisha_api` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `heytrisha_api`;

-- =====================================================
-- Table: sites
-- Stores WordPress site registrations and API keys
-- =====================================================
CREATE TABLE IF NOT EXISTS `sites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_url` VARCHAR(255) NOT NULL,
  `api_key_hash` VARCHAR(64) NOT NULL,
  `openai_key` TEXT NOT NULL COMMENT 'Encrypted OpenAI API key',
  `email` VARCHAR(255) DEFAULT NULL,
  `username` VARCHAR(255) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed password',
  `first_name` VARCHAR(255) DEFAULT NULL,
  `last_name` VARCHAR(255) DEFAULT NULL,
  `db_name` VARCHAR(255) DEFAULT NULL COMMENT 'WordPress database name',
  `db_username` VARCHAR(255) DEFAULT NULL COMMENT 'WordPress database username',
  `db_password` TEXT DEFAULT NULL COMMENT 'Encrypted WordPress database password',
  `wordpress_version` VARCHAR(50) DEFAULT NULL,
  `woocommerce_version` VARCHAR(50) DEFAULT NULL,
  `plugin_version` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `query_count` INT NOT NULL DEFAULT 0,
  `last_query_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sites_site_url_unique` (`site_url`),
  UNIQUE KEY `sites_api_key_hash_unique` (`api_key_hash`),
  UNIQUE KEY `sites_username_unique` (`username`),
  KEY `sites_is_active_index` (`is_active`),
  KEY `sites_created_at_index` (`created_at`),
  KEY `sites_username_index` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Laravel Framework Tables (if using Laravel migrations)
-- =====================================================

-- Table: migrations (tracks which migrations have run)
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Optional: Create database user for API
-- =====================================================
-- Uncomment and modify as needed:
-- CREATE USER IF NOT EXISTS 'heytrisha_api'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT ALL PRIVILEGES ON `heytrisha_api`.* TO 'heytrisha_api'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- Verification Query
-- =====================================================
-- Run this to verify the table was created:
-- SHOW TABLES;
-- DESCRIBE sites;


