-- Run this only if your database was created before the requisitions (ใบเบิก)
-- feature existed. (A fresh install using database/schema.sql already includes it.)

USE nsokhkaen_inventory;

CREATE TABLE IF NOT EXISTS requisitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_no VARCHAR(50) NOT NULL UNIQUE,
    purpose VARCHAR(255) NULL,
    requester_name VARCHAR(150) NULL,
    requester_position VARCHAR(150) NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_req_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS requisition_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT UNSIGNED NOT NULL,
    item_type ENUM('material', 'asset') NOT NULL,
    material_id INT UNSIGNED NULL,
    asset_id INT UNSIGNED NULL,
    item_name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NULL,
    quantity_requested INT NOT NULL DEFAULT 1,
    quantity_issued INT NOT NULL DEFAULT 1,
    note VARCHAR(255) NULL,
    CONSTRAINT fk_reqitem_requisition FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_reqitem_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL,
    CONSTRAINT fk_reqitem_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE material_transactions
    ADD COLUMN requisition_id INT UNSIGNED NULL AFTER user_id,
    ADD CONSTRAINT fk_mtx_requisition FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL;

ALTER TABLE asset_transactions
    ADD COLUMN requisition_id INT UNSIGNED NULL AFTER user_id,
    ADD CONSTRAINT fk_atx_requisition FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL;
