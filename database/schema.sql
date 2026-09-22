-- ============================================================
-- Inventory Management System
-- Provincial Statistical Office of Khon Kaen
-- Database: nsokhkaen_inventory
-- ============================================================

CREATE DATABASE IF NOT EXISTS nsokhkaen_inventory
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE nsokhkaen_inventory;

-- ------------------------------------------------------------
-- Users & Roles
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Categories (shared by materials and assets)
-- ------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    item_type ENUM('material', 'asset') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Materials (consumables - withdraw reduces stock quantity)
-- ------------------------------------------------------------
CREATE TABLE materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_code VARCHAR(50) NOT NULL UNIQUE,
    qr_code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'ชิ้น',
    stock_qty INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    storage_location VARCHAR(255) NULL,
    note TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_materials_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_materials_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Assets (fixed assets - each unit has its own code, borrow/return)
-- ------------------------------------------------------------
CREATE TABLE assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    qr_code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NULL,
    brand_model VARCHAR(255) NULL,
    serial_number VARCHAR(150) NULL,
    status ENUM('available', 'borrowed', 'maintenance', 'disposed') NOT NULL DEFAULT 'available',
    storage_location VARCHAR(255) NULL,
    acquired_date DATE NULL,
    note TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assets_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Material transactions (withdraw / stock-in history)
-- ------------------------------------------------------------
CREATE TABLE material_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('withdraw', 'stock_in') NOT NULL,
    quantity INT NOT NULL,
    balance_after INT NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mtx_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    CONSTRAINT fk_mtx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Asset transactions (borrow / return history)
-- ------------------------------------------------------------
CREATE TABLE asset_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action ENUM('borrow', 'return') NOT NULL,
    borrower_name VARCHAR(150) NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_atx_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    CONSTRAINT fk_atx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Mock data
-- ------------------------------------------------------------

-- password for both accounts is: password123
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$12$fuD/beirJBBABd/usQP9oebzSe8/YoNL6kdSYTJ7nqg.ba.anUcFO', 'ผู้ดูแลระบบ', 'admin'),
('staff1', '$2y$12$fuD/beirJBBABd/usQP9oebzSe8/YoNL6kdSYTJ7nqg.ba.anUcFO', 'เจ้าหน้าที่พัสดุ', 'staff');

INSERT INTO categories (name, item_type) VALUES
('เครื่องเขียน', 'material'),
('วัสดุสำนักงาน', 'material'),
('คอมพิวเตอร์และอุปกรณ์ต่อพ่วง', 'asset'),
('ครุภัณฑ์สำนักงาน', 'asset');

INSERT INTO materials (material_code, qr_code, name, category_id, unit, stock_qty, min_stock, storage_location) VALUES
('MAT-0001', 'MAT-QR-0001', 'ปากกาลูกลื่นสีน้ำเงิน', 1, 'ด้าม', 150, 20, 'ห้องพัสดุ ชั้น 1'),
('MAT-0002', 'MAT-QR-0002', 'กระดาษ A4 80 แกรม', 2, 'รีม', 60, 10, 'ห้องพัสดุ ชั้น 1'),
('MAT-0003', 'MAT-QR-0003', 'แฟ้มสันกว้าง', 2, 'เล่ม', 40, 5, 'ห้องพัสดุ ชั้น 1');

INSERT INTO assets (asset_code, qr_code, name, category_id, brand_model, serial_number, status, storage_location, acquired_date) VALUES
('AST-0001', 'AST-QR-0001', 'คอมพิวเตอร์ตั้งโต๊ะ', 3, 'Dell OptiPlex 3090', 'SN-DL-0001', 'available', 'ห้องปฏิบัติการ ชั้น 2', '2023-05-10'),
('AST-0002', 'AST-QR-0002', 'เครื่องพิมพ์เลเซอร์', 3, 'HP LaserJet M404dn', 'SN-HP-0002', 'available', 'ห้องธุรการ ชั้น 1', '2022-11-02'),
('AST-0003', 'AST-QR-0003', 'โต๊ะทำงานเหล็ก', 4, '-', '-', 'borrowed', 'ห้องประชุม ชั้น 3', '2021-02-15');
