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
    $stmt = $pdo->query('SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getUser(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน'], 404);
    }
    jsonResponse(['success' => true, 'data' => $row]);
}

function createUser(PDO $pdo): void
{
    $username = sanitizeString($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $role = sanitizeString($_POST['role'] ?? 'staff');

    if ($username === '' || $fullName === '' || strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน (รหัสผ่านอย่างน้อย 6 ตัวอักษร)'], 422);
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        jsonResponse(['success' => false, 'message' => 'สิทธิ์การใช้งานไม่ถูกต้อง'], 422);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (:username, :password_hash, :full_name, :role)');
        $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName,
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
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $role = sanitizeString($_POST['role'] ?? 'staff');
    $password = $_POST['password'] ?? '';

    if (!$id || $fullName === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        jsonResponse(['success' => false, 'message' => 'สิทธิ์การใช้งานไม่ถูกต้อง'], 422);
    }

    if ($password !== '' && strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'], 422);
    }

    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, role = :role, password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            'full_name' => $fullName,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, role = :role WHERE id = :id');
        $stmt->execute(['full_name' => $fullName, 'role' => $role, 'id' => $id]);
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
