<?php
require_once __DIR__ . '/../config/config.php';

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['user']['role'] === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        // Remember the page the user was trying to reach (e.g. a QR code deep
        // link to scan.php?qr=...) so we can send them back there after login.
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: ' . BASE_URL . 'public/index.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (สำหรับผู้ดูแลระบบเท่านั้น)');
    }
}

function requireLoginApi(): array
{
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
        exit;
    }
    return $_SESSION['user'];
}

// The scan page (public/scan.php) doesn't require a full username+password
// login - identifying with a 6-digit PIN (see public/scan_login.php) logs
// the user in exactly the same way (sets $_SESSION['user']), so once
// verified they have full access everywhere their role allows (dashboard,
// ใบเบิก, etc.), not just the scan page - the only difference is which
// login screen a not-yet-logged-in visitor is sent to, and the PIN session
// is remembered for the rest of the visit including page loads triggered
// by scanning another sticker with the phone's own camera app.
function requireScanAccess(): void
{
    if (!isLoggedIn()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: ' . BASE_URL . 'public/scan_login.php');
        exit;
    }
}

function requireAdminApi(): array
{
    $user = requireLoginApi();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ทำรายการนี้']);
        exit;
    }
    return $user;
}
