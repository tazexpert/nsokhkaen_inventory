<?php

// Generate the next running code for materials/assets, e.g. MAT-0001, AST-0001
function generateNextCode(PDO $pdo, string $table, string $column, string $prefix): string
{
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '-%']);
    $last = $stmt->fetchColumn();

    $nextNumber = 1;
    if ($last) {
        $parts = explode('-', $last);
        $nextNumber = (int) end($parts) + 1;
    }

    return $prefix . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

// Unique code stored in the qr_code column and used as the lookup key
function generateQrPayload(string $code): string
{
    return $code . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

// Requisition numbers follow Thai government document convention:
// running number / Buddhist-era year, resetting back to 1 each new year
// (e.g. 1/2569, 2/2569, ... then 1/2570 the following year).
function generateRequisitionNo(PDO $pdo): string
{
    $thaiYear = (int) date('Y') + 543;
    $suffix = '/' . $thaiYear;

    $stmt = $pdo->prepare("SELECT requisition_no FROM requisitions
        WHERE requisition_no LIKE :suffix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['suffix' => '%' . $suffix]);
    $last = $stmt->fetchColumn();

    $nextNumber = 1;
    if ($last) {
        $nextNumber = (int) strtok($last, '/') + 1;
    }

    return $nextNumber . $suffix;
}

// First Thai consonant (ก-ฮ) in the name, skipping leading vowels (เ แ โ ใ ไ),
// digits, Latin letters, spaces, etc. e.g. "แฟ้มสันกว้าง" -> "ฟ",
// "โพสต์อิท..." -> "พ". Returns null if the name has no Thai consonant at all.
function thaiCategoryLetter(string $name): ?string
{
    foreach (mb_str_split($name, 1, 'UTF-8') as $char) {
        $code = mb_ord($char, 'UTF-8');
        if ($code !== false && $code >= 0x0E01 && $code <= 0x0E2E) {
            return $char;
        }
    }
    return null;
}

// Finds (or creates) the single-letter material category matching the
// item's name, returning its category_id - or null if the name has no
// Thai consonant to derive a letter from (e.g. an all-English name).
function autoMaterialCategoryId(PDO $pdo, string $name): ?int
{
    $letter = thaiCategoryLetter($name);
    if ($letter === null) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND item_type = 'material'");
    $stmt->execute(['name' => $letter]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }

    $insert = $pdo->prepare("INSERT INTO categories (name, item_type) VALUES (:name, 'material')");
    $insert->execute(['name' => $letter]);
    return (int) $pdo->lastInsertId();
}

// Saves an uploaded photo (field $fieldName in $_FILES) under
// uploads/$subfolder/$code.<ext>, replacing any previous image for the
// same $code regardless of its old extension. Returns the path to store in
// the DB (relative to the project root), or null if no file was uploaded.
// Throws InvalidArgumentException on an invalid file.
function handleImageUpload(string $fieldName, string $subfolder, string $code): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('เกิดข้อผิดพลาดระหว่างอัปโหลดรูปภาพ');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($_FILES[$fieldName]['size'] > $maxBytes) {
        throw new InvalidArgumentException('ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB');
    }

    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$fieldName]['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        throw new InvalidArgumentException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, WEBP หรือ GIF เท่านั้น');
    }
    $ext = $allowedMimes[$mime];

    $dir = __DIR__ . '/../uploads/' . $subfolder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // Remove any previous image for this item (possibly a different extension)
    foreach (glob($dir . '/' . $code . '.*') ?: [] as $existing) {
        unlink($existing);
    }

    $filename = $code . '.' . $ext;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dir . '/' . $filename)) {
        throw new InvalidArgumentException('ไม่สามารถบันทึกไฟล์รูปภาพได้');
    }

    return 'uploads/' . $subfolder . '/' . $filename;
}

// The actual content encoded into the printed QR image: a direct link to the
// scan page. Any phone camera app can open this - it does not need our own
// in-page scanner. If the user is not logged in, scan.php requires login
// first and returns here afterwards (see requireLogin() in includes/auth.php).
function buildQrScanUrl(string $qrCode): string
{
    return APP_URL . 'public/scan.php?qr=' . urlencode($qrCode);
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeString(?string $value): string
{
    return trim($value ?? '');
}
