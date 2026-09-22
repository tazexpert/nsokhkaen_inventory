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
