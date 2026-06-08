-- Energy Talks Middle East - Database Setup
-- Run this SQL in your cPanel phpMyAdmin or MySQL client

CREATE DATABASE IF NOT EXISTS energytalks_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE energytalks_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    status ENUM('active', 'inactive') DEFAULT 'active',
    permissions JSON DEFAULT NULL,
    post_count INT DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Blog posts table
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    category VARCHAR(100) NOT NULL,
    excerpt TEXT NOT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(500) DEFAULT '',
    published TINYINT(1) DEFAULT 1,
    featured TINYINT(1) DEFAULT 0,
    read_time VARCHAR(20) DEFAULT '5 min',
    tags JSON DEFAULT NULL,
    view_count INT DEFAULT 0,
    author_id INT DEFAULT NULL,
    published_date DATE DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_published (published),
    INDEX idx_category (category),
    INDEX idx_published_date (published_date DESC)
) ENGINE=InnoDB;

-- Subscribers table
CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT '',
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, full_name, password_hash, role, status, permissions)
VALUES (
    'admin',
    'admin@energytalks.me',
    'Administrator',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'active',
    '{"create":true,"edit":true,"editAll":true,"delete":true,"publish":true,"subscribers":true}'
);

-- Insert sample blog posts
INSERT INTO posts (slug, title, category, excerpt, content, featured_image, published, featured, read_time, tags, view_count, author_id, published_date) VALUES
('uae-adnoc-low-carbon-investments', 'UAE''s ADNOC Accelerates Low-Carbon Investments', 'Oil & Gas', 'ADNOC is investing heavily in carbon capture and hydrogen technologies as part of its sustainability roadmap.', '<p>Abu Dhabi National Oil Company (ADNOC) has announced a significant expansion of its low-carbon investments, marking a pivotal shift in the region''s energy landscape.</p><h3>Strategic Investments</h3><p>The company is allocating substantial resources to carbon capture, utilization, and storage (CCUS) technologies, alongside hydrogen production facilities.</p><h3>Key Developments</h3><p>ADNOC''s carbon capture facility is expected to be one of the largest in the Middle East, with capacity to capture millions of tons of CO2 annually.</p>', 'https://images.unsplash.com/photo-1497435334941-8c899ee9e8e9?w=800&q=80', 1, 1, '5 min', '["ADNOC","carbon capture","hydrogen","UAE"]', 245, 1, '2025-12-15'),

('saudi-arabia-50b-solar-initiative', 'Saudi Arabia''s $50B Solar Initiative', 'Renewables', 'The Kingdom''s ambitious solar energy program is attracting global investors and reshaping the regional energy mix.', '<p>Saudi Arabia has unveiled a massive $50 billion solar energy initiative as part of its Vision 2030 diversification strategy.</p><h3>Project Scale</h3><p>The initiative includes multiple utility-scale solar projects with a combined capacity exceeding 40 gigawatts.</p>', 'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=800&q=80', 1, 1, '6 min', '["Saudi Arabia","solar","Vision 2030"]', 189, 1, '2025-12-12'),

('oil-prices-stabilize-opec-decisions', 'Oil Prices Stabilize Amid OPEC+ Decisions', 'Market Analysis', 'OPEC+ coordination continues to influence global oil markets with strategic production adjustments.', '<p>Global oil prices have stabilized following the latest OPEC+ ministerial meeting.</p><h3>Market Impact</h3><p>The decision to maintain current production levels has provided stability to global markets.</p>', 'https://images.unsplash.com/photo-1611273426858-450d8e3c9fce?w=800&q=80', 1, 0, '4 min', '["OPEC","oil prices"]', 156, 1, '2025-12-10'),

('ai-transforming-middle-east-oil-fields', 'AI Transforming Middle East Oil Fields', 'Technology', 'Artificial intelligence and machine learning are revolutionizing exploration and production across the region.', '<p>The Middle East''s oil and gas industry is undergoing a digital transformation with AI and ML technologies.</p>', 'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=800&q=80', 1, 0, '5 min', '["AI","technology"]', 134, 1, '2025-12-08'),

('green-hydrogen-hub-plans-expand', 'Green Hydrogen Hub Plans Expand', 'Hydrogen', 'GCC countries are positioning themselves as major green hydrogen exporters to global markets.', '<p>Gulf Cooperation Council nations are rapidly developing green hydrogen production capabilities.</p>', 'https://images.unsplash.com/photo-1548337138-e87d889cc369?w=800&q=80', 1, 0, '5 min', '["hydrogen","green energy","GCC"]', 98, 1, '2025-12-05'),

('nocs-strengthen-esg-commitments', 'NOCs Strengthen ESG Commitments', 'ESG', 'National oil companies across the region are enhancing their ESG frameworks and reporting standards.', '<p>National oil companies are significantly strengthening their environmental, social, and governance commitments.</p>', 'https://images.unsplash.com/photo-1569163139599-0f4517e36f51?w=800&q=80', 1, 0, '4 min', '["ESG","sustainability","NOC"]', 87, 1, '2025-12-03');
