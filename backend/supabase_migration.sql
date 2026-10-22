-- ==============================================================================
-- Book Banko: Full Supabase PostgreSQL Migration Script
-- Generated for PostgreSQL 14+ / 15+ / 16+ (Supabase Native)
-- Includes: DDL Schema, Sequence Fixes, Constraints, Indexes, RLS Policies, 
--           Storage Buckets & Policies, and Complete Initial Datasets.
-- ==============================================================================

-- 1. EXTENSIONS
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 2. TRIGGER FUNCTION FOR UPDATED_AT
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ------------------------------------------------------------------------------
-- TABLE: admins
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    role VARCHAR(20) NOT NULL DEFAULT 'super_admin' CHECK (role IN ('super_admin', 'editor', 'viewer')),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    last_login TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_admins_updated_at ON admins;
DROP TRIGGER IF EXISTS trg_admins_updated_at ON admins;
CREATE TRIGGER trg_admins_updated_at BEFORE UPDATE ON admins
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: boards
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS boards (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(100) DEFAULT 'school',
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_boards_updated_at ON boards;
DROP TRIGGER IF EXISTS trg_boards_updated_at ON boards;
CREATE TRIGGER trg_boards_updated_at BEFORE UPDATE ON boards
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: mediums
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mediums (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    icon VARCHAR(100) DEFAULT 'translate',
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_mediums_updated_at ON mediums;
DROP TRIGGER IF EXISTS trg_mediums_updated_at ON mediums;
CREATE TRIGGER trg_mediums_updated_at BEFORE UPDATE ON mediums
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: standards
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS standards (
    id SERIAL PRIMARY KEY,
    board_id INT NULL REFERENCES boards(id) ON DELETE SET NULL,
    medium_id INT NULL REFERENCES mediums(id) ON DELETE SET NULL,
    standard_number INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    requires_stream BOOLEAN NOT NULL DEFAULT FALSE,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_standards_board_medium ON standards(board_id, medium_id, standard_number);

DROP TRIGGER IF EXISTS trg_standards_updated_at ON standards;
DROP TRIGGER IF EXISTS trg_standards_updated_at ON standards;
CREATE TRIGGER trg_standards_updated_at BEFORE UPDATE ON standards
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: streams
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS streams (
    id SERIAL PRIMARY KEY,
    board_id INT NULL REFERENCES boards(id) ON DELETE SET NULL,
    medium_id INT NULL REFERENCES mediums(id) ON DELETE SET NULL,
    standard_id INT NULL REFERENCES standards(id) ON DELETE SET NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    icon VARCHAR(100) DEFAULT 'science',
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_streams_updated_at ON streams;
DROP TRIGGER IF EXISTS trg_streams_updated_at ON streams;
CREATE TRIGGER trg_streams_updated_at BEFORE UPDATE ON streams
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: learning_categories
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS learning_categories (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) DEFAULT 'bookmark',
    color_hex VARCHAR(10) DEFAULT '#0061A4',
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_learning_categories_updated_at ON learning_categories;
DROP TRIGGER IF EXISTS trg_learning_categories_updated_at ON learning_categories;
CREATE TRIGGER trg_learning_categories_updated_at BEFORE UPDATE ON learning_categories
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: dashboard_modules
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dashboard_modules (
    id SERIAL PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    route_key VARCHAR(50) NOT NULL,
    icon VARCHAR(100) DEFAULT 'menu_book',
    badge_text VARCHAR(50) NULL,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_dashboard_modules_updated_at ON dashboard_modules;
DROP TRIGGER IF EXISTS trg_dashboard_modules_updated_at ON dashboard_modules;
CREATE TRIGGER trg_dashboard_modules_updated_at BEFORE UPDATE ON dashboard_modules
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: subjects
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id SERIAL PRIMARY KEY,
    board_id INT NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    medium_id INT NULL REFERENCES mediums(id) ON DELETE SET NULL,
    standard_id INT NOT NULL REFERENCES standards(id) ON DELETE CASCADE,
    stream_id INT NULL REFERENCES streams(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    icon VARCHAR(100) DEFAULT 'menu_book',
    color_hex VARCHAR(10) DEFAULT '#0061A4',
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_subjects_lookup ON subjects(board_id, medium_id, standard_id, stream_id, status);

DROP TRIGGER IF EXISTS trg_subjects_updated_at ON subjects;
DROP TRIGGER IF EXISTS trg_subjects_updated_at ON subjects;
CREATE TRIGGER trg_subjects_updated_at BEFORE UPDATE ON subjects
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: chapters_content
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chapters_content (
    id SERIAL PRIMARY KEY,
    subject_id INT NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    module_id INT NOT NULL DEFAULT 1 REFERENCES dashboard_modules(id) ON DELETE CASCADE,
    chapter_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    pdf_file_path VARCHAR(255) NULL,
    pdf_external_url VARCHAR(500) NULL,
    page_count INT NOT NULL DEFAULT 1,
    file_size_mb NUMERIC(6,2) DEFAULT 0.00,
    is_free BOOLEAN NOT NULL DEFAULT TRUE,
    views_count INT NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_chapters_subject_module ON chapters_content(subject_id, module_id, status);

DROP TRIGGER IF EXISTS trg_chapters_content_updated_at ON chapters_content;
DROP TRIGGER IF EXISTS trg_chapters_content_updated_at ON chapters_content;
CREATE TRIGGER trg_chapters_content_updated_at BEFORE UPDATE ON chapters_content
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: banners
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
    id SERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    sub_title VARCHAR(255) NULL,
    image_path VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL DEFAULT 'none' CHECK (action_type IN ('none', 'open_url', 'open_subject', 'open_standard')),
    action_value VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_banners_updated_at ON banners;
DROP TRIGGER IF EXISTS trg_banners_updated_at ON banners;
CREATE TRIGGER trg_banners_updated_at BEFORE UPDATE ON banners
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: app_settings
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_app_settings_updated_at ON app_settings;
DROP TRIGGER IF EXISTS trg_app_settings_updated_at ON app_settings;
CREATE TRIGGER trg_app_settings_updated_at BEFORE UPDATE ON app_settings
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: extra_materials
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extra_materials (
    id SERIAL PRIMARY KEY,
    category_id INT NULL REFERENCES learning_categories(id) ON DELETE SET NULL,
    category_slug VARCHAR(50) NOT NULL DEFAULT 'extra_material',
    board_id INT NULL REFERENCES boards(id) ON DELETE SET NULL,
    medium_id INT NULL REFERENCES mediums(id) ON DELETE SET NULL,
    standard_id INT NULL REFERENCES standards(id) ON DELETE SET NULL,
    subject_id INT NULL REFERENCES subjects(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    pdf_file_path VARCHAR(255) NULL,
    pdf_external_url VARCHAR(500) NULL,
    thumbnail VARCHAR(255) NULL,
    page_count INT NOT NULL DEFAULT 1,
    file_size_mb NUMERIC(6,2) DEFAULT 0.00,
    is_free BOOLEAN NOT NULL DEFAULT TRUE,
    views_count INT NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_extra_materials_filter ON extra_materials(category_slug, board_id, medium_id, standard_id, status);

DROP TRIGGER IF EXISTS trg_extra_materials_updated_at ON extra_materials;
DROP TRIGGER IF EXISTS trg_extra_materials_updated_at ON extra_materials;
CREATE TRIGGER trg_extra_materials_updated_at BEFORE UPDATE ON extra_materials
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: study_products
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS study_products (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    affiliate_link VARCHAR(1000) NOT NULL,
    search_tags VARCHAR(500) NULL,
    image_path VARCHAR(255) NULL,
    image_external_url VARCHAR(1000) NULL,
    display_order INT NOT NULL DEFAULT 99,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    is_fallback_ad BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_study_products_status ON study_products(status, display_order);

DROP TRIGGER IF EXISTS trg_study_products_updated_at ON study_products;
DROP TRIGGER IF EXISTS trg_study_products_updated_at ON study_products;
CREATE TRIGGER trg_study_products_updated_at BEFORE UPDATE ON study_products
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: competitive_exams
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competitive_exams (
    id SERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    exam_code VARCHAR(50) NOT NULL,
    category VARCHAR(100) DEFAULT 'Engineering / Medical',
    description TEXT NULL,
    icon VARCHAR(100) DEFAULT 'emoji_events',
    color_hex VARCHAR(10) DEFAULT '#0061A4',
    banner_image VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_competitive_exams_updated_at ON competitive_exams;
DROP TRIGGER IF EXISTS trg_competitive_exams_updated_at ON competitive_exams;
CREATE TRIGGER trg_competitive_exams_updated_at BEFORE UPDATE ON competitive_exams
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: competitive_exam_materials
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competitive_exam_materials (
    id SERIAL PRIMARY KEY,
    exam_id INT NOT NULL REFERENCES competitive_exams(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    subject_name VARCHAR(100) NULL,
    material_type VARCHAR(100) DEFAULT 'Question Paper',
    year VARCHAR(20) NULL,
    description TEXT NULL,
    pdf_file_path VARCHAR(255) NULL,
    pdf_external_url VARCHAR(500) NULL,
    thumbnail VARCHAR(255) NULL,
    page_count INT NOT NULL DEFAULT 1,
    file_size_mb NUMERIC(6,2) DEFAULT 0.00,
    is_free BOOLEAN NOT NULL DEFAULT TRUE,
    views_count INT NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_comp_materials_exam ON competitive_exam_materials(exam_id, status);

DROP TRIGGER IF EXISTS trg_competitive_exam_materials_updated_at ON competitive_exam_materials;
DROP TRIGGER IF EXISTS trg_competitive_exam_materials_updated_at ON competitive_exam_materials;
CREATE TRIGGER trg_competitive_exam_materials_updated_at BEFORE UPDATE ON competitive_exam_materials
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------------------------
-- TABLE: higher_education_materials
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS higher_education_materials (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    course_name VARCHAR(150) NULL,
    material_type VARCHAR(100) DEFAULT 'PDF Material',
    description TEXT NULL,
    pdf_file_path VARCHAR(255) NULL,
    pdf_external_url VARCHAR(500) NULL,
    thumbnail VARCHAR(255) NULL,
    page_count INT NOT NULL DEFAULT 1,
    file_size_mb NUMERIC(6,2) DEFAULT 0.00,
    is_free BOOLEAN NOT NULL DEFAULT TRUE,
    views_count INT NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_higher_ed_status ON higher_education_materials(course_name, status);

DROP TRIGGER IF EXISTS trg_higher_education_materials_updated_at ON higher_education_materials;
DROP TRIGGER IF EXISTS trg_higher_education_materials_updated_at ON higher_education_materials;
CREATE TRIGGER trg_higher_education_materials_updated_at BEFORE UPDATE ON higher_education_materials
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: app_users (Registered Students & Logins)
CREATE TABLE IF NOT EXISTS app_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    acknowledged SMALLINT DEFAULT 1,
    board VARCHAR(50) DEFAULT NULL,
    medium VARCHAR(50) DEFAULT NULL,
    standard INT DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_app_users_email ON app_users(email);

DROP TRIGGER IF EXISTS trg_app_users_updated_at ON app_users;
CREATE TRIGGER trg_app_users_updated_at BEFORE UPDATE ON app_users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ==============================================================================
-- 3. ROW LEVEL SECURITY (RLS) POLICIES
-- ==============================================================================

-- Enable RLS on all tables
ALTER TABLE admins ENABLE ROW LEVEL SECURITY;
ALTER TABLE boards ENABLE ROW LEVEL SECURITY;
ALTER TABLE mediums ENABLE ROW LEVEL SECURITY;
ALTER TABLE standards ENABLE ROW LEVEL SECURITY;
ALTER TABLE streams ENABLE ROW LEVEL SECURITY;
ALTER TABLE learning_categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE dashboard_modules ENABLE ROW LEVEL SECURITY;
ALTER TABLE subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE chapters_content ENABLE ROW LEVEL SECURITY;
ALTER TABLE banners ENABLE ROW LEVEL SECURITY;
ALTER TABLE app_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE extra_materials ENABLE ROW LEVEL SECURITY;
ALTER TABLE study_products ENABLE ROW LEVEL SECURITY;
ALTER TABLE competitive_exams ENABLE ROW LEVEL SECURITY;
ALTER TABLE competitive_exam_materials ENABLE ROW LEVEL SECURITY;
ALTER TABLE higher_education_materials ENABLE ROW LEVEL SECURITY;

-- Public Read Policies (Allow Anon & Authenticated users to view active content)
DROP POLICY IF EXISTS "Public can view active boards" ON boards;
DROP POLICY IF EXISTS "Public can view active boards" ON boards;
CREATE POLICY "Public can view active boards" ON boards FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active mediums" ON mediums;
DROP POLICY IF EXISTS "Public can view active mediums" ON mediums;
CREATE POLICY "Public can view active mediums" ON mediums FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active standards" ON standards;
DROP POLICY IF EXISTS "Public can view active standards" ON standards;
CREATE POLICY "Public can view active standards" ON standards FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active streams" ON streams;
DROP POLICY IF EXISTS "Public can view active streams" ON streams;
CREATE POLICY "Public can view active streams" ON streams FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active categories" ON learning_categories;
DROP POLICY IF EXISTS "Public can view active categories" ON learning_categories;
CREATE POLICY "Public can view active categories" ON learning_categories FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active modules" ON dashboard_modules;
DROP POLICY IF EXISTS "Public can view active modules" ON dashboard_modules;
CREATE POLICY "Public can view active modules" ON dashboard_modules FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active subjects" ON subjects;
DROP POLICY IF EXISTS "Public can view active subjects" ON subjects;
CREATE POLICY "Public can view active subjects" ON subjects FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active chapters" ON chapters_content;
DROP POLICY IF EXISTS "Public can view active chapters" ON chapters_content;
CREATE POLICY "Public can view active chapters" ON chapters_content FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active banners" ON banners;
DROP POLICY IF EXISTS "Public can view active banners" ON banners;
CREATE POLICY "Public can view active banners" ON banners FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view app settings" ON app_settings;
DROP POLICY IF EXISTS "Public can view app settings" ON app_settings;
CREATE POLICY "Public can view app settings" ON app_settings FOR SELECT USING (true);
DROP POLICY IF EXISTS "Public can view active extra materials" ON extra_materials;
DROP POLICY IF EXISTS "Public can view active extra materials" ON extra_materials;
CREATE POLICY "Public can view active extra materials" ON extra_materials FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active study products" ON study_products;
DROP POLICY IF EXISTS "Public can view active study products" ON study_products;
CREATE POLICY "Public can view active study products" ON study_products FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active competitive exams" ON competitive_exams;
DROP POLICY IF EXISTS "Public can view active competitive exams" ON competitive_exams;
CREATE POLICY "Public can view active competitive exams" ON competitive_exams FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active exam materials" ON competitive_exam_materials;
DROP POLICY IF EXISTS "Public can view active exam materials" ON competitive_exam_materials;
CREATE POLICY "Public can view active exam materials" ON competitive_exam_materials FOR SELECT USING (status = 'active');
DROP POLICY IF EXISTS "Public can view active higher ed materials" ON higher_education_materials;
DROP POLICY IF EXISTS "Public can view active higher ed materials" ON higher_education_materials;
CREATE POLICY "Public can view active higher ed materials" ON higher_education_materials FOR SELECT USING (status = 'active');

-- Service Role / Admin Full Access Policies (Full CRUD for Backend / Admin Panel)
DROP POLICY IF EXISTS "Service role full access on admins" ON admins;
DROP POLICY IF EXISTS "Service role full access on admins" ON admins;
CREATE POLICY "Service role full access on admins" ON admins FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on boards" ON boards;
DROP POLICY IF EXISTS "Service role full access on boards" ON boards;
CREATE POLICY "Service role full access on boards" ON boards FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on mediums" ON mediums;
DROP POLICY IF EXISTS "Service role full access on mediums" ON mediums;
CREATE POLICY "Service role full access on mediums" ON mediums FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on standards" ON standards;
DROP POLICY IF EXISTS "Service role full access on standards" ON standards;
CREATE POLICY "Service role full access on standards" ON standards FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on streams" ON streams;
DROP POLICY IF EXISTS "Service role full access on streams" ON streams;
CREATE POLICY "Service role full access on streams" ON streams FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on categories" ON learning_categories;
DROP POLICY IF EXISTS "Service role full access on categories" ON learning_categories;
CREATE POLICY "Service role full access on categories" ON learning_categories FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on modules" ON dashboard_modules;
DROP POLICY IF EXISTS "Service role full access on modules" ON dashboard_modules;
CREATE POLICY "Service role full access on modules" ON dashboard_modules FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on subjects" ON subjects;
DROP POLICY IF EXISTS "Service role full access on subjects" ON subjects;
CREATE POLICY "Service role full access on subjects" ON subjects FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on chapters" ON chapters_content;
DROP POLICY IF EXISTS "Service role full access on chapters" ON chapters_content;
CREATE POLICY "Service role full access on chapters" ON chapters_content FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on banners" ON banners;
DROP POLICY IF EXISTS "Service role full access on banners" ON banners;
CREATE POLICY "Service role full access on banners" ON banners FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on app_settings" ON app_settings;
DROP POLICY IF EXISTS "Service role full access on app_settings" ON app_settings;
CREATE POLICY "Service role full access on app_settings" ON app_settings FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on extra_materials" ON extra_materials;
DROP POLICY IF EXISTS "Service role full access on extra_materials" ON extra_materials;
CREATE POLICY "Service role full access on extra_materials" ON extra_materials FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on study_products" ON study_products;
DROP POLICY IF EXISTS "Service role full access on study_products" ON study_products;
CREATE POLICY "Service role full access on study_products" ON study_products FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on competitive_exams" ON competitive_exams;
DROP POLICY IF EXISTS "Service role full access on competitive_exams" ON competitive_exams;
CREATE POLICY "Service role full access on competitive_exams" ON competitive_exams FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on exam_materials" ON competitive_exam_materials;
DROP POLICY IF EXISTS "Service role full access on exam_materials" ON competitive_exam_materials;
CREATE POLICY "Service role full access on exam_materials" ON competitive_exam_materials FOR ALL USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Service role full access on higher_ed_materials" ON higher_education_materials;
DROP POLICY IF EXISTS "Service role full access on higher_ed_materials" ON higher_education_materials;
CREATE POLICY "Service role full access on higher_ed_materials" ON higher_education_materials FOR ALL USING (true) WITH CHECK (true);

-- ==============================================================================
-- 4. SUPABASE STORAGE BUCKETS SETUP
-- ==============================================================================
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES 
    ('pdfs', 'pdfs', true, 524288000, ARRAY['application/pdf']),
    ('banners', 'banners', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
    ('icons', 'icons', true, 5242880, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
    ('products', 'products', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
ON CONFLICT (id) DO UPDATE SET 
    public = EXCLUDED.public,
    file_size_limit = EXCLUDED.file_size_limit,
    allowed_mime_types = EXCLUDED.allowed_mime_types;

-- Storage Policies
DROP POLICY IF EXISTS "Public Read on Storage PDFs" ON storage.objects;
DROP POLICY IF EXISTS "Public Read on Storage PDFs" ON storage.objects;
CREATE POLICY "Public Read on Storage PDFs" ON storage.objects FOR SELECT USING (bucket_id = 'pdfs');
DROP POLICY IF EXISTS "Public Read on Storage Banners" ON storage.objects;
DROP POLICY IF EXISTS "Public Read on Storage Banners" ON storage.objects;
CREATE POLICY "Public Read on Storage Banners" ON storage.objects FOR SELECT USING (bucket_id = 'banners');
DROP POLICY IF EXISTS "Public Read on Storage Icons" ON storage.objects;
DROP POLICY IF EXISTS "Public Read on Storage Icons" ON storage.objects;
CREATE POLICY "Public Read on Storage Icons" ON storage.objects FOR SELECT USING (bucket_id = 'icons');
DROP POLICY IF EXISTS "Public Read on Storage Products" ON storage.objects;
DROP POLICY IF EXISTS "Public Read on Storage Products" ON storage.objects;
CREATE POLICY "Public Read on Storage Products" ON storage.objects FOR SELECT USING (bucket_id = 'products');

DROP POLICY IF EXISTS "Service Role Upload on PDFs" ON storage.objects;
DROP POLICY IF EXISTS "Service Role Upload on PDFs" ON storage.objects;
CREATE POLICY "Service Role Upload on PDFs" ON storage.objects FOR INSERT WITH CHECK (bucket_id = 'pdfs');
DROP POLICY IF EXISTS "Service Role Upload on Banners" ON storage.objects;
DROP POLICY IF EXISTS "Service Role Upload on Banners" ON storage.objects;
CREATE POLICY "Service Role Upload on Banners" ON storage.objects FOR INSERT WITH CHECK (bucket_id = 'banners');
DROP POLICY IF EXISTS "Service Role Upload on Icons" ON storage.objects;
DROP POLICY IF EXISTS "Service Role Upload on Icons" ON storage.objects;
CREATE POLICY "Service Role Upload on Icons" ON storage.objects FOR INSERT WITH CHECK (bucket_id = 'icons');
DROP POLICY IF EXISTS "Service Role Upload on Products" ON storage.objects;
DROP POLICY IF EXISTS "Service Role Upload on Products" ON storage.objects;
CREATE POLICY "Service Role Upload on Products" ON storage.objects FOR INSERT WITH CHECK (bucket_id = 'products');

-- ==============================================================================
-- 5. SEED DATA MIGRATION (PRESERVES EXACT IDS AND RELATIONSHIPS)
-- ==============================================================================

-- 5.1. ADMINS
INSERT INTO admins (id, username, email, password_hash, full_name, role, status)
VALUES (1, 'admin', 'admin@bookbanko.com', '$2y$10$tZ92uW7y0gN4e1zZ8b8Wye0zXn.zKzMvFpW4rC9s9cE8X1sI7pEfa', 'Master Administrator', 'super_admin', 'active')
ON CONFLICT (id) DO UPDATE SET username = EXCLUDED.username;

-- 5.2. BOARDS
INSERT INTO boards (id, code, name, description, icon, display_order, status) VALUES
(1, 'GSEB', 'Gujarat Secondary and Higher Secondary Education Board', 'State board curriculum for Gujarat State', 'school', 1, 'active'),
(2, 'CBSE', 'Central Board of Secondary Education', 'National level education board in India', 'menu_book', 2, 'active'),
(3, 'NCERT', 'National Council of Educational Research and Training', 'Standard central curriculum books', 'auto_stories', 3, 'active'),
(4, 'ICSE', 'Indian Certificate of Secondary Education', 'CISCE affiliated national curriculum', 'apartment', 4, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description;

-- 5.3. MEDIUMS
INSERT INTO mediums (id, code, name, description, icon, display_order, status) VALUES
(1, 'gujarati', 'Gujarati Medium', 'Gujarati Medium Curriculum & Textbooks', 'translate', 1, 'active'),
(2, 'english', 'English Medium', 'English Medium Curriculum & Textbooks', 'language', 2, 'active'),
(3, 'hindi', 'Hindi Medium', 'Hindi Medium Curriculum & Textbooks', 'menu_book', 3, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description;

-- 5.4. STANDARDS
INSERT INTO standards (id, board_id, medium_id, standard_number, name, requires_stream, display_order, status) VALUES
(1, 1, 1, 6, 'Standard 6', false, 1, 'active'),
(2, 1, 1, 7, 'Standard 7', false, 2, 'active'),
(3, 1, 1, 8, 'Standard 8', false, 3, 'active'),
(4, 1, 1, 9, 'Standard 9', false, 4, 'active'),
(5, 1, 1, 10, 'Standard 10', false, 5, 'active'),
(6, 1, 1, 11, 'Standard 11', true, 6, 'active'),
(7, 1, 1, 12, 'Standard 12', true, 7, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, requires_stream = EXCLUDED.requires_stream;

-- 5.5. STREAMS
INSERT INTO streams (id, board_id, medium_id, standard_id, slug, name, description, icon, display_order, status) VALUES
(1, 1, 1, 6, 'science', 'Science', 'Medical, Engineering & Technical Subjects', 'biotech', 1, 'active'),
(2, 1, 1, 6, 'commerce', 'Commerce', 'Accountancy, Finance & Business Studies', 'account_balance', 2, 'active'),
(3, 1, 1, 6, 'arts', 'Arts / Humanities', 'Literature, History, Geography & Social Sciences', 'palette', 3, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description;

-- 5.6. LEARNING CATEGORIES
INSERT INTO learning_categories (id, slug, name, icon, color_hex, display_order, status) VALUES
(1, 'extra_material', 'Extra Material', 'bookmark_rounded', '#0061A4', 1, 'active'),
(2, 'study_material', 'Study Material', 'menu_book_rounded', '#2196F3', 2, 'active'),
(3, 'competitive_exams', 'Competitive Exams', 'emoji_events_rounded', '#F59E0B', 3, 'active'),
(4, 'higher_education', 'Higher Education', 'account_balance_rounded', '#10B981', 4, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, color_hex = EXCLUDED.color_hex;

-- 5.7. DASHBOARD MODULES
INSERT INTO dashboard_modules (id, title, slug, route_key, icon, badge_text, display_order, status) VALUES
(1, 'Textbooks', 'textbooks', '/textbooks', 'menu_book_rounded', 'Essential', 1, 'active'),
(2, 'Old PYQs', 'pyqs', '/pyqs', 'history_edu_rounded', 'Past Papers', 2, 'active'),
(3, 'Paper Sets', 'paper_sets', '/paper_sets', 'assignment_rounded', 'Practice', 3, 'active'),
(4, 'Blueprint', 'blueprint', '/blueprint', 'architecture_rounded', 'Official', 4, 'active'),
(5, 'M.IMP', 'mimp', '/mimp', 'star_rounded', 'Most Important', 5, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, badge_text = EXCLUDED.badge_text;

-- 5.8. SUBJECTS
INSERT INTO subjects (id, board_id, medium_id, standard_id, stream_id, name, code, icon, color_hex, display_order, status) VALUES
-- Standard 9 (GSEB Gujarati Medium)
(1, 1, 1, 4, NULL, 'Gujarati', 'guj', 'translate_rounded', '#4F46E5', 1, 'active'),
(2, 1, 1, 4, NULL, 'Mathematics', 'math', 'calculate_rounded', '#0284C7', 2, 'active'),
(3, 1, 1, 4, NULL, 'Science & Technology', 'sci', 'science_rounded', '#059669', 3, 'active'),
(4, 1, 1, 4, NULL, 'Social Science', 'ss', 'public_rounded', '#D97706', 4, 'active'),
(5, 1, 1, 4, NULL, 'English', 'eng', 'language_rounded', '#7C3AED', 5, 'active'),
(6, 1, 1, 4, NULL, 'Hindi', 'hin', 'menu_book_rounded', '#DC2626', 6, 'active'),
(7, 1, 1, 4, NULL, 'Sanskrit', 'sans', 'auto_stories_rounded', '#0891B2', 7, 'active'),
(8, 1, 1, 4, NULL, 'Computer Studies', 'cs', 'computer_rounded', '#2563EB', 8, 'active'),
-- Standard 10 (GSEB Gujarati Medium)
(9, 1, 1, 5, NULL, 'Mathematics (Standard)', 'math_std', 'calculate_rounded', '#0284C7', 1, 'active'),
(10, 1, 1, 5, NULL, 'Mathematics (Basic)', 'math_basic', 'calculate_rounded', '#0369A1', 2, 'active'),
(11, 1, 1, 5, NULL, 'Science', 'sci_10', 'science_rounded', '#059669', 3, 'active'),
(12, 1, 1, 5, NULL, 'Social Science', 'ss_10', 'public_rounded', '#D97706', 4, 'active'),
(13, 1, 1, 5, NULL, 'English First Language', 'eng_fl', 'language_rounded', '#7C3AED', 5, 'active'),
(14, 1, 1, 5, NULL, 'Gujarati First Language', 'guj_fl', 'translate_rounded', '#4F46E5', 6, 'active'),
-- Standard 11 Science (GSEB Gujarati Medium)
(15, 1, 1, 6, 1, 'Physics', 'phy_11', 'bolt_rounded', '#0284C7', 1, 'active'),
(16, 1, 1, 6, 1, 'Chemistry', 'chem_11', 'science_rounded', '#059669', 2, 'active'),
(17, 1, 1, 6, 1, 'Mathematics', 'math_11', 'calculate_rounded', '#2563EB', 3, 'active'),
(18, 1, 1, 6, 1, 'Biology', 'bio_11', 'biotech_rounded', '#16A34A', 4, 'active'),
(19, 1, 1, 6, 1, 'English', 'eng_11', 'language_rounded', '#7C3AED', 5, 'active')
ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, code = EXCLUDED.code;

-- 5.9. CHAPTERS CONTENT
INSERT INTO chapters_content (id, subject_id, module_id, chapter_number, title, description, pdf_file_path, pdf_external_url, page_count, file_size_mb, display_order, status) VALUES
(1, 2, 1, 1, 'Number Systems', 'Introduction to rational and irrational numbers, real numbers and their decimal expansions.', 'ch1_number_systems.pdf', '', 42, 3.45, 1, 'active'),
(2, 2, 1, 2, 'Polynomials', 'Definition of a polynomial, zeros of a polynomial, remainder theorem and factorization.', 'ch2_polynomials.pdf', '', 38, 2.80, 2, 'active'),
(3, 2, 1, 3, 'Coordinate Geometry', 'Cartesian plane, coordinates of a point, plotting points in the plane.', 'ch3_coordinate_geometry.pdf', '', 26, 1.95, 3, 'active'),
(4, 2, 1, 4, 'Linear Equations in Two Variables', 'Standard form of linear equations, graph of linear equation in two variables.', 'ch4_linear_equations.pdf', '', 30, 2.20, 4, 'active'),
(5, 2, 1, 5, 'Introduction to Euclid''s Geometry', 'Euclid''s definitions, axioms and postulates with practical explanations.', 'ch5_euclids_geometry.pdf', '', 22, 1.60, 5, 'active'),
(6, 2, 1, 6, 'Lines and Angles', 'Basic terms and definitions, intersecting lines, pairs of angles and parallel lines.', 'ch6_lines_and_angles.pdf', '', 35, 2.90, 6, 'active'),
(7, 2, 1, 7, 'Triangles', 'Congruence of triangles, criteria for congruence (SAS, ASA, SSS, RHS).', 'ch7_triangles.pdf', '', 40, 3.10, 7, 'active'),
(8, 2, 1, 8, 'Quadrilaterals', 'Angle sum property of a quadrilateral, types of quadrilaterals and their properties.', 'ch8_quadrilaterals.pdf', '', 32, 2.50, 8, 'active'),
(9, 2, 1, 9, 'Circles', 'Circle and its related terms, chord and subtended angles, cyclic quadrilaterals.', 'ch9_circles.pdf', '', 28, 2.10, 9, 'active'),
(10, 2, 1, 10, 'Heron''s Formula', 'Area of a triangle using Heron''s formula and practical applications.', 'ch10_herons_formula.pdf', '', 18, 1.40, 10, 'active'),
(11, 2, 1, 11, 'Surface Areas and Volumes', 'Surface area and volume of cuboids, cylinders, cones and spheres.', 'ch11_surface_areas.pdf', '', 45, 3.80, 11, 'active'),
(12, 2, 1, 12, 'Statistics', 'Collection and presentation of data, graphical representation, bar graphs and histograms.', 'ch12_statistics.pdf', '', 36, 2.75, 12, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, pdf_file_path = EXCLUDED.pdf_file_path;

-- 5.10. BANNERS
INSERT INTO banners (id, title, sub_title, image_path, action_type, action_value, display_order, status) VALUES
(1, 'GSEB 2026 Board Exam Blueprints Released!', 'Download official subject-wise blueprints & question formats', 'banner_blueprint.png', 'none', '', 1, 'active'),
(2, 'Score 90%+ with Most IMP Question Sets', 'Specially curated revision material for Standards 9 & 10', 'banner_imp.png', 'none', '', 2, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, image_path = EXCLUDED.image_path;

-- 5.11. APP SETTINGS
INSERT INTO app_settings (id, setting_key, setting_value, description) VALUES
(1, 'app_name', 'Book Banko', 'Application Title'),
(2, 'app_version', '1.0.0', 'Latest App Build Version'),
(3, 'force_update', '0', 'Set to 1 to force app update'),
(4, 'maintenance_mode', '0', 'Set to 1 to enable app maintenance mode'),
(5, 'maintenance_message', 'We are updating our content library. Please check back shortly.', 'Maintenance notice display'),
(6, 'contact_email', 'support@bookbanko.com', 'Support Helpdesk Email'),
(7, 'privacy_policy_url', 'https://bookbanko.com/privacy', 'Privacy Policy URL'),
(8, 'terms_conditions_url', 'https://bookbanko.com/terms', 'Terms & Conditions URL'),
(9, 'notice_title', 'Welcome to Book Banko!', 'Home announcement title'),
(10, 'notice_message', 'Access free textbook PDFs, PYQs, and study guides for all Gujarat & CBSE standards.', 'Home announcement body')
ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, description = EXCLUDED.description;

-- 5.12. COMPETITIVE EXAMS
INSERT INTO competitive_exams (id, title, slug, exam_code, category, description, icon, color_hex, display_order, status) VALUES
(1, 'JEE (Main & Advanced)', 'jee', 'JEE', 'Engineering Entrance', 'Joint Entrance Examination for IITs, NITs & Top Engineering Colleges. Solved PYQs, Formula Sheets & Mock Papers.', 'engineering_rounded', '#0061A4', 1, 'active'),
(2, 'NEET (UG)', 'neet', 'NEET', 'Medical Entrance', 'National Eligibility cum Entrance Test for MBBS & BDS. Complete Biology, Physics & Chemistry Solved Papers.', 'biotech_rounded', '#059669', 2, 'active'),
(3, 'GATE', 'gate', 'GATE', 'Postgraduate & PSU', 'Graduate Aptitude Test in Engineering for M.Tech & PSU Recruitment. Engineering Math, Aptitude & Technical Papers.', 'account_balance_rounded', '#7C3AED', 3, 'active'),
(4, 'GUJCET', 'gujcet', 'GUJCET', 'Gujarat State Entrance', 'Gujarat Common Entrance Test for Degree Engineering and Pharmacy Admissions in Gujarat.', 'school_rounded', '#D97706', 4, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, slug = EXCLUDED.slug;

-- 5.13. COMPETITIVE EXAM MATERIALS
INSERT INTO competitive_exam_materials (id, exam_id, title, subject_name, material_type, year, description, pdf_file_path, pdf_external_url, page_count, file_size_mb, display_order, status) VALUES
(1, 1, 'JEE Main 2024 Physics Question Paper with Complete Solutions', 'Physics', 'Question Paper', '2024', 'Official JEE Main Shift 1 & 2 solved physics paper with step-by-step detailed explanations.', 'bb_6ab901c015ba22.92430469.pdf', '', 38, 2.45, 1, 'active'),
(2, 1, 'JEE Mathematics Master Formula Book & Quick Tricks', 'Mathematics', 'Formula Book', '2025', 'Complete compilation of Calculus, Algebra, Coordinate Geometry and Vectors formulas with short tricks.', 'bb_6ab901c015ba22.92430469.pdf', '', 52, 3.10, 2, 'active'),
(3, 1, 'JEE Chemistry Organic Reactions & Mechanism Revision Notes', 'Chemistry', 'Notes', '2025', 'High-yield named reactions, reagents, conversions and mechanisms for JEE Main & Advanced.', 'bb_6ab901c015ba22.92430469.pdf', '', 45, 2.80, 3, 'active'),
(4, 2, 'NEET UG 2024 Biology 10-Year Solved Chapterwise PYQs', 'Biology', 'Question Paper', '2024', 'NCERT line-by-line past 10 years NEET questions with verified answer keys.', 'bb_6ab901c015ba22.92430469.pdf', '', 64, 4.20, 1, 'active'),
(5, 2, 'NEET Physics Most Scoring Topics Formula Handbook', 'Physics', 'Formula Book', '2025', 'Formula handbook covering Mechanics, Electrodynamics, Optics and Modern Physics.', 'bb_6ab901c015ba22.92430469.pdf', '', 36, 2.15, 2, 'active'),
(6, 3, 'GATE General Aptitude & Engineering Mathematics Solved Papers', 'General Aptitude', 'Question Paper', '2024', 'Comprehensive solved papers for common 30 marks section covering Quantitative Aptitude and Engineering Math.', 'bb_6ab901c015ba22.92430469.pdf', '', 48, 3.50, 1, 'active'),
(7, 3, 'GATE Core Subject Quick Revision Notes & Concepts', 'Technical', 'Notes', '2025', 'Handwritten summary notes and high priority formulas for GATE technical sections.', 'bb_6ab901c015ba22.92430469.pdf', '', 55, 3.90, 2, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, pdf_file_path = EXCLUDED.pdf_file_path;

-- 5.14. STUDY PRODUCTS
INSERT INTO study_products (id, title, description, affiliate_link, search_tags, image_path, display_order, status, is_fallback_ad) VALUES
(1, 'Premium Gel Pens Pack (0.5mm Blue/Black)', 'Smooth writing waterproof ink gel pens for exam notes and fast handwriting practice.', 'https://amzn.to/example-gel-pens', 'pen, gel pen, writing, stationery', 'ball_pen.jpg', 1, 'active', false),
(2, 'Classmate Long Notebooks (Set of 6, 240 Pages)', 'Premium quality smooth paper ruled long notebooks for school & college students.', 'https://amzn.to/example-notebooks', 'notebook, register, classmate, notes', 'notebook.jpg', 2, 'active', false),
(3, 'Apsara Platinum Extra Dark Pencils Pack', 'Dark, smooth drawing & writing pencils with sharpener and eraser included.', 'https://amzn.to/example-pencils', 'pencil, apsara, drawing, stationary', 'pencils.jpg', 3, 'active', false),
(4, 'Dust-Free Non-Toxic Erasers (Pack of 5)', 'Clean erasing without paper tearing or smudge marks.', 'https://amzn.to/example-eraser', 'eraser, rubber, stationery', 'eraser.jpg', 4, 'active', false),
(5, 'Metal Sharpener with Rust-Resistant Blade', 'Precision contour sharpener suitable for all standard wooden pencils.', 'https://amzn.to/example-sharpener', 'sharpener, pencil, stationery', 'sharpener.jpg', 5, 'active', false)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, affiliate_link = EXCLUDED.affiliate_link;

-- 5.15. HIGHER EDUCATION MATERIALS
INSERT INTO higher_education_materials (id, title, course_name, material_type, description, pdf_file_path, pdf_external_url, page_count, file_size_mb, is_free, display_order, status) VALUES
(1, 'Engineering Mathematics I - Calculus & Matrices Handbook', 'B.Tech / B.E.', 'Notes & Formulas', 'Complete reference guide covering Differential Calculus, Linear Algebra, and Multiple Integrals for 1st Year Engineering.', 'bb_6ab901c015ba22.92430469.pdf', '', 56, 3.80, true, 1, 'active'),
(2, 'Data Structures & Algorithms in C++ Complete Lecture Notes', 'B.Tech / BCA / MCA', 'Lecture Notes', 'Stacks, Queues, Linked Lists, Binary Search Trees, Graphs & Dynamic Programming with code snippets.', 'bb_6ab901c015ba22.92430469.pdf', '', 72, 4.50, true, 2, 'active'),
(3, 'Financial Accounting & Corporate Auditing Principles', 'B.Com / BBA / M.Com', 'Study Material', 'Comprehensive textbook material for accounting concepts, balance sheet analysis, and tax audits.', 'bb_6ab901c015ba22.92430469.pdf', '', 65, 4.10, true, 3, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, pdf_file_path = EXCLUDED.pdf_file_path;

-- 5.16. EXTRA MATERIALS
INSERT INTO extra_materials (id, category_id, category_slug, board_id, medium_id, standard_id, subject_id, title, description, pdf_file_path, pdf_external_url, page_count, file_size_mb, is_free, display_order, status) VALUES
(1, 1, 'extra_material', 1, 1, 4, 2, 'Class 9 Maths Quick Formula Revision Sheet', 'All important algebra, geometry, mensuration and statistics formulas summarized on 4 pages.', 'bb_6ab901c015ba22.92430469.pdf', '', 4, 0.95, true, 1, 'active'),
(2, 1, 'extra_material', 1, 1, 5, 11, 'Class 10 Science Most Frequent Board Exam Questions', 'Chapterwise analysis of top repeating questions and diagrams for board preparation.', 'bb_6ab901c015ba22.92430469.pdf', '', 28, 2.10, true, 2, 'active')
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, pdf_file_path = EXCLUDED.pdf_file_path;

-- ==============================================================================
-- 6. SEQUENCE SYNCHRONIZATION
-- ==============================================================================
SELECT setval('admins_id_seq', COALESCE((SELECT MAX(id) FROM admins), 1));
SELECT setval('boards_id_seq', COALESCE((SELECT MAX(id) FROM boards), 1));
SELECT setval('mediums_id_seq', COALESCE((SELECT MAX(id) FROM mediums), 1));
SELECT setval('standards_id_seq', COALESCE((SELECT MAX(id) FROM standards), 1));
SELECT setval('streams_id_seq', COALESCE((SELECT MAX(id) FROM streams), 1));
SELECT setval('learning_categories_id_seq', COALESCE((SELECT MAX(id) FROM learning_categories), 1));
SELECT setval('dashboard_modules_id_seq', COALESCE((SELECT MAX(id) FROM dashboard_modules), 1));
SELECT setval('subjects_id_seq', COALESCE((SELECT MAX(id) FROM subjects), 1));
SELECT setval('chapters_content_id_seq', COALESCE((SELECT MAX(id) FROM chapters_content), 1));
SELECT setval('banners_id_seq', COALESCE((SELECT MAX(id) FROM banners), 1));
SELECT setval('app_settings_id_seq', COALESCE((SELECT MAX(id) FROM app_settings), 1));
SELECT setval('extra_materials_id_seq', COALESCE((SELECT MAX(id) FROM extra_materials), 1));
SELECT setval('study_products_id_seq', COALESCE((SELECT MAX(id) FROM study_products), 1));
SELECT setval('competitive_exams_id_seq', COALESCE((SELECT MAX(id) FROM competitive_exams), 1));
SELECT setval('competitive_exam_materials_id_seq', COALESCE((SELECT MAX(id) FROM competitive_exam_materials), 1));
SELECT setval('higher_education_materials_id_seq', COALESCE((SELECT MAX(id) FROM higher_education_materials), 1));
