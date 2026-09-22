<?php
/**
 * Backend for the QR Code scan workflow.
 * Handles: lookup (identify material/asset by QR), withdraw (material),
 * borrow/return (asset). Every transaction records the acting user and timestamp.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'lookup':
        lookupQrCode($pdo);
        break;
    case 'withdraw':
        withdrawMaterial($pdo, $user);
        break;
    case 'borrow':
        borrowAsset($pdo, $user);
        break;
    case 'return':
        returnAsset($pdo, $user);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

/**
 * Step 1: identify whether the scanned QR code belongs to a material or an asset.
 */
function lookupQrCode(PDO $pdo): void
{
    $qr = sanitizeString($_GET['qr_code'] ?? $_POST['qr_code'] ?? '');
    if ($qr === '') {
        jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูล QR Code'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM materials WHERE qr_code = :qr');
    $stmt->execute(['qr' => $qr]);
    $material = $stmt->fetch();

    if ($material) {
        jsonResponse(['success' => true, 'item_type' => 'material', 'data' => $material]);
    }

    $stmt = $pdo->prepare('SELECT * FROM assets WHERE qr_code = :qr');
    $stmt->execute(['qr' => $qr]);
    $asset = $stmt->fetch();

    if ($asset) {
        jsonResponse(['success' => true, 'item_type' => 'asset', 'data' => $asset]);
    }

    jsonResponse(['success' => false, 'message' => 'ไม่พบรายการที่ตรงกับ QR Code นี้ในระบบ'], 404);
}

/**
 * Step 2 (material): withdraw a quantity from stock and record the transaction.
 * Uses SELECT ... FOR UPDATE inside a DB transaction to avoid race conditions
 * when multiple staff scan the same item concurrently.
 */
function withdrawMaterial(PDO $pdo, array $user): void
{
    $materialId = (int) ($_POST['material_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $note = sanitizeString($_POST['note'] ?? '');

    if (!$materialId || $quantity <= 0) {
        jsonResponse(['success' => false, 'message' => 'กรุณาระบุจำนวนที่ต้องการเบิกให้ถูกต้อง'], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $materialId]);
        $material = $stmt->fetch();

        if (!$material) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลวัสดุ'], 404);
        }

        if ($material['stock_qty'] < $quantity) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => "สต๊อกคงเหลือไม่เพียงพอ (คงเหลือ {$material['stock_qty']} {$material['unit']})"], 422);
        }

        $newBalance = $material['stock_qty'] - $quantity;

        $update = $pdo->prepare('UPDATE materials SET stock_qty = :qty WHERE id = :id');
        $update->execute(['qty' => $newBalance, 'id' => $materialId]);

        $insert = $pdo->prepare("INSERT INTO material_transactions
            (material_id, user_id, transaction_type, quantity, balance_after, note)
            VALUES (:material_id, :user_id, 'withdraw', :quantity, :balance_after, :note)");
        $insert->execute([
            'material_id' => $materialId,
            'user_id' => $user['id'],
            'quantity' => $quantity,
            'balance_after' => $newBalance,
            'note' => $note ?: null,
        ]);

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "เบิก {$material['name']} จำนวน {$quantity} {$material['unit']} เรียบร้อยแล้ว",
            'balance_after' => $newBalance,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], 500);
    }
}

/**
 * Step 2 (asset): borrow an available asset.
 */
function borrowAsset(PDO $pdo, array $user): void
{
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $borrowerName = sanitizeString($_POST['borrower_name'] ?? '');
    $note = sanitizeString($_POST['note'] ?? '');

    if (!$assetId) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $assetId]);
        $asset = $stmt->fetch();

        if (!$asset) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลครุภัณฑ์'], 404);
        }

        if ($asset['status'] !== 'available') {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'ครุภัณฑ์รายการนี้ไม่สามารถยืมได้ในขณะนี้ (สถานะ: ' . $asset['status'] . ')'], 422);
        }

        $update = $pdo->prepare("UPDATE assets SET status = 'borrowed' WHERE id = :id");
        $update->execute(['id' => $assetId]);

        $insert = $pdo->prepare("INSERT INTO asset_transactions
            (asset_id, user_id, action, borrower_name, note)
            VALUES (:asset_id, :user_id, 'borrow', :borrower_name, :note)");
        $insert->execute([
            'asset_id' => $assetId,
            'user_id' => $user['id'],
            'borrower_name' => $borrowerName ?: null,
            'note' => $note ?: null,
        ]);

        $pdo->commit();

        jsonResponse(['success' => true, 'message' => "บันทึกการยืม {$asset['name']} เรียบร้อยแล้ว"]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], 500);
    }
}

/**
 * Step 2 (asset): return a borrowed asset.
 */
function returnAsset(PDO $pdo, array $user): void
{
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $note = sanitizeString($_POST['note'] ?? '');

    if (!$assetId) {
        jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $assetId]);
        $asset = $stmt->fetch();

        if (!$asset) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูลครุภัณฑ์'], 404);
        }

        if ($asset['status'] !== 'borrowed') {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'ครุภัณฑ์รายการนี้ไม่ได้อยู่ในสถานะถูกยืม'], 422);
        }

        $update = $pdo->prepare("UPDATE assets SET status = 'available' WHERE id = :id");
        $update->execute(['id' => $assetId]);

        $insert = $pdo->prepare("INSERT INTO asset_transactions
            (asset_id, user_id, action, note)
            VALUES (:asset_id, :user_id, 'return', :note)");
        $insert->execute([
            'asset_id' => $assetId,
            'user_id' => $user['id'],
            'note' => $note ?: null,
        ]);

        $pdo->commit();

        jsonResponse(['success' => true, 'message' => "บันทึกการคืน {$asset['name']} เรียบร้อยแล้ว"]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], 500);
    }
}
