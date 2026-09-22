-- Run this only if your database was created before the settings/storage_locations
-- tables existed. (A fresh install using database/schema.sql already includes them.)

USE nsokhkaen_inventory;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS storage_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('app_name', 'ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น'),
('app_url', 'http://localhost/nsokhkaen_inventory/');

-- Seed the dropdown with whatever storage_location text already exists in your data.
INSERT IGNORE INTO storage_locations (name)
SELECT DISTINCT storage_location FROM materials WHERE storage_location IS NOT NULL AND storage_location <> '';

INSERT IGNORE INTO storage_locations (name)
SELECT DISTINCT storage_location FROM assets WHERE storage_location IS NOT NULL AND storage_location <> '';
