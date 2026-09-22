<?php
// Renders a single QR code image (PNG) for a given payload string.
// Uses the bundled phpqrcode library (libs/phpqrcode) - no Composer required.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$data = $_GET['data'] ?? '';
if ($data === '') {
    http_response_code(400);
    exit('missing data');
}

header('Content-Type: image/png');
QRcode::png($data, false, QR_ECLEVEL_L, 6, 2);
