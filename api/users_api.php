<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = requireAdminApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listUsers($pdo);
        break;
    case 'get':
        getUser($pdo);
        break;
    case 'create':
        createUser($pdo);
        break;
    case 'update':
        updateUser($pdo);
        break;
    case 'toggle_active':
        toggleActive($pdo, $currentUser);
        break;
    case 'delete':
        deleteUser($pdo, $currentUser);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function listUsers(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id, username, full_name, position, role, is_active,
        (pin_hash IS NOT NULL) AS has_pin, created_at FROM users ORDER BY id');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getUser(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, username, full_name, position, role, is_active,
        (pin_hash IS NOT NULL) AS has_pin FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน'], 404);
    }
    jsonResponse(['success' => true, 'data' => $row]);
}

// A user needs a username+password pair (both or neither), a PIN, or
// both - never none of the above, or they'd have no way to be identified
// by the system at all.
function validateLoginAndPin(string $username, string $password, string $pin): void
{
    if (($username !== '') !== ($password !== '')) {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอกทั้งชื่อผู้ใช้และรหัสผ่านคู่กัน หรือเว้นว่างทั้งคู่'], 422);
    }
    if ($password !== '' && strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'], 422);
    }
    if ($pin !== '' && !preg_match('/^\d{6}$/', $pin)) {
        jsonResponse(['success' => false, 'message' => 'PIN ต้องเป็นตัวเลข 6 หลัก'], 422);
    }
}

function createUser(PDO $pdo): void
{
    $username = sanitizeString($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $position = sanitizeString($_POST['position'] ?? '');
    $role = sanitizeString($_POST['role'] ?? 'staff');
    $pin = sanitizeString($_POST['pin'] ?? '');

    if ($fullName === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อ-นามสกุล'], 422);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        jsonResponse(['success' => false, 'message' => 'สิทธิ์การใช้งานไม่ถูกต้อง'], 422);
    }
    validateLoginAndPin($username, $password, $pin);
    if ($username === '' && $pin === '') {
        jsonResponse(['success' => false, 'message' => 'ต้องตั้งชื่อผู้ใช้+รหัสผ่าน (สำหรับเข้าสู่ระบบ) หรือ PIN (สำหรับเบิกวัสดุ) อย่างน้อยหนึ่งอย่าง'], 422);
    }
    if ($pin !== '' && findUserByPin($pdo, $pin) !== null) {
        jsonResponse(['success' => false, 'message' => 'PIN นี้มีผู้ใช้งานอยู่แล้ว กรุณาใช้ PIN อื่น'], 422);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, position, pin_hash, role)
            VALUES (:username, :password_hash, :full_name, :position, :pin_hash, :role)');
        $stmt->execute([
            'username' => $username ?: null,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'full_name' => $fullName,
            'position' => $position ?: null,
            'pin_hash' => $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null,
            'role' => $role,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(['success' => false, 'message' => 'มีชื่อผู้ใช้นี้อยู่แล้ว'], 422);
        }
        throw $e;
    }

    jsonResponse(['success' => true, 'message' => 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว', 'id' => $pdo->lastInsertId()]);
}

function updateUser(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $username = sanitizeString($_POST['username'] ?? '');
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $position = sanitizeString($_POST['position'] ?? '');
    $role = sanitizeString($_POST['role'] ?? 'staff');
    $password = $_POST['password'] ?? '';
    $pin = sanitizeString($_POST['pin'] ?? '');
    $removePin = ($_POST['remove_pin'] ?? '') === '1';

    if (!$id || $fullName === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        jsonResponse(['success' => false, 'message' => 'สิทธิ์การใช้งานไม่ถูกต้อง'], 422);
    }

    $currentStmt = $pdo->prepare('SELECT username, password_hash FROM users WHERE id = :id');
    $currentStmt->execute(['id' => $id]);
    $current = $currentStmt->fetch();
    if (!$current) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน'], 404);
    }
    // A password is only required when (re)setting a username for a user who
    // doesn't already have login credentials - otherwise their existing
    // password hash is simply kept as-is.
    if ($username !== '' && $current['password_hash'] === null && $password === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอกรหัสผ่านสำหรับการเข้าสู่ระบบ'], 422);
    }
    if ($password !== '' && $username === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้คู่กับรหัสผ่านใหม่'], 422);
    }
    if ($password !== '' && strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'], 422);
    }
    if ($pin !== '') {
        if (!preg_match('/^\d{6}$/', $pin)) {
            jsonResponse(['success' => false, 'message' => 'PIN ต้องเป็นตัวเลข 6 หลัก'], 422);
        }
        if (findUserByPin($pdo, $pin, $id) !== null) {
            jsonResponse(['success' => false, 'message' => 'PIN นี้มีผู้ใช้งานอยู่แล้ว กรุณาใช้ PIN อื่น'], 422);
        }
    }

    $sql = 'UPDATE users SET full_name = :full_name, position = :position, role = :role';
    $params = ['full_name' => $fullName, 'position' => $position ?: null, 'role' => $role, 'id' => $id];

    if ($username !== '') {
        $sql .= ', username = :username';
        $params['username'] = $username;
    }
    if ($password !== '') {
        $sql .= ', password_hash = :password_hash';
        $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($pin !== '') {
        $sql .= ', pin_hash = :pin_hash';
        $params['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
    } elseif ($removePin) {
        $sql .= ', pin_hash = NULL';
    }

    $sql .= ' WHERE id = :id';

    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(['success' => false, 'message' => 'มีชื่อผู้ใช้นี้อยู่แล้ว'], 422);
        }
        throw $e;
    }

    jsonResponse(['success' => true, 'message' => 'แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว']);
}

function toggleActive(PDO $pdo, array $currentUser): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }
    if ($id === (int) $currentUser['id']) {
        jsonResponse(['success' => false, 'message' => 'ไม่สามารถปิดการใช้งานบัญชีของตนเองได้'], 422);
    }

    $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'เปลี่ยนสถานะผู้ใช้งานเรียบร้อยแล้ว']);
}

function deleteUser(PDO $pdo, array $currentUser): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }
    if ($id === (int) $currentUser['id']) {
        jsonResponse(['success' => false, 'message' => 'ไม่สามารถลบบัญชีของตนเองได้'], 422);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        // FK RESTRICT on material_transactions/asset_transactions.user_id
        if ($e->getCode() === '23000') {
            jsonResponse(['success' => false, 'message' => 'ไม่สามารถลบผู้ใช้งานนี้ได้เนื่องจากมีประวัติการทำรายการอยู่ กรุณาใช้ "ปิดการใช้งาน" แทน'], 422);
        }
        throw $e;
    }

    jsonResponse(['success' => true, 'message' => 'ลบผู้ใช้งานเรียบร้อยแล้ว']);
}
