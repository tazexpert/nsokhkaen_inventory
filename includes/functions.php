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

// Finds (or creates) a category by its exact name, scoped to $itemType
// ('material' or 'asset'), returning its id.
function findOrCreateCategory(PDO $pdo, string $name, string $itemType): int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name AND item_type = :item_type');
    $stmt->execute(['name' => $name, 'item_type' => $itemType]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }

    $insert = $pdo->prepare('INSERT INTO categories (name, item_type) VALUES (:name, :item_type)');
    $insert->execute(['name' => $name, 'item_type' => $itemType]);
    return (int) $pdo->lastInsertId();
}

// Resolves the category_id to save for a material: $manualCategory (admin
// typed it in directly) wins when given; otherwise falls back to the
// auto-derived single-Thai-letter category from the item name. Returns
// null only when there's no manual value AND the name has no Thai
// consonant to derive one from (e.g. an all-English name).
function resolveMaterialCategoryId(PDO $pdo, string $name, string $manualCategory): ?int
{
    $manualCategory = trim($manualCategory);
    if ($manualCategory !== '') {
        return findOrCreateCategory($pdo, $manualCategory, 'material');
    }

    $letter = thaiCategoryLetter($name);
    if ($letter === null) {
        return null;
    }

    return findOrCreateCategory($pdo, $letter, 'material');
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

// Borrower PINs (ผู้ยืม) identify someone borrowing an asset without
// requiring them to have a full system login. Stored hashed exactly like
// user passwords; since each hash is independently salted, uniqueness of
// the plaintext PIN has to be checked by trying every active borrower's
// hash rather than by a database constraint.

// Finds the active borrower whose PIN matches, or null if none does.
// Used both to verify a PIN typed on the scan page and to enforce
// PIN uniqueness when creating/editing a borrower.
function findBorrowerByPin(PDO $pdo, string $pin, ?int $excludeId = null): ?array
{
    $sql = 'SELECT * FROM borrowers WHERE is_active = 1';
    $params = [];
    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params['exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $borrower) {
        if (password_verify($pin, $borrower['pin_hash'])) {
            return $borrower;
        }
    }

    return null;
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

// Cache-busting query string for a static asset (CSS/JS), based on its
// last-modified time, so browsers fetch the new version immediately after
// a deploy instead of serving a stale cached copy indefinitely.
function assetVersion(string $relativePath): string
{
    $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $mtime = file_exists($fullPath) ? filemtime($fullPath) : time();
    return '?v=' . $mtime;
}
