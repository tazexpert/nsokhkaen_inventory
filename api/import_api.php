<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminApi();
$pdo = getDbConnection();

use Shuchkin\SimpleXLSX;

$type = $_POST['type'] ?? '';
if (!in_array($type, ['material', 'asset'], true)) {
    jsonResponse(['success' => false, 'message' => 'ประเภทข้อมูลไม่ถูกต้อง'], 422);
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า'], 422);
}

$xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name']);
if (!$xlsx) {
    jsonResponse(['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์ Excel ได้: ' . SimpleXLSX::parseError()], 422);
}
$rows = $xlsx->rows();

if (count($rows) < 2) {
    jsonResponse(['success' => false, 'message' => 'ไฟล์ไม่มีข้อมูล'], 422);
}

/**
 * Two supported layouts for materials:
 *  1. Our own simple template (import_template.php): one header row with
 *     English column keys (name, unit, unit_cost, stock_qty, ...).
 *  2. The office's own "ใบสืบราคา/รายละเอียดพัสดุ" form: a multi-row merged
 *     header (ลำดับที่ / รายละเอียดของพัสดุ / ราคาที่ได้มาจากการสืบราคา(หน่วยละ) /
 *     จำนวน(หน่วย) / จำนวนเงิน), with each item row starting with its running
 *     number in column A. Detected by that running-number column instead of
 *     by header text, since the header spans several merged rows.
 */
function extractMaterialRecords(array $rows): array
{
    $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
    if (in_array('name', $header, true)) {
        $records = [];
        foreach (array_slice($rows, 1) as $row) {
            $record = array_combine($header, array_pad($row, count($header), null));
            if (trim((string) ($record['name'] ?? '')) === '') {
                continue;
            }
            $records[] = [
                'name' => trim((string) $record['name']),
                'unit' => trim((string) ($record['unit'] ?? 'ชิ้น')) ?: 'ชิ้น',
                'unit_cost' => (float) ($record['unit_cost'] ?? 0),
                'stock_qty' => (int) ($record['stock_qty'] ?? 0),
                'min_stock' => (int) ($record['min_stock'] ?? 0),
                'storage_location' => trim((string) ($record['storage_location'] ?? '')) ?: null,
                'note' => trim((string) ($record['note'] ?? '')) ?: null,
            ];
        }
        return $records;
    }

    // Office form: column A = running number, B = item name, D = unit price,
    // E = quantity being purchased/received into stock.
    $records = [];
    foreach ($rows as $row) {
        $no = $row[0] ?? null;
        $name = trim((string) ($row[1] ?? ''));
        if ($name === '' || !is_numeric($no) || (int) $no <= 0) {
            continue;
        }
        $records[] = [
            'name' => $name,
            'unit' => 'ชิ้น',
            'unit_cost' => (float) ($row[3] ?? 0),
            'stock_qty' => (int) ($row[4] ?? 0),
            'min_stock' => 0,
            'storage_location' => null,
            'note' => null,
        ];
    }
    return $records;
}

$imported = 0;
$skipped = 0;

$pdo->beginTransaction();
try {
    if ($type === 'material') {
        $records = extractMaterialRecords($rows);

        foreach ($records as $record) {
            $code = generateNextCode($pdo, 'materials', 'material_code', 'MAT');
            $qr = generateQrPayload($code);
            $categoryId = resolveMaterialCategoryId($pdo, $record['name'], '');

            $stmt = $pdo->prepare("INSERT INTO materials
                (material_code, qr_code, name, category_id, unit, unit_cost, stock_qty, min_stock, storage_location, note, created_by)
                VALUES (:code, :qr, :name, :category_id, :unit, :unit_cost, :stock_qty, :min_stock, :location, :note, :created_by)");
            $stmt->execute([
                'code' => $code, 'qr' => $qr, 'name' => $record['name'], 'category_id' => $categoryId,
                'unit' => $record['unit'], 'unit_cost' => $record['unit_cost'], 'stock_qty' => $record['stock_qty'],
                'min_stock' => $record['min_stock'], 'location' => $record['storage_location'], 'note' => $record['note'],
                'created_by' => $_SESSION['user']['id'],
            ]);
            $imported++;
        }
    } else {
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
        $dataRows = array_slice($rows, 1);

        foreach ($dataRows as $row) {
            $record = array_combine($header, array_pad($row, count($header), null));
            $name = trim((string) ($record['name'] ?? ''));

            if ($name === '') {
                $skipped++;
                continue;
            }

            $categoryId = !empty($record['category_id']) ? (int) $record['category_id'] : null;
            $storageLocation = trim((string) ($record['storage_location'] ?? '')) ?: null;
            $note = trim((string) ($record['note'] ?? '')) ?: null;

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
            $imported++;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างนำเข้าข้อมูล: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true, 'message' => "นำเข้าข้อมูลสำเร็จ {$imported} รายการ (ข้าม {$skipped} รายการที่ไม่มีชื่อ)"]);
