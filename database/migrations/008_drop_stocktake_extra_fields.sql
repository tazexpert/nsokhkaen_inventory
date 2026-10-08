-- Migration 007 added book_qty/count_date/ledger_no to support the richer
-- "รายงานวัสดุคงเหลือ" stocktake report format (with separate ยอดตามบัญชี/
-- ยอดตรวจนับ, ราคาต่อหน่วย, หมายเหตุ, ลำดับในทะเบียน columns). The office's
-- actual stock report file only has ลำดับที่ / รหัสหมวดวัสดุ / รายการ /
-- หน่วยนับ / ยอดตามบัญชี (a single quantity column), so these extra fields
-- are unused - drop them.

USE nsokhkaen_inventory;

ALTER TABLE materials
    DROP COLUMN book_qty,
    DROP COLUMN count_date,
    DROP COLUMN ledger_no;
