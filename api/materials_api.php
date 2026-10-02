<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listMaterials($pdo);
        break;
    case 'get':
        getMaterial($pdo);
        break;
    case 'create':
        requireAdminApi();
        createMaterial($pdo, $user);
        break;
    case 'update':
        requireAdminApi();
        updateMaterial($pdo);
        break;
    case 'delete':
        requireAdminApi();
        deleteMaterial($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function listMaterials(PDO $pdo): void
{
    $keyword = sanitizeString($_GET['keyword'] ?? '');
    $lowStockOnly = ($_GET['low_stock'] ?? '') === '1';
    $sql = "SELECT m.*, c.name AS category_name FROM materials m LEFT JOIN categories c ON c.id = m.category_id";
    $conditions = [];
    $params = [];
    if ($keyword !== '') {
        // Two distinct placeholders: PDO with ATTR_EMULATE_PREPARES=false (native
        // prepared statements) does not allow the same named parameter twice.
        $conditions[] = "(m.name LIKE :kw1 OR m.material_code LIKE :kw2)";
        $params['kw1'] = "%$keyword%";
        $params['kw2'] = "%$keyword%";
    }
    if ($lowStockOnly) {
        $conditions[] = "m.stock_qty <= m.min_stock";
    }
    if ($conditions) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY m.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getMaterial(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT m.*, c.name AS category_name FROM materials m LEFT JOIN categories c ON c.id = m.category_id WHERE m.id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลวัสดุ'], 404);
    }
    jsonResponse(['success' => true, 'data' => $row]);
}

function createMaterial(PDO $pdo, array $user): void
{
    $name = sanitizeString($_POST['name'] ?? '');
    $unit = sanitizeString($_POST['unit'] ?? 'ชิ้น');
    $unitCost = (float) ($_POST['unit_cost'] ?? 0);
    $stockQty = (int) ($_POST['stock_qty'] ?? 0);
    $minStock = (int) ($_POST['min_stock'] ?? 0);
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $note = sanitizeString($_POST['note'] ?? '');
    $category = sanitizeString($_POST['category'] ?? '');

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อวัสดุ'], 422);
    }

    if ($unitCost < 0) {
        jsonResponse(['success' => false, 'message' => 'ต้นทุน/หน่วยต้องไม่ติดลบ'], 422);
    }

    $code = generateNextCode($pdo, 'materials', 'material_code', 'MAT');
    $qr = generateQrPayload($code);
    // Category defaults to the first Thai consonant of the item name
    // (e.g. "แฟ้ม..." -> "ฟ") unless the admin typed one in themselves.
    $categoryId = resolveMaterialCategoryId($pdo, $name, $category);

    try {
        $imagePath = handleImageUpload('image', 'materials', $code);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $stmt = $pdo->prepare("INSERT INTO materials
        (material_code, qr_code, name, category_id, unit, unit_cost, stock_qty, min_stock, storage_location, image_path, note, created_by)
        VALUES (:code, :qr, :name, :category_id, :unit, :unit_cost, :stock_qty, :min_stock, :location, :image_path, :note, :created_by)");
    $stmt->execute([
        'code' => $code,
        'qr' => $qr,
        'name' => $name,
        'category_id' => $categoryId,
        'unit' => $unit,
        'unit_cost' => $unitCost,
        'stock_qty' => $stockQty,
        'min_stock' => $minStock,
        'location' => $location ?: null,
        'image_path' => $imagePath,
        'note' => $note ?: null,
        'created_by' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'เพิ่มวัสดุเรียบร้อยแล้ว', 'id' => $pdo->lastInsertId(), 'code' => $code]);
}

function updateMaterial(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = sanitizeString($_POST['name'] ?? '');
    $unit = sanitizeString($_POST['unit'] ?? 'ชิ้น');
    $unitCost = (float) ($_POST['unit_cost'] ?? 0);
    $minStock = (int) ($_POST['min_stock'] ?? 0);
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $note = sanitizeString($_POST['note'] ?? '');
    $category = sanitizeString($_POST['category'] ?? '');

    if (!$id || $name === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    if ($unitCost < 0) {
        jsonResponse(['success' => false, 'message' => 'ต้นทุน/หน่วยต้องไม่ติดลบ'], 422);
    }

    $stmt = $pdo->prepare('SELECT material_code, image_path FROM materials WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลวัสดุ'], 404);
    }

    try {
        $imagePath = handleImageUpload('image', 'materials', $existing['material_code']) ?? $existing['image_path'];
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $categoryId = resolveMaterialCategoryId($pdo, $name, $category);

    $stmt = $pdo->prepare("UPDATE materials SET name = :name, category_id = :category_id, unit = :unit,
        unit_cost = :unit_cost, min_stock = :min_stock, storage_location = :location, image_path = :image_path,
        note = :note WHERE id = :id");
    $stmt->execute([
        'name' => $name,
        'category_id' => $categoryId,
        'unit' => $unit,
        'unit_cost' => $unitCost,
        'min_stock' => $minStock,
        'location' => $location ?: null,
        'image_path' => $imagePath,
        'note' => $note ?: null,
        'id' => $id,
    ]);

    jsonResponse(['success' => true, 'message' => 'แก้ไขข้อมูลวัสดุเรียบร้อยแล้ว']);
}

function deleteMaterial(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    $stmt = $pdo->prepare('SELECT image_path FROM materials WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $imagePath = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM materials WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
        unlink(__DIR__ . '/../' . $imagePath);
    }

    jsonResponse(['success' => true, 'message' => 'ลบข้อมูลวัสดุเรียบร้อยแล้ว']);
}
