<?php
/**
 * Requisition slips (ใบเบิกวัสดุ) - a single slip groups several materials
 * (withdraw) and/or assets (borrow) scanned in one visit, matching the
 * office's paper form. Every line is still recorded into
 * material_transactions / asset_transactions as before (with a
 * requisition_id back-reference) so existing reports keep working.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireLoginApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        createRequisition($pdo, $user);
        break;
    case 'list':
        listRequisitions($pdo);
        break;
    case 'get':
        getRequisition($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'ไม่พบคำสั่งที่ร้องขอ'], 400);
}

function createRequisition(PDO $pdo, array $user): void
{
    $purpose = sanitizeString($_POST['purpose'] ?? '');
    $requesterName = sanitizeString($_POST['requester_name'] ?? '');
    $requesterPosition = sanitizeString($_POST['requester_position'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (!is_array($items) || count($items) === 0) {
        jsonResponse(['success' => false, 'message' => 'กรุณาสแกนหรือเพิ่มรายการอย่างน้อย 1 รายการ'], 422);
    }

    try {
        $pdo->beginTransaction();

        $requisitionNo = generateNextCode($pdo, 'requisitions', 'requisition_no', 'REQ');
        $insertReq = $pdo->prepare('INSERT INTO requisitions (requisition_no, purpose, requester_name, requester_position, created_by)
            VALUES (:no, :purpose, :requester_name, :requester_position, :created_by)');
        $insertReq->execute([
            'no' => $requisitionNo,
            'purpose' => $purpose ?: null,
            'requester_name' => $requesterName ?: null,
            'requester_position' => $requesterPosition ?: null,
            'created_by' => $user['id'],
        ]);
        $requisitionId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare('INSERT INTO requisition_items
            (requisition_id, item_type, material_id, asset_id, item_name, unit, quantity_requested, quantity_issued, note)
            VALUES (:requisition_id, :item_type, :material_id, :asset_id, :item_name, :unit, :qty_requested, :qty_issued, :note)');

        $insertMaterialTx = $pdo->prepare("INSERT INTO material_transactions
            (material_id, user_id, requisition_id, transaction_type, quantity, balance_after, note)
            VALUES (:material_id, :user_id, :requisition_id, 'withdraw', :quantity, :balance_after, :note)");

        $insertAssetTx = $pdo->prepare("INSERT INTO asset_transactions
            (asset_id, user_id, requisition_id, action, borrower_name, note)
            VALUES (:asset_id, :user_id, :requisition_id, 'borrow', :borrower_name, :note)");

        foreach ($items as $item) {
            $itemType = $item['item_type'] ?? '';
            $note = sanitizeString($item['note'] ?? '');

            if ($itemType === 'material') {
                $materialId = (int) ($item['id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);

                if (!$materialId || $quantity <= 0) {
                    throw new InvalidArgumentException('ข้อมูลวัสดุในรายการไม่ถูกต้อง');
                }

                $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $materialId]);
                $material = $stmt->fetch();

                if (!$material) {
                    throw new InvalidArgumentException('ไม่พบข้อมูลวัสดุรายการหนึ่งในใบเบิก');
                }
                if ($material['stock_qty'] < $quantity) {
                    throw new InvalidArgumentException("สต๊อก \"{$material['name']}\" คงเหลือไม่เพียงพอ (คงเหลือ {$material['stock_qty']} {$material['unit']})");
                }

                $newBalance = $material['stock_qty'] - $quantity;
                $pdo->prepare('UPDATE materials SET stock_qty = :qty WHERE id = :id')
                    ->execute(['qty' => $newBalance, 'id' => $materialId]);

                $insertMaterialTx->execute([
                    'material_id' => $materialId,
                    'user_id' => $user['id'],
                    'requisition_id' => $requisitionId,
                    'quantity' => $quantity,
                    'balance_after' => $newBalance,
                    'note' => $note ?: null,
                ]);

                $insertItem->execute([
                    'requisition_id' => $requisitionId,
                    'item_type' => 'material',
                    'material_id' => $materialId,
                    'asset_id' => null,
                    'item_name' => $material['name'],
                    'unit' => $material['unit'],
                    'qty_requested' => $quantity,
                    'qty_issued' => $quantity,
                    'note' => $note ?: null,
                ]);
            } elseif ($itemType === 'asset') {
                $assetId = (int) ($item['id'] ?? 0);
                if (!$assetId) {
                    throw new InvalidArgumentException('ข้อมูลครุภัณฑ์ในรายการไม่ถูกต้อง');
                }

                $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $assetId]);
                $asset = $stmt->fetch();

                if (!$asset) {
                    throw new InvalidArgumentException('ไม่พบข้อมูลครุภัณฑ์รายการหนึ่งในใบเบิก');
                }
                if ($asset['status'] !== 'available') {
                    throw new InvalidArgumentException("\"{$asset['name']}\" ไม่สามารถยืมได้ในขณะนี้ (สถานะ: {$asset['status']})");
                }

                $pdo->prepare("UPDATE assets SET status = 'borrowed' WHERE id = :id")->execute(['id' => $assetId]);

                $insertAssetTx->execute([
                    'asset_id' => $assetId,
                    'user_id' => $user['id'],
                    'requisition_id' => $requisitionId,
                    'borrower_name' => $requesterName ?: null,
                    'note' => $note ?: null,
                ]);

                $insertItem->execute([
                    'requisition_id' => $requisitionId,
                    'item_type' => 'asset',
                    'material_id' => null,
                    'asset_id' => $assetId,
                    'item_name' => $asset['name'],
                    'unit' => null,
                    'qty_requested' => 1,
                    'qty_issued' => 1,
                    'note' => $note ?: null,
                ]);
            } else {
                throw new InvalidArgumentException('ประเภทรายการไม่ถูกต้อง');
            }
        }

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "บันทึกใบเบิกเลขที่ {$requisitionNo} เรียบร้อยแล้ว",
            'requisition_id' => $requisitionId,
            'requisition_no' => $requisitionNo,
        ]);
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกใบเบิก'], 500);
    }
}

function listRequisitions(PDO $pdo): void
{
    $keyword = sanitizeString($_GET['keyword'] ?? '');
    $startDate = sanitizeString($_GET['start_date'] ?? '');
    $endDate = sanitizeString($_GET['end_date'] ?? '');

    $sql = "SELECT r.*, u.full_name AS created_by_name,
                (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = r.id) AS item_count
            FROM requisitions r
            JOIN users u ON u.id = r.created_by
            WHERE 1=1";
    $params = [];

    if ($keyword !== '') {
        $sql .= ' AND (r.requisition_no LIKE :kw1 OR r.requester_name LIKE :kw2 OR r.purpose LIKE :kw3)';
        $params['kw1'] = "%$keyword%";
        $params['kw2'] = "%$keyword%";
        $params['kw3'] = "%$keyword%";
    }
    if ($startDate !== '') {
        $sql .= ' AND r.created_at >= :start_date';
        $params['start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== '') {
        $sql .= ' AND r.created_at <= :end_date';
        $params['end_date'] = $endDate . ' 23:59:59';
    }

    $sql .= ' ORDER BY r.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getRequisition(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT r.*, u.full_name AS created_by_name FROM requisitions r
        JOIN users u ON u.id = r.created_by WHERE r.id = :id');
    $stmt->execute(['id' => $id]);
    $requisition = $stmt->fetch();

    if (!$requisition) {
        jsonResponse(['success' => false, 'message' => 'ไม่พบใบเบิก'], 404);
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM requisition_items WHERE requisition_id = :id ORDER BY id');
    $itemsStmt->execute(['id' => $id]);
    $requisition['items'] = $itemsStmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $requisition]);
}
