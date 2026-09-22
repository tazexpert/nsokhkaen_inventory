<?php
// Renders a single QR code image (PNG) for a given payload string.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$data = $_GET['data'] ?? '';
if ($data === '') {
    http_response_code(400);
    exit('missing data');
}

$qrCode = new QrCode($data);
$qrCode->setSize(300);
$qrCode->setMargin(5);

$writer = new PngWriter();
$result = $writer->write($qrCode);

header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
