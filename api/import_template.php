<?php
// Generates a blank Excel template with the exact column headers import_api.php expects.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminApi();

use Shuchkin\SimpleXLSXGen;

$type = $_GET['type'] ?? '';
if (!in_array($type, ['material', 'asset'], true)) {
    http_response_code(422);
    exit('invalid type');
}

if ($type === 'material') {
    $rows = [
        ['name', 'category_id', 'unit', 'unit_cost', 'stock_qty', 'min_stock', 'storage_location', 'note'],
        ['ตัวอย่าง: ปากกาลูกลื่น', '1', 'ด้าม', '5.00', '100', '10', 'ห้องพัสดุ ชั้น 1', ''],
    ];
    $filename = 'material_import_template.xlsx';
} else {
    $rows = [
        ['name', 'category_id', 'brand_model', 'serial_number', 'storage_location', 'acquired_date', 'note'],
        ['ตัวอย่าง: เครื่องคอมพิวเตอร์', '3', 'Dell OptiPlex 3090', 'SN-0001', 'ห้องปฏิบัติการ ชั้น 2', '2024-01-01', ''],
    ];
    $filename = 'asset_import_template.xlsx';
}

SimpleXLSXGen::fromArray($rows, 'template')->downloadAs($filename);
