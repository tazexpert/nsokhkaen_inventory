-- Adds fields needed to import the office's own "รายงานวัสดุคงเหลือ" stocktake
-- report (ลำดับที่ / รหัสหมวดวัสดุ / รายการ / หน่วยนับ / ยอดตามบัญชี / ยอดตรวจนับ /
-- ราคาต่อหน่วย / ยอดรวม / หมายเหตุ / ลำดับในทะเบียนรายงานวัสดุ), in addition to
-- the two material import formats already supported.
--
-- stock_qty keeps meaning "current quantity on hand" as before - on import
-- from this report it's set to ยอดตรวจนับ (the physically counted amount,
-- the authoritative figure), while book_qty/count_date keep the book figure
-- and the count date for reference/reconciliation only.

USE nsokhkaen_inventory;

ALTER TABLE materials
    ADD COLUMN category_code VARCHAR(20) NULL AFTER category_id,
    ADD COLUMN book_qty INT NULL AFTER stock_qty,
    ADD COLUMN count_date DATE NULL AFTER book_qty,
    ADD COLUMN ledger_no INT NULL AFTER count_date;
