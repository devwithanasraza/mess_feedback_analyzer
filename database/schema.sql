-- ==========================================================
-- Mess Menu Feedback Analyzer
-- Database Schema for B.Tech Data Science 1st-Year Project
-- Database: mess_feedback_analyzer
-- ==========================================================

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Menus Table (Per Date and Meal Type)
CREATE TABLE IF NOT EXISTS `menus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_date` DATE NOT NULL,
    `meal_type` ENUM('Breakfast', 'Lunch', 'Snacks', 'Dinner') NOT NULL,
    `serving_time` VARCHAR(50) NOT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_date_meal` (`menu_date`, `meal_type`),
    INDEX `idx_menu_date` (`menu_date`),
    INDEX `idx_meal_type` (`meal_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Menu Items Table
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_id` INT NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `is_veg` TINYINT(1) DEFAULT 1,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`menu_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE,
    INDEX `idx_item_name` (`item_name`),
    INDEX `idx_menu_id` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Feedback Table (Anonymous Overall Feedback)
CREATE TABLE IF NOT EXISTS `feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_id` INT NOT NULL,
    `overall_rating` TINYINT NOT NULL CHECK (`overall_rating` BETWEEN 1 AND 5),
    `hygiene_rating` TINYINT NOT NULL CHECK (`hygiene_rating` BETWEEN 1 AND 5),
    `temperature` ENUM('Too Cold', 'Acceptable', 'Hot/Fresh') NOT NULL,
    `portion` ENUM('Too Little', 'Appropriate', 'Too Much') NOT NULL,
    `wastage_level` ENUM('None', 'Very Little', 'Some', 'A lot') NOT NULL,
    `wastage_reason` VARCHAR(100) NULL,
    `comment` TEXT NULL,
    `suggestion` TEXT NULL,
    `sentiment` ENUM('Positive', 'Neutral', 'Negative') DEFAULT 'Neutral',
    `sentiment_score` DECIMAL(4,2) DEFAULT 0.00,
    `session_hash` VARCHAR(64) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`menu_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE,
    INDEX `idx_feedback_created` (`created_at`),
    INDEX `idx_sentiment` (`sentiment`),
    INDEX `idx_wastage_level` (`wastage_level`),
    INDEX `idx_overall_rating` (`overall_rating`),
    INDEX `idx_fb_menu` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Feedback Items Table (Dish-Level Ratings)
CREATE TABLE IF NOT EXISTS `feedback_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feedback_id` INT NOT NULL,
    `menu_item_id` INT NOT NULL,
    `taste_rating` TINYINT NOT NULL CHECK (`taste_rating` BETWEEN 1 AND 5),
    `quality_rating` TINYINT NOT NULL CHECK (`quality_rating` BETWEEN 1 AND 5),
    `quantity_rating` TINYINT NOT NULL CHECK (`quantity_rating` BETWEEN 1 AND 5),
    `freshness_rating` TINYINT NOT NULL CHECK (`freshness_rating` BETWEEN 1 AND 5),
    `repeat_preference` ENUM('Yes', 'Maybe', 'No') DEFAULT 'Yes',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`feedback_id`) REFERENCES `feedback`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_fb_item` (`menu_item_id`),
    INDEX `idx_fb_id` (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Wastage Records Table
CREATE TABLE IF NOT EXISTS `wastage_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feedback_id` INT NOT NULL,
    `menu_item_id` INT NULL,
    `wastage_level` ENUM('None', 'Very Little', 'Some', 'A lot') NOT NULL,
    `wastage_reason` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`feedback_id`) REFERENCES `feedback`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE SET NULL,
    INDEX `idx_wastage_item` (`menu_item_id`),
    INDEX `idx_wastage_reason` (`wastage_reason`),
    INDEX `idx_wastage_level_col` (`wastage_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Feedback Sentiment Table (Keyword Analysis Record)
CREATE TABLE IF NOT EXISTS `feedback_sentiment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feedback_id` INT NOT NULL,
    `sentiment` ENUM('Positive', 'Neutral', 'Negative') NOT NULL,
    `score` DECIMAL(4,2) NOT NULL,
    `matched_keywords` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`feedback_id`) REFERENCES `feedback`(`id`) ON DELETE CASCADE,
    INDEX `idx_fb_sentiment` (`sentiment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Administrator Account:
-- Username: admin
-- Password: admin123 (hashed via password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `email`, `role`)
VALUES ('admin', '$2y$10$wT8yZpBf3vVekgTsq.1dTu5FjYqP/hQ50R5M3N9ZkM1Ym6p8Yk1.C', 'Mess Administrator', 'admin@campus.edu', 'super_admin')
ON DUPLICATE KEY UPDATE `username` = `username`;
