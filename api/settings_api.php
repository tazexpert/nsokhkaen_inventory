<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get':
        getSettings($pdo);
        break;
    case 'update':
        updateSettings($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function getSettings(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT `key`, `value` FROM settings');
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }
    $settings['base_url'] = BASE_URL; // derived from app_url, shown for reference only
    jsonResponse(['success' => true, 'data' => $settings]);
}

function updateSettings(PDO $pdo): void
{
    $appName = sanitizeString($_POST['app_name'] ?? '');
    $appUrl = sanitizeString($_POST['app_url'] ?? '');

    if ($appName === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อระบบ'], 422);
    }

    if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) {
        jsonResponse(['success' => false, 'message' => 'รูปแบบ URL ไม่ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)'], 422);
    }

    $appUrl = rtrim($appUrl, '/') . '/';

    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE `value` = :value2');
    $stmt->execute(['key' => 'app_name', 'value' => $appName, 'value2' => $appName]);
    $stmt->execute(['key' => 'app_url', 'value' => $appUrl, 'value2' => $appUrl]);

    jsonResponse(['success' => true, 'message' => 'บันทึกค่าระบบเรียบร้อยแล้ว กรุณารีเฟรชหน้าเว็บเพื่อให้มีผล']);
}
