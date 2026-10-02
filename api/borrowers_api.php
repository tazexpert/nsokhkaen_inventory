<?php
/**
 * Borrowers (ผู้ยืม) directory - people who can borrow assets by typing a
 * 6-digit PIN on the scan page instead of needing a full system login.
 * PINs are hashed with password_hash(), same as user passwords.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        requireAdminApi();
        listBorrowers($pdo);
        break;
    case 'create':
        requireAdminApi();
        createBorrower($pdo);
        break;
    case 'update':
        requireAdminApi();
        updateBorrower($pdo);
        break;
    case 'delete':
        requireAdminApi();
        deleteBorrower($pdo);
        break;
    case 'verify_pin':
        // Used from the scan page by any logged-in staff member to identify
        // who they're handing an asset to - not admin-only.
        verifyPin($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function listBorrowers(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id, full_name, position, is_active, created_at FROM borrowers ORDER BY full_name');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function validatePinFormat(string $pin): void
{
    if (!preg_match('/^\d{6}$/', $pin)) {
        jsonResponse(['success' => false, 'message' => 'PIN ต้องเป็นตัวเลข 6 หลัก'], 422);
    }
}

function createBorrower(PDO $pdo): void
{
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $position = sanitizeString($_POST['position'] ?? '');
    $pin = sanitizeString($_POST['pin'] ?? '');

    if ($fullName === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุชื่อผู้ยืม'], 422);
    }
    validatePinFormat($pin);

    if (findBorrowerByPin($pdo, $pin) !== null) {
        jsonResponse(['success' => false, 'message' => 'PIN นี้มีผู้ใช้งานอยู่แล้ว กรุณาใช้ PIN อื่น'], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO borrowers (full_name, position, pin_hash) VALUES (:full_name, :position, :pin_hash)');
    $stmt->execute([
        'full_name' => $fullName,
        'position' => $position ?: null,
        'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
    ]);

    jsonResponse(['success' => true, 'message' => 'เพิ่มผู้ยืมเรียบร้อยแล้ว', 'id' => $pdo->lastInsertId()]);
}

function updateBorrower(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $fullName = sanitizeString($_POST['full_name'] ?? '');
    $position = sanitizeString($_POST['position'] ?? '');
    $pin = sanitizeString($_POST['pin'] ?? '');

    if (!$id || $fullName === '') {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    if ($pin !== '') {
        validatePinFormat($pin);
        if (findBorrowerByPin($pdo, $pin, $id) !== null) {
            jsonResponse(['success' => false, 'message' => 'PIN นี้มีผู้ใช้งานอยู่แล้ว กรุณาใช้ PIN อื่น'], 422);
        }

        $stmt = $pdo->prepare('UPDATE borrowers SET full_name = :full_name, position = :position, pin_hash = :pin_hash WHERE id = :id');
        $stmt->execute([
            'full_name' => $fullName,
            'position' => $position ?: null,
            'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE borrowers SET full_name = :full_name, position = :position WHERE id = :id');
        $stmt->execute([
            'full_name' => $fullName,
            'position' => $position ?: null,
            'id' => $id,
        ]);
    }

    jsonResponse(['success' => true, 'message' => 'แก้ไขข้อมูลผู้ยืมเรียบร้อยแล้ว']);
}

function deleteBorrower(PDO $pdo): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM borrowers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    jsonResponse(['success' => true, 'message' => 'ลบข้อมูลผู้ยืมเรียบร้อยแล้ว']);
}

function verifyPin(PDO $pdo): void
{
    $pin = sanitizeString($_POST['pin'] ?? $_GET['pin'] ?? '');
    validatePinFormat($pin);

    $borrower = findBorrowerByPin($pdo, $pin);
    if ($borrower === null) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบผู้ยืมที่ใช้ PIN นี้'], 404);
    }

    jsonResponse(['success' => true, 'data' => [
        'id' => (int) $borrower['id'],
        'full_name' => $borrower['full_name'],
        'position' => $borrower['position'],
    ]]);
}
