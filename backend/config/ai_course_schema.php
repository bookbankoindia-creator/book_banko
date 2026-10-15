<?php
/**
 * AI Course Table Schema & Auto-Migration Helper
 * Book Banko Admin Panel & API Backend
 */

require_once __DIR__ . '/database.php';

function ensureAiCourseTableExists(PDO $db): void {
    static $checked = false;
    if ($checked) return;

    $driver = strtolower($db->getAttribute(PDO::ATTR_DRIVER_NAME));

    if ($driver === 'pgsql') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS ai_course_materials (
                id SERIAL PRIMARY KEY,
                standard_id INT NULL,
                standard_number INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                module_name VARCHAR(150) NULL,
                material_type VARCHAR(100) DEFAULT 'Course Notes & PDF',
                description TEXT NULL,
                pdf_file_path VARCHAR(255) NULL,
                pdf_external_url VARCHAR(500) NULL,
                video_url VARCHAR(500) NULL,
                thumbnail VARCHAR(255) NULL,
                page_count INT NOT NULL DEFAULT 1,
                file_size_mb DECIMAL(6,2) DEFAULT 0.00,
                is_free BOOLEAN NOT NULL DEFAULT TRUE,
                views_count INT NOT NULL DEFAULT 0,
                display_order INT NOT NULL DEFAULT 1,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            );
        ");
    } else {
        $db->exec("
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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // Check if table is empty; if so, seed rich default curriculum for Standards 6-12
    $countStmt = $db->query("SELECT COUNT(*) FROM ai_course_materials");
    $count = (int)$countStmt->fetchColumn();

    if ($count === 0) {
        $sampleItems = [
            [
                'standard_number' => 6,
                'title' => 'Introduction to Artificial Intelligence & Smart World',
                'module_name' => 'Module 1 • What is AI?',
                'description' => 'Discover how machines learn, everyday examples of AI like voice assistants, recommendation systems, and computer vision.',
                'page_count' => 16,
                'file_size_mb' => 2.10,
                'display_order' => 1,
            ],
            [
                'standard_number' => 6,
                'title' => 'Human vs Machine Intelligence & Fun Logic Games',
                'module_name' => 'Module 2 • Smart Machines',
                'description' => 'Comparing human brain vs computer processing, pattern recognition, and decision puzzles.',
                'page_count' => 18,
                'file_size_mb' => 2.30,
                'display_order' => 2,
            ],
            [
                'standard_number' => 7,
                'title' => 'Computational Thinking & AI Problem Solving',
                'module_name' => 'Module 1 • Algorithms & Logic',
                'description' => 'Understanding algorithms, block coding, decision trees, and designing simple conversational agents.',
                'page_count' => 20,
                'file_size_mb' => 2.45,
                'display_order' => 1,
            ],
            [
                'standard_number' => 8,
                'title' => 'Data, Sensors & Computer Perception in AI',
                'module_name' => 'Module 1 • Data & Sensors',
                'description' => 'How machines collect and clean data, image recognition fundamentals, and ethics in automated systems.',
                'page_count' => 24,
                'file_size_mb' => 2.80,
                'display_order' => 1,
            ],
            [
                'standard_number' => 9,
                'title' => 'Python for Artificial Intelligence & Data Science',
                'module_name' => 'Module 1 • Python Coding Essentials',
                'description' => 'Hands-on Python syntax, variables, lists, dictionaries, functions, and working with data for machine learning.',
                'page_count' => 32,
                'file_size_mb' => 3.20,
                'display_order' => 1,
            ],
            [
                'standard_number' => 9,
                'title' => 'Computer Vision & Natural Language Processing (NLP)',
                'module_name' => 'Module 2 • Core AI Domains',
                'description' => 'Techniques for feature extraction, optical character recognition, text sentiment analysis, and speech processing.',
                'page_count' => 28,
                'file_size_mb' => 2.90,
                'display_order' => 2,
            ],
            [
                'standard_number' => 10,
                'title' => 'Machine Learning Foundations & Supervised Learning',
                'module_name' => 'Module 1 • Machine Learning',
                'description' => 'Supervised learning, classification vs regression, decision tree modeling, model training and evaluation metrics.',
                'page_count' => 36,
                'file_size_mb' => 3.60,
                'display_order' => 1,
            ],
            [
                'standard_number' => 10,
                'title' => 'Board Exam AI Revision Guide & Ethics Project Cycle',
                'module_name' => 'Module 2 • Board Curriculum & Ethics',
                'description' => 'Complete curriculum revision notes, AI project cycle (Problem Scoping, Data Acquisition, Exploration, Modeling, Evaluation), and AI ethics.',
                'page_count' => 44,
                'file_size_mb' => 4.10,
                'display_order' => 2,
            ],
            [
                'standard_number' => 11,
                'title' => 'Applied Neural Networks & Deep Learning Architectures',
                'module_name' => 'Module 1 • Deep Learning',
                'description' => 'Artificial Neural Networks (ANN), activation functions, perceptrons, backpropagation, and training deep models.',
                'page_count' => 48,
                'file_size_mb' => 4.50,
                'display_order' => 1,
            ],
            [
                'standard_number' => 12,
                'title' => 'Generative AI, Large Language Models & AI Capstone',
                'module_name' => 'Module 1 • Generative AI & LLMs',
                'description' => 'Transformers, Prompt Engineering, Large Language Models (LLMs), diffusion models, and developing end-to-end AI applications.',
                'page_count' => 52,
                'file_size_mb' => 5.20,
                'display_order' => 1,
            ],
        ];

        $insertSql = "
            INSERT INTO ai_course_materials 
            (standard_number, title, module_name, description, page_count, file_size_mb, display_order, status)
            VALUES 
            (:std, :title, :mod, :desc, :pages, :size, :d_order, 'active')
        ";
        $insertStmt = $db->prepare($insertSql);

        foreach ($sampleItems as $item) {
            $insertStmt->execute([
                ':std' => $item['standard_number'],
                ':title' => $item['title'],
                ':mod' => $item['module_name'],
                ':desc' => $item['description'],
                ':pages' => $item['page_count'],
                ':size' => $item['file_size_mb'],
                ':d_order' => $item['display_order'],
            ]);
        }
    }

    $checked = true;
}
