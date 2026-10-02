-- Run this only if your database was created before the borrower-PIN
-- feature existed. (A fresh install using database/schema.sql already
-- includes it.)

USE nsokhkaen_inventory;

CREATE TABLE IF NOT EXISTS borrowers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NULL,
    pin_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
