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
    $sql = "SELECT m.*, c.name AS category_name FROM materials m LEFT JOIN categories c ON c.id = m.category_id";
    $params = [];
    if ($keyword !== '') {
        $sql .= " WHERE m.name LIKE :kw OR m.material_code LIKE :kw";
        $params['kw'] = "%$keyword%";
    }
    $sql .= " ORDER BY m.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getMaterial(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id');
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
    $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $unit = sanitizeString($_POST['unit'] ?? 'ชิ้น');
    $stockQty = (int) ($_POST['stock_qty'] ?? 0);
    $minStock = (int) ($_POST['min_stock'] ?? 0);
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $note = sanitizeString($_POST['note'] ?? '');

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อวัสดุ'], 422);
    }

    $code = generateNextCode($pdo, 'materials', 'material_code', 'MAT');
    $qr = generateQrPayload($code);

    $stmt = $pdo->prepare("INSERT INTO materials
        (material_code, qr_code, name, category_id, unit, stock_qty, min_stock, storage_location, note, created_by)
        VALUES (:code, :qr, :name, :category_id, :unit, :stock_qty, :min_stock, :location, :note, :created_by)");
    $stmt->execute([
        'code' => $code,
        'qr' => $qr,
        'name' => $name,
        'category_id' => $categoryId,
        'unit' => $unit,
        'stock_qty' => $stockQty,
        'min_stock' => $minStock,
        'location' => $location ?: null,
        'note' => $note ?: null,
        'created_by' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'เพิ่มวัสดุเรียบร้อยแล้ว', 'id' => $pdo->lastInsertId(), 'code' => $code]);
}

function updateMaterial(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = sanitizeString($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $unit = sanitizeString($_POST['unit'] ?? 'ชิ้น');
    $minStock = (int) ($_POST['min_stock'] ?? 0);
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $note = sanitizeString($_POST['note'] ?? '');

    if (!$id || $name === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    $stmt = $pdo->prepare("UPDATE materials SET name = :name, category_id = :category_id, unit = :unit,
        min_stock = :min_stock, storage_location = :location, note = :note WHERE id = :id");
    $stmt->execute([
        'name' => $name,
        'category_id' => $categoryId,
        'unit' => $unit,
        'min_stock' => $minStock,
        'location' => $location ?: null,
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
    $stmt = $pdo->prepare('DELETE FROM materials WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'ลบข้อมูลวัสดุเรียบร้อยแล้ว']);
}
