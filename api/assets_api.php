<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listAssets($pdo);
        break;
    case 'get':
        getAsset($pdo);
        break;
    case 'create':
        requireAdminApi();
        createAsset($pdo, $user);
        break;
    case 'update':
        requireAdminApi();
        updateAsset($pdo);
        break;
    case 'delete':
        requireAdminApi();
        deleteAsset($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function listAssets(PDO $pdo): void
{
    $keyword = sanitizeString($_GET['keyword'] ?? '');
    $status = sanitizeString($_GET['status'] ?? '');
    $sql = "SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id";
    $conditions = [];
    $params = [];
    if ($keyword !== '') {
        // Two distinct placeholders: PDO with ATTR_EMULATE_PREPARES=false (native
        // prepared statements) does not allow the same named parameter twice.
        $conditions[] = "(a.name LIKE :kw1 OR a.asset_code LIKE :kw2)";
        $params['kw1'] = "%$keyword%";
        $params['kw2'] = "%$keyword%";
    }
    if ($status !== '' && in_array($status, ['available', 'borrowed', 'maintenance', 'disposed'], true)) {
        $conditions[] = "a.status = :status";
        $params['status'] = $status;
    }
    if ($conditions) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY a.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getAsset(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลครุภัณฑ์'], 404);
    }
    jsonResponse(['success' => true, 'data' => $row]);
}

function createAsset(PDO $pdo, array $user): void
{
    $name = sanitizeString($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $brandModel = sanitizeString($_POST['brand_model'] ?? '');
    $serial = sanitizeString($_POST['serial_number'] ?? '');
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $acquiredDate = sanitizeString($_POST['acquired_date'] ?? '') ?: null;
    $note = sanitizeString($_POST['note'] ?? '');

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อครุภัณฑ์'], 422);
    }

    $code = generateNextCode($pdo, 'assets', 'asset_code', 'AST');
    $qr = generateQrPayload($code);

    try {
        $imagePath = handleImageUpload('image', 'assets', $code);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $stmt = $pdo->prepare("INSERT INTO assets
        (asset_code, qr_code, name, category_id, brand_model, serial_number, status, storage_location, acquired_date, image_path, note, created_by)
        VALUES (:code, :qr, :name, :category_id, :brand_model, :serial, 'available', :location, :acquired_date, :image_path, :note, :created_by)");
    $stmt->execute([
        'code' => $code,
        'qr' => $qr,
        'name' => $name,
        'category_id' => $categoryId,
        'brand_model' => $brandModel ?: null,
        'serial' => $serial ?: null,
        'location' => $location ?: null,
        'acquired_date' => $acquiredDate,
        'image_path' => $imagePath,
        'note' => $note ?: null,
        'created_by' => $user['id'],
    ]);

    jsonResponse(['success' => true, 'message' => 'เพิ่มครุภัณฑ์เรียบร้อยแล้ว', 'id' => $pdo->lastInsertId(), 'code' => $code]);
}

function updateAsset(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = sanitizeString($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $brandModel = sanitizeString($_POST['brand_model'] ?? '');
    $serial = sanitizeString($_POST['serial_number'] ?? '');
    $location = sanitizeString($_POST['storage_location'] ?? '');
    $acquiredDate = sanitizeString($_POST['acquired_date'] ?? '') ?: null;
    $note = sanitizeString($_POST['note'] ?? '');
    $status = sanitizeString($_POST['status'] ?? 'available');

    if (!$id || $name === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    if (!in_array($status, ['available', 'borrowed', 'maintenance', 'disposed'], true)) {
        jsonResponse(['success' => false, 'message' => 'สถานะไม่ถูกต้อง'], 422);
    }

    $stmt = $pdo->prepare('SELECT asset_code, image_path FROM assets WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลครุภัณฑ์'], 404);
    }

    try {
        $imagePath = handleImageUpload('image', 'assets', $existing['asset_code']) ?? $existing['image_path'];
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $stmt = $pdo->prepare("UPDATE assets SET name = :name, category_id = :category_id, brand_model = :brand_model,
        serial_number = :serial, storage_location = :location, acquired_date = :acquired_date, image_path = :image_path,
        note = :note, status = :status WHERE id = :id");
    $stmt->execute([
        'name' => $name,
        'category_id' => $categoryId,
        'brand_model' => $brandModel ?: null,
        'serial' => $serial ?: null,
        'location' => $location ?: null,
        'acquired_date' => $acquiredDate,
        'image_path' => $imagePath,
        'note' => $note ?: null,
        'status' => $status,
        'id' => $id,
    ]);

    jsonResponse(['success' => true, 'message' => 'แก้ไขข้อมูลครุภัณฑ์เรียบร้อยแล้ว']);
}

function deleteAsset(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    $stmt = $pdo->prepare('SELECT image_path FROM assets WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $imagePath = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM assets WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
        unlink(__DIR__ . '/../' . $imagePath);
    }

    jsonResponse(['success' => true, 'message' => 'ลบข้อมูลครุภัณฑ์เรียบร้อยแล้ว']);
}
