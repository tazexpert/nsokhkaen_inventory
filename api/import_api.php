<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminApi();
$pdo = getDbConnection();

use PhpOffice\PhpSpreadsheet\IOFactory;

$type = $_POST['type'] ?? '';
if (!in_array($type, ['material', 'asset'], true)) {
    jsonResponse(['success' => false, 'message' => 'ประเภทข้อมูลไม่ถูกต้อง'], 422);
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า'], 422);
}

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์ Excel ได้: ' . $e->getMessage()], 422);
}

if (count($rows) < 2) {
    jsonResponse(['success' => false, 'message' => 'ไฟล์ไม่มีข้อมูล'], 422);
}

$header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
$dataRows = array_slice($rows, 1);

$imported = 0;
$skipped = 0;

$pdo->beginTransaction();
try {
    foreach ($dataRows as $row) {
        $record = array_combine($header, $row);
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            $skipped++;
            continue;
        }

        $categoryId = !empty($record['category_id']) ? (int) $record['category_id'] : null;
        $storageLocation = trim((string) ($record['storage_location'] ?? '')) ?: null;
        $note = trim((string) ($record['note'] ?? '')) ?: null;

        if ($type === 'material') {
            $code = generateNextCode($pdo, 'materials', 'material_code', 'MAT');
            $qr = generateQrPayload($code);
            $unit = trim((string) ($record['unit'] ?? 'ชิ้น')) ?: 'ชิ้น';
            $stockQty = (int) ($record['stock_qty'] ?? 0);
            $minStock = (int) ($record['min_stock'] ?? 0);

            $stmt = $pdo->prepare("INSERT INTO materials
                (material_code, qr_code, name, category_id, unit, stock_qty, min_stock, storage_location, note, created_by)
                VALUES (:code, :qr, :name, :category_id, :unit, :stock_qty, :min_stock, :location, :note, :created_by)");
            $stmt->execute([
                'code' => $code, 'qr' => $qr, 'name' => $name, 'category_id' => $categoryId,
                'unit' => $unit, 'stock_qty' => $stockQty, 'min_stock' => $minStock,
                'location' => $storageLocation, 'note' => $note, 'created_by' => $_SESSION['user']['id'],
            ]);
        } else {
            $code = generateNextCode($pdo, 'assets', 'asset_code', 'AST');
            $qr = generateQrPayload($code);
            $brandModel = trim((string) ($record['brand_model'] ?? '')) ?: null;
            $serial = trim((string) ($record['serial_number'] ?? '')) ?: null;
            $acquiredDate = trim((string) ($record['acquired_date'] ?? '')) ?: null;

            $stmt = $pdo->prepare("INSERT INTO assets
                (asset_code, qr_code, name, category_id, brand_model, serial_number, status, storage_location, acquired_date, note, created_by)
                VALUES (:code, :qr, :name, :category_id, :brand_model, :serial, 'available', :location, :acquired_date, :note, :created_by)");
            $stmt->execute([
                'code' => $code, 'qr' => $qr, 'name' => $name, 'category_id' => $categoryId,
                'brand_model' => $brandModel, 'serial' => $serial, 'location' => $storageLocation,
                'acquired_date' => $acquiredDate, 'note' => $note, 'created_by' => $_SESSION['user']['id'],
            ]);
        }

        $imported++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างนำเข้าข้อมูล: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true, 'message' => "นำเข้าข้อมูลสำเร็จ {$imported} รายการ (ข้าม {$skipped} รายการที่ไม่มีชื่อ)"]);
