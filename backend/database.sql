-- ==========================================================
-- Book Banko Database Schema & Initial Data
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB 10.3+
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `book_banko_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `book_banko_db`;

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
  `role` VARCHAR(50) NOT NULL DEFAULT 'super_admin',
  `permissions` LONGTEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Super Admin (Username: admin, Password: admin123)
-- Hash generated via password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`) 
VALUES (1, 'admin', 'admin@bookbanko.com', '$2y$10$tZ92uW7y0gN4e1zZ8b8Wye0zXn.zKzMvFpW4rC9s9cE8X1sI7pEfa', 'Master Administrator', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- --------------------------------------------------------
-- Table: boards (Educational Boards like GSEB, CBSE, NCERT, ICSE)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `boards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(100) DEFAULT 'school',
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `boards` (`id`, `code`, `name`, `description`, `icon`, `display_order`, `status`) VALUES
(1, 'GSEB', 'Gujarat Secondary and Higher Secondary Education Board', 'State board curriculum for Gujarat State', 'school', 1, 'active'),
(2, 'CBSE', 'Central Board of Secondary Education', 'National level education board in India', 'menu_book', 2, 'active'),
(3, 'NCERT', 'National Council of Educational Research and Training', 'Standard central curriculum books', 'auto_stories', 3, 'active'),
(4, 'ICSE', 'Indian Certificate of Secondary Education', 'CISCE affiliated national curriculum', 'apartment', 4, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: standards (Standards / Classes 6 to 12)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `standards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `standard_number` INT NOT NULL UNIQUE,
  `name` VARCHAR(50) NOT NULL,
  `requires_stream` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `standards` (`id`, `standard_number`, `name`, `requires_stream`, `display_order`, `status`) VALUES
(1, 6, 'Standard 6', 0, 1, 'active'),
(2, 7, 'Standard 7', 0, 2, 'active'),
(3, 8, 'Standard 8', 0, 3, 'active'),
(4, 9, 'Standard 9', 0, 4, 'active'),
(5, 10, 'Standard 10', 0, 5, 'active'),
(6, 11, 'Standard 11', 1, 6, 'active'),
(7, 12, 'Standard 12', 1, 7, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: streams (For Higher Secondary 11 & 12: Science, Commerce, Arts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `streams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `icon` VARCHAR(100) DEFAULT 'science',
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `streams` (`id`, `slug`, `name`, `description`, `icon`, `display_order`, `status`) VALUES
(1, 'science', 'Science', 'Medical, Engineering & Technical Subjects', 'biotech', 1, 'active'),
(2, 'commerce', 'Commerce', 'Accountancy, Finance & Business Studies', 'account_balance', 2, 'active'),
(3, 'arts', 'Arts / Humanities', 'Literature, History, Geography & Social Sciences', 'palette', 3, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: learning_categories (Home Screen 2x2 Grid)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'bookmark',
  `color_hex` VARCHAR(10) DEFAULT '#0061A4',
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `learning_categories` (`id`, `slug`, `name`, `icon`, `color_hex`, `display_order`, `status`) VALUES
(1, 'extra_material', 'Extra Material', 'bookmark_rounded', '#0061A4', 1, 'active'),
(2, 'study_material', 'Study Material', 'menu_book_rounded', '#2196F3', 2, 'active'),
(3, 'competitive_exams', 'Competitive Exams', 'emoji_events_rounded', '#F59E0B', 3, 'active'),
(4, 'higher_education', 'Higher Education', 'account_balance_rounded', '#10B981', 4, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: dashboard_modules (Standard Dashboard Modules: Textbooks, PYQs, etc.)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dashboard_modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `route_key` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'menu_book',
  `badge_text` VARCHAR(50) NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dashboard_modules` (`id`, `title`, `slug`, `route_key`, `icon`, `badge_text`, `display_order`, `status`) VALUES
(1, 'Textbooks', 'textbooks', '/textbooks', 'menu_book_rounded', 'Essential', 1, 'active'),
(2, 'Old PYQs', 'pyqs', '/pyqs', 'history_edu_rounded', 'Past Papers', 2, 'active'),
(3, 'Paper Sets', 'paper_sets', '/paper_sets', 'assignment_rounded', 'Practice', 3, 'active'),
(4, 'Blueprint', 'blueprint', '/blueprint', 'architecture_rounded', 'Official', 4, 'active'),
(5, 'M.IMP', 'mimp', '/mimp', 'star_rounded', 'Most Important', 5, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table: subjects
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `board_id` INT NOT NULL,
  `standard_id` INT NOT NULL,
  `stream_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'menu_book',
  `color_hex` VARCHAR(10) DEFAULT '#0061A4',
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`standard_id`) REFERENCES `standards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Standard 9 Subjects (GSEB)
INSERT INTO `subjects` (`id`, `board_id`, `standard_id`, `stream_id`, `name`, `code`, `icon`, `color_hex`, `display_order`, `status`) VALUES
(1, 1, 4, NULL, 'Gujarati', 'guj', 'translate_rounded', '#4F46E5', 1, 'active'),
(2, 1, 4, NULL, 'Mathematics', 'math', 'calculate_rounded', '#0284C7', 2, 'active'),
(3, 1, 4, NULL, 'Science & Technology', 'sci', 'science_rounded', '#059669', 3, 'active'),
(4, 1, 4, NULL, 'Social Science', 'ss', 'public_rounded', '#D97706', 4, 'active'),
(5, 1, 4, NULL, 'English', 'eng', 'language_rounded', '#7C3AED', 5, 'active'),
(6, 1, 4, NULL, 'Hindi', 'hin', 'menu_book_rounded', '#DC2626', 6, 'active'),
(7, 1, 4, NULL, 'Sanskrit', 'sans', 'auto_stories_rounded', '#0891B2', 7, 'active'),
(8, 1, 4, NULL, 'Computer Studies', 'cs', 'computer_rounded', '#2563EB', 8, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Standard 10 Subjects (GSEB)
INSERT INTO `subjects` (`id`, `board_id`, `standard_id`, `stream_id`, `name`, `code`, `icon`, `color_hex`, `display_order`, `status`) VALUES
(9, 1, 5, NULL, 'Mathematics (Standard)', 'math_std', 'calculate_rounded', '#0284C7', 1, 'active'),
(10, 1, 5, NULL, 'Mathematics (Basic)', 'math_basic', 'calculate_rounded', '#0369A1', 2, 'active'),
(11, 1, 5, NULL, 'Science', 'sci_10', 'science_rounded', '#059669', 3, 'active'),
(12, 1, 5, NULL, 'Social Science', 'ss_10', 'public_rounded', '#D97706', 4, 'active'),
(13, 1, 5, NULL, 'English First Language', 'eng_fl', 'language_rounded', '#7C3AED', 5, 'active'),
(14, 1, 5, NULL, 'Gujarati First Language', 'guj_fl', 'translate_rounded', '#4F46E5', 6, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Standard 11 Science Subjects (GSEB)
INSERT INTO `subjects` (`id`, `board_id`, `standard_id`, `stream_id`, `name`, `code`, `icon`, `color_hex`, `display_order`, `status`) VALUES
(15, 1, 6, 1, 'Physics', 'phy_11', 'bolt_rounded', '#0284C7', 1, 'active'),
(16, 1, 6, 1, 'Chemistry', 'chem_11', 'science_rounded', '#059669', 2, 'active'),
(17, 1, 6, 1, 'Mathematics', 'math_11', 'calculate_rounded', '#2563EB', 3, 'active'),
(18, 1, 6, 1, 'Biology', 'bio_11', 'biotech_rounded', '#16A34A', 4, 'active'),
(19, 1, 6, 1, 'English', 'eng_11', 'language_rounded', '#7C3AED', 5, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Table: chapters_content (Chapters, PDFs & Material)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chapters_content` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `module_id` INT NOT NULL DEFAULT 1,
  `chapter_number` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `pdf_file_path` VARCHAR(255) NULL,
  `pdf_external_url` VARCHAR(500) NULL,
  `page_count` INT NOT NULL DEFAULT 1,
  `file_size_mb` DECIMAL(6,2) DEFAULT 0.00,
  `is_free` TINYINT(1) NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`module_id`) REFERENCES `dashboard_modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Standard 9 Mathematics Chapters (Subject ID: 2, Module ID: 1 Textbooks)
INSERT INTO `chapters_content` (`id`, `subject_id`, `module_id`, `chapter_number`, `title`, `description`, `pdf_file_path`, `pdf_external_url`, `page_count`, `file_size_mb`, `display_order`, `status`) VALUES
(1, 2, 1, 1, 'Number Systems', 'Introduction to rational and irrational numbers, real numbers and their decimal expansions.', 'ch1_number_systems.pdf', '', 42, 3.45, 1, 'active'),
(2, 2, 1, 2, 'Polynomials', 'Definition of a polynomial, zeros of a polynomial, remainder theorem and factorization.', 'ch2_polynomials.pdf', '', 38, 2.80, 2, 'active'),
(3, 2, 1, 3, 'Coordinate Geometry', 'Cartesian plane, coordinates of a point, plotting points in the plane.', 'ch3_coordinate_geometry.pdf', '', 26, 1.95, 3, 'active'),
(4, 2, 1, 4, 'Linear Equations in Two Variables', 'Standard form of linear equations, graph of linear equation in two variables.', 'ch4_linear_equations.pdf', '', 30, 2.20, 4, 'active'),
(5, 2, 1, 5, 'Introduction to Euclid\'s Geometry', 'Euclid\'s definitions, axioms and postulates with practical explanations.', 'ch5_euclids_geometry.pdf', '', 22, 1.60, 5, 'active'),
(6, 2, 1, 6, 'Lines and Angles', 'Basic terms and definitions, intersecting lines, pairs of angles and parallel lines.', 'ch6_lines_and_angles.pdf', '', 35, 2.90, 6, 'active'),
(7, 2, 1, 7, 'Triangles', 'Congruence of triangles, criteria for congruence (SAS, ASA, SSS, RHS).', 'ch7_triangles.pdf', '', 40, 3.10, 7, 'active'),
(8, 2, 1, 8, 'Quadrilaterals', 'Angle sum property of a quadrilateral, types of quadrilaterals and their properties.', 'ch8_quadrilaterals.pdf', '', 32, 2.50, 8, 'active'),
(9, 2, 1, 9, 'Circles', 'Circle and its related terms, chord and subtended angles, cyclic quadrilaterals.', 'ch9_circles.pdf', '', 28, 2.10, 9, 'active'),
(10, 2, 1, 10, 'Heron\'s Formula', 'Area of a triangle using Heron\'s formula and practical applications.', 'ch10_herons_formula.pdf', '', 18, 1.40, 10, 'active'),
(11, 2, 1, 11, 'Surface Areas and Volumes', 'Surface area and volume of cuboids, cylinders, cones and spheres.', 'ch11_surface_areas.pdf', '', 45, 3.80, 11, 'active'),
(12, 2, 1, 12, 'Statistics', 'Collection and presentation of data, graphical representation, bar graphs and histograms.', 'ch12_statistics.pdf', '', 36, 2.75, 12, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table: banners (Promotional and Announcement Banners)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `sub_title` VARCHAR(255) NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `action_type` ENUM('none', 'open_url', 'open_subject', 'open_standard') NOT NULL DEFAULT 'none',
  `action_value` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `banners` (`id`, `title`, `sub_title`, `image_path`, `action_type`, `action_value`, `display_order`, `status`) VALUES
(1, 'GSEB 2026 Board Exam Blueprints Released!', 'Download official subject-wise blueprints & question formats', 'banner_blueprint.png', 'none', '', 1, 'active'),
(2, 'Score 90%+ with Most IMP Question Sets', 'Specially curated revision material for Standards 9 & 10', 'banner_imp.png', 'none', '', 2, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table: app_settings (Global App Configurations & Notice)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'Book Banko', 'Application Title'),
('app_version', '1.0.0', 'Latest App Build Version'),
('force_update', '0', 'Set to 1 to force app update'),
('maintenance_mode', '0', 'Set to 1 to enable app maintenance mode'),
('maintenance_message', 'We are updating our content library. Please check back shortly.', 'Maintenance notice display'),
('contact_email', 'support@bookbanko.com', 'Support Helpdesk Email'),
('privacy_policy_url', 'https://bookbanko.com/privacy', 'Privacy Policy URL'),
('terms_conditions_url', 'https://bookbanko.com/terms', 'Terms & Conditions URL'),
('notice_title', 'Welcome to Book Banko!', 'Home announcement title'),
('notice_message', 'Access free textbook PDFs, PYQs, and study guides for all Gujarat & CBSE standards.', 'Home announcement body')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- --------------------------------------------------------
-- Table: extra_materials (Extra Study Material, Formula Sheets, Notes)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `extra_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NULL,
  `category_slug` VARCHAR(50) NOT NULL DEFAULT 'extra_material',
  `board_id` INT NULL,
  `medium_id` INT NULL,
  `standard_id` INT NULL,
  `subject_id` INT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `pdf_file_path` VARCHAR(255) NULL,
  `pdf_external_url` VARCHAR(500) NULL,
  `thumbnail` VARCHAR(255) NULL,
  `page_count` INT NOT NULL DEFAULT 1,
  `file_size_mb` DECIMAL(6,2) DEFAULT 0.00,
  `is_free` TINYINT(1) NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `learning_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`medium_id`) REFERENCES `mediums`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`standard_id`) REFERENCES `standards`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: study_products (Study Material & Stationery Products, Affiliate Store)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `affiliate_link` VARCHAR(1000) NOT NULL,
  `search_tags` VARCHAR(500) NULL,
  `image_path` VARCHAR(255) NULL,
  `image_external_url` VARCHAR(1000) NULL,
  `display_order` INT NOT NULL DEFAULT 99,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `is_fallback_ad` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: competitive_exams (Competitive Exams like JEE, NEET, GATE, GUJCET)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitive_exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `exam_code` VARCHAR(50) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'Engineering / Medical',
  `description` TEXT NULL,
  `icon` VARCHAR(100) DEFAULT 'emoji_events',
  `color_hex` VARCHAR(10) DEFAULT '#0061A4',
  `banner_image` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `competitive_exams` (`id`, `title`, `slug`, `exam_code`, `category`, `description`, `icon`, `color_hex`, `display_order`, `status`) VALUES
(1, 'JEE (Main & Advanced)', 'jee', 'JEE', 'Engineering Entrance', 'Joint Entrance Examination for IITs, NITs & Top Engineering Colleges. Solved PYQs, Formula Sheets & Mock Papers.', 'engineering_rounded', '#0061A4', 1, 'active'),
(2, 'NEET (UG)', 'neet', 'NEET', 'Medical Entrance', 'National Eligibility cum Entrance Test for MBBS & BDS. Complete Biology, Physics & Chemistry Solved Papers.', 'biotech_rounded', '#059669', 2, 'active'),
(3, 'GATE', 'gate', 'GATE', 'Postgraduate & PSU', 'Graduate Aptitude Test in Engineering for M.Tech & PSU Recruitment. Engineering Math, Aptitude & Technical Papers.', 'account_balance_rounded', '#7C3AED', 3, 'active'),
(4, 'GUJCET', 'gujcet', 'GUJCET', 'Gujarat State Entrance', 'Gujarat Common Entrance Test for Degree Engineering and Pharmacy Admissions in Gujarat.', 'school_rounded', '#D97706', 4, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table: competitive_exam_materials (PDFs, Papers & Notes for Competitive Exams)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitive_exam_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `subject_name` VARCHAR(100) NULL,
  `material_type` VARCHAR(100) DEFAULT 'Question Paper',
  `year` VARCHAR(20) NULL,
  `description` TEXT NULL,
  `pdf_file_path` VARCHAR(255) NULL,
  `pdf_external_url` VARCHAR(500) NULL,
  `thumbnail` VARCHAR(255) NULL,
  `page_count` INT NOT NULL DEFAULT 1,
  `file_size_mb` DECIMAL(6,2) DEFAULT 0.00,
  `is_free` TINYINT(1) NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `competitive_exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `competitive_exam_materials` (`id`, `exam_id`, `title`, `subject_name`, `material_type`, `year`, `description`, `pdf_file_path`, `pdf_external_url`, `page_count`, `file_size_mb`, `display_order`, `status`) VALUES
(1, 1, 'JEE Main 2024 Physics Question Paper with Complete Solutions', 'Physics', 'Question Paper', '2024', 'Official JEE Main Shift 1 & 2 solved physics paper with step-by-step detailed explanations.', 'bb_6ab901c015ba22.92430469.pdf', '', 38, 2.45, 1, 'active'),
(2, 1, 'JEE Mathematics Master Formula Book & Quick Tricks', 'Mathematics', 'Formula Book', '2025', 'Complete compilation of Calculus, Algebra, Coordinate Geometry and Vectors formulas with short tricks.', 'bb_6ab901c015ba22.92430469.pdf', '', 52, 3.10, 2, 'active'),
(3, 1, 'JEE Chemistry Organic Reactions & Mechanism Revision Notes', 'Chemistry', 'Notes', '2025', 'High-yield named reactions, reagents, conversions and mechanisms for JEE Main & Advanced.', 'bb_6ab901c015ba22.92430469.pdf', '', 45, 2.80, 3, 'active'),
(4, 2, 'NEET UG 2024 Biology 10-Year Solved Chapterwise PYQs', 'Biology', 'Question Paper', '2024', 'NCERT line-by-line past 10 years NEET questions with verified answer keys.', 'bb_6ab901c015ba22.92430469.pdf', '', 64, 4.20, 1, 'active'),
(5, 2, 'NEET Physics Most Scoring Topics Formula Handbook', 'Physics', 'Formula Book', '2025', 'Formula handbook covering Mechanics, Electrodynamics, Optics and Modern Physics.', 'bb_6ab901c015ba22.92430469.pdf', '', 36, 2.15, 2, 'active'),
(6, 3, 'GATE General Aptitude & Engineering Mathematics Solved Papers', 'General Aptitude', 'Question Paper', '2024', 'Comprehensive solved papers for common 30 marks section covering Quantitative Aptitude and Engineering Math.', 'bb_6ab901c015ba22.92430469.pdf', '', 48, 3.50, 1, 'active'),
(7, 3, 'GATE Core Subject Quick Revision Notes & Concepts', 'Technical', 'Notes', '2025', 'Handwritten summary notes and high priority formulas for GATE technical sections.', 'bb_6ab901c015ba22.92430469.pdf', '', 55, 3.90, 2, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table: ai_course_materials (Artificial Intelligence & Coding Materials for Standards 6-12)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_course_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `standard_id` INT NULL,
  `standard_number` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `module_name` VARCHAR(150) NULL,
  `material_type` VARCHAR(100) DEFAULT 'Course Notes & PDF',
  `description` TEXT NULL,
  `pdf_file_path` VARCHAR(255) NULL,
  `pdf_external_url` VARCHAR(500) NULL,
  `video_url` VARCHAR(500) NULL,
  `thumbnail` VARCHAR(255) NULL,
  `page_count` INT NOT NULL DEFAULT 1,
  `file_size_mb` DECIMAL(6,2) DEFAULT 0.00,
  `is_free` TINYINT(1) NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 1,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`standard_id`) REFERENCES `standards`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_course_materials` (`id`, `standard_number`, `title`, `module_name`, `description`, `page_count`, `file_size_mb`, `display_order`, `status`) VALUES
(1, 6, 'Introduction to Artificial Intelligence & Smart World', 'Module 1 • What is AI?', 'Discover how machines learn, everyday examples of AI like voice assistants, recommendation systems, and computer vision.', 16, 2.10, 1, 'active'),
(2, 7, 'Computational Thinking & AI Problem Solving', 'Module 1 • Algorithms & Logic', 'Understanding algorithms, block coding, decision trees, and designing simple conversational agents.', 20, 2.45, 1, 'active'),
(3, 8, 'Data, Sensors & Computer Perception in AI', 'Module 1 • Data & Sensors', 'How machines collect and clean data, image recognition fundamentals, and ethics in automated systems.', 24, 2.80, 1, 'active'),
(4, 9, 'Python for Artificial Intelligence & Data Science', 'Module 1 • Python Coding Essentials', 'Hands-on Python syntax, variables, lists, dictionaries, functions, and working with data for machine learning.', 32, 3.20, 1, 'active'),
(5, 9, 'Computer Vision & Natural Language Processing (NLP)', 'Module 2 • Core AI Domains', 'Techniques for feature extraction, optical character recognition, text sentiment analysis, and speech processing.', 28, 2.90, 2, 'active'),
(6, 10, 'Machine Learning Foundations & Supervised Learning', 'Module 1 • Machine Learning', 'Supervised learning, classification vs regression, decision tree modeling, model training and evaluation metrics.', 36, 3.60, 1, 'active'),
(7, 10, 'Board Exam AI Revision Guide & Ethics Project Cycle', 'Module 2 • Board Curriculum & Ethics', 'Complete curriculum revision notes, AI project cycle (Problem Scoping, Data Acquisition, Exploration, Modeling, Evaluation), and AI ethics.', 44, 4.10, 2, 'active'),
(8, 11, 'Applied Neural Networks & Deep Learning Architectures', 'Module 1 • Deep Learning', 'Artificial Neural Networks (ANN), activation functions, perceptrons, backpropagation, and training deep models.', 48, 4.50, 1, 'active'),
(9, 12, 'Generative AI, Large Language Models & AI Capstone', 'Module 1 • Generative AI & LLMs', 'Transformers, Prompt Engineering, Large Language Models (LLMs), diffusion models, and developing end-to-end AI applications.', 52, 5.20, 1, 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Table structure for registered student app users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `school_name` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `acknowledged` TINYINT(1) DEFAULT 1,
  `board` VARCHAR(50) DEFAULT NULL,
  `medium` VARCHAR(50) DEFAULT NULL,
  `standard` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



