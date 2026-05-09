-- HandToGlobal Database Schema
-- Database: handtoglobal
-- Port: 3307
-- Complete schema for all tables required by the PHP application

-- Use the database
USE `handtoglobal`;

-- Set default character set
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (for clean setup)
DROP TABLE IF EXISTS `completed_tasks`;
DROP TABLE IF EXISTS `withdrawals`;
DROP TABLE IF EXISTS `deposits`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `invitation_codes`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `settings`;

-- Create tables

-- 1. Admins table
CREATE TABLE `admins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users table
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `fullname` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `balance` decimal(10,2) DEFAULT 0.00,
    `level` varchar(50) DEFAULT 'Bronze',
    `bronze_unlocked` tinyint(1) DEFAULT 1,
    `silver_unlocked` tinyint(1) DEFAULT 0,
    `gold_unlocked` tinyint(1) DEFAULT 0,
    `platinum_unlocked` tinyint(1) DEFAULT 0,
    `is_blocked` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `accuracy` decimal(5,2) DEFAULT 0.00,
    `rating` decimal(5,2) DEFAULT 0.00,
    `total_tasks` int(11) DEFAULT 0,
    `referred_by` int(11) DEFAULT NULL,
    `invite_code_used` varchar(50) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `referred_by` (`referred_by`),
    FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tasks table
CREATE TABLE `tasks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `description` text,
    `level` varchar(50) NOT NULL,
    `reward` decimal(10,2) NOT NULL DEFAULT 0.00,
    `correct_answer` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Completed Tasks table
CREATE TABLE `completed_tasks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `task_id` int(11) NOT NULL,
    `completed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `reward` decimal(10,2) DEFAULT 0.00,
    `level` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `task_id` (`task_id`),
    KEY `completed_at` (`completed_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Deposits table
CREATE TABLE `deposits` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Withdrawals table
CREATE TABLE `withdrawals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `wallet_address` text NOT NULL,
    `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Notifications table
CREATE TABLE `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `is_read` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Settings table
CREATE TABLE `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `description` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Employees table
CREATE TABLE `employees` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `role` varchar(100) DEFAULT 'Employee',
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Invitation Codes table
CREATE TABLE `invitation_codes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `code` varchar(20) NOT NULL,
    `reward` decimal(10,2) DEFAULT 0.00,
    `uses_remaining` int(11) DEFAULT 1,
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `is_active` (`is_active`),
    FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default data

-- Default admin account (password: admin123)
INSERT INTO `admins` (`email`, `password`) VALUES 
('admin@handtoglobal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('appearance_mode', 'light', 'Light or dark theme'),
('theme_primary', '#4f46e5', 'Primary theme color'),
('theme_secondary', '#7c3aed', 'Secondary theme color'),
('theme_sidebar', '#101828', 'Sidebar color'),
('theme_background', '#f5f7fb', 'Background color'),
('theme_surface', '#ffffff', 'Surface color'),
('theme_text', '#101828', 'Text color'),
('theme_radius', '16px', 'Border radius'),
('theme_shadow', '0 10px 30px rgba(16,24,40,.08)', 'Shadow style'),
('site_name', 'HandToGlobal', 'Site name'),
('site_description', 'Earn USDT by completing tasks', 'Site description');

-- Sample tasks for each level
INSERT INTO `tasks` (`title`, `description`, `level`, `reward`, `correct_answer`) VALUES
-- Bronze tasks (1.8 USDT each)
('Social Media Follow', 'Follow our social media accounts', 'Bronze', 1.80, 'Yes'),
('Watch Video', 'Watch promotional video and answer question', 'Bronze', 1.80, 'Yes'),
('Survey Completion', 'Complete short survey about our platform', 'Bronze', 1.80, 'Yes'),
('App Download', 'Download and install our mobile app', 'Bronze', 1.80, 'Yes'),
('Review Platform', 'Write a review about our platform', 'Bronze', 1.80, 'Yes'),

-- Silver tasks (2.5 USDT each)
('Advanced Survey', 'Complete detailed market research survey', 'Silver', 2.50, 'Yes'),
('Content Creation', 'Create promotional content for social media', 'Silver', 2.50, 'Yes'),
('Referral Program', 'Refer 3 new users to platform', 'Silver', 2.50, 'Yes'),
('Product Testing', 'Test new features and provide feedback', 'Silver', 2.50, 'Yes'),
('Blog Writing', 'Write blog post about platform benefits', 'Silver', 2.50, 'Yes'),

-- Gold tasks (3.5 USDT each)
('Market Analysis', 'Analyze market trends and submit report', 'Gold', 3.50, 'Yes'),
('Partnership Outreach', 'Contact potential business partners', 'Gold', 3.50, 'Yes'),
('Content Strategy', 'Develop content strategy for platform', 'Gold', 3.50, 'Yes'),
('User Research', 'Conduct user interviews and research', 'Gold', 3.50, 'Yes'),
('Quality Assurance', 'Test platform functionality thoroughly', 'Gold', 3.50, 'Yes'),

-- Platinum tasks (5.0 USDT each)
('Business Development', 'Develop business expansion plans', 'Platinum', 5.00, 'Yes'),
('Strategic Planning', 'Create strategic roadmap for platform', 'Platinum', 5.00, 'Yes'),
('Investment Analysis', 'Analyze investment opportunities', 'Platinum', 5.00, 'Yes'),
('Global Expansion', 'Research international market opportunities', 'Platinum', 5.00, 'Yes'),
('Innovation Projects', 'Lead innovation initiatives', 'Platinum', 5.00, 'Yes'),

-- VIP1 tasks (100 USDT each)
('VIP Partnership', 'Establish VIP partnership deal', 'VIP1', 100.00, 'Yes'),
('Premium Consulting', 'Provide premium consulting services', 'VIP1', 100.00, 'Yes'),

-- VIP2 tasks (200 USDT each)
('Elite Consulting', 'Provide elite-level consulting', 'VIP2', 200.00, 'Yes'),
('Strategic Advisory', 'Serve as strategic advisor', 'VIP2', 200.00, 'Yes'),

-- VIP3 tasks (350 USDT each)
('Executive Leadership', 'Lead executive projects', 'VIP3', 350.00, 'Yes'),
('Board Advisory', 'Serve on advisory board', 'VIP3', 350.00, 'Yes');

-- Default employees
INSERT INTO `employees` (`name`, `email`, `role`, `status`) VALUES
('John Doe', 'john@handtoglobal.com', 'Manager', 'active'),
('Jane Smith', 'jane@handtoglobal.com', 'Employee', 'active');

-- Default invitation codes
INSERT INTO `invitation_codes` (`code`, `reward`, `uses_remaining`) VALUES
('WELCOME2024', 5.00, 10),
('START2024', 10.00, 5),
('BONUS2024', 15.00, 3);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create indexes for better performance
CREATE INDEX idx_users_balance ON users(balance);
CREATE INDEX idx_users_level ON users(level);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_completed_tasks_user_date ON completed_tasks(user_id, completed_at);
CREATE INDEX idx_deposits_user_status ON deposits(user_id, status);
CREATE INDEX idx_withdrawals_user_status ON withdrawals(user_id, status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

-- Database setup complete
SELECT 'HandToGlobal database setup completed successfully!' as message;
