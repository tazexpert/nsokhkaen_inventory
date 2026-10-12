-- Admin can approve a requisition; once approved it can no longer be
-- cancelled (see api/requisition_api.php: approveRequisition()/
-- cancelRequisition()). NULL approved_at means still pending.

USE nsokhkaen_inventory;

ALTER TABLE requisitions
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER created_at,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD CONSTRAINT fk_req_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
