<?php
/**
 * Self-service account management: lets the CURRENTLY LOGGED IN user (any
 * role) change their own password and set/change/remove their own PIN,
 * without needing admin access. Every action is scoped strictly to
 * $user['id'] from the session - never from client input - so a user can
 * only ever edit their own account here.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get':
        getAccount($pdo, $user);
        break;
    case 'change_password':
        changePassword($pdo, $user);
        break;
    case 'set_pin':
        setPin($pdo, $user);
        break;
    case 'remove_pin':
        removePin($pdo, $user);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function getAccount(PDO $pdo, array $user): void
{
    $stmt = $pdo->prepare('SELECT id, username, full_name, position, role,
        (pin_hash IS NOT NULL) AS has_pin FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetch()]);
}

function changePassword(PDO $pdo, array $user): void
{
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'], 422);
    }
    if (strlen($newPassword) < 6) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'], 422);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน'], 422);
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    $row = $stmt->fetch();

    if (!$row || $row['password_hash'] === null || !password_verify($currentPassword, $row['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
    }

    $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')
        ->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]);

    jsonResponse(['success' => true, 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว']);
}

function setPin(PDO $pdo, array $user): void
{
    $pin = sanitizeString($_POST['pin'] ?? '');
    $confirmPin = sanitizeString($_POST['confirm_pin'] ?? '');

    if (!preg_match('/^\d{6}$/', $pin)) {
        jsonResponse(['success' => false, 'message' => 'PIN ต้องเป็นตัวเลข 6 หลัก'], 422);
    }
    if ($pin !== $confirmPin) {
        jsonResponse(['success' => false, 'message' => 'PIN และยืนยัน PIN ไม่ตรงกัน'], 422);
    }
    if (findUserByPin($pdo, $pin, $user['id']) !== null) {
        jsonResponse(['success' => false, 'message' => 'PIN นี้มีผู้ใช้งานอยู่แล้ว กรุณาใช้ PIN อื่น'], 422);
    }

    $pdo->prepare('UPDATE users SET pin_hash = :pin_hash WHERE id = :id')
        ->execute(['pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'id' => $user['id']]);

    // Keep the session in sync so index.php's post-login PIN-setup check
    // (and anywhere else that reads has_pin) sees this immediately.
    $_SESSION['user']['has_pin'] = true;

    jsonResponse(['success' => true, 'message' => 'ตั้ง PIN เรียบร้อยแล้ว']);
}

function removePin(PDO $pdo, array $user): void
{
    $pdo->prepare('UPDATE users SET pin_hash = NULL WHERE id = :id')->execute(['id' => $user['id']]);
    $_SESSION['user']['has_pin'] = false;
    jsonResponse(['success' => true, 'message' => 'ลบ PIN เรียบร้อยแล้ว']);
}
