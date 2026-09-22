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

// QR payload is simply the unique code; the scanner reads it back as plain text
function generateQrPayload(string $code): string
{
    return $code . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
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
