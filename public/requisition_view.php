<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT r.*, u.full_name AS created_by_name FROM requisitions r
    JOIN users u ON u.id = r.created_by WHERE r.id = :id');
$stmt->execute(['id' => $id]);
$requisition = $stmt->fetch();

if (!$requisition) {
    http_response_code(404);
    echo '<div class="alert alert-danger">ไม่พบใบเบิกที่ร้องขอ</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$itemsStmt = $pdo->prepare('SELECT * FROM requisition_items WHERE requisition_id = :id ORDER BY id');
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>ใบเบิก เลขที่ <?= htmlspecialchars($requisition['requisition_no']) ?></h3>
    <div>
        <a href="<?= BASE_URL ?>public/requisitions.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับไปหน้ารายการใบเบิก</a>
        <a href="<?= BASE_URL ?>public/requisition_print.php?id=<?= (int) $requisition['id'] ?>" target="_blank" class="btn btn-primary"><i class="bi bi-printer"></i> พิมพ์ใบเบิก</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">เลขที่ใบเบิก</div>
                <div class="fw-bold"><?= htmlspecialchars($requisition['requisition_no']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">วันที่</div>
                <div><?= htmlspecialchars($requisition['created_at']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">ผู้เบิก</div>
                <div><?= htmlspecialchars($requisition['requester_name'] ?: '-') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">ตำแหน่ง</div>
                <div><?= htmlspecialchars($requisition['requester_position'] ?: '-') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">ใช้งานเพื่อ</div>
                <div><?= htmlspecialchars($requisition['purpose'] ?: '-') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">ผู้บันทึก (ผู้จ่าย)</div>
                <div><?= htmlspecialchars($requisition['created_by_name']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
        <tr>
            <th>ลำดับที่</th><th>รายการ</th><th>ประเภท</th><th>หน่วยนับ</th><th>จำนวนเบิก</th><th>จำนวนจ่าย</th><th>หมายเหตุ</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= $item['item_type'] === 'asset' ? '<span class="badge bg-success">ครุภัณฑ์</span>' : '<span class="badge bg-info">วัสดุ</span>' ?></td>
                <td><?= htmlspecialchars($item['unit'] ?? '-') ?></td>
                <td><?= (int) $item['quantity_requested'] ?></td>
                <td><?= (int) $item['quantity_issued'] ?></td>
                <td><?= htmlspecialchars($item['note'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="7" class="text-center text-muted">ไม่มีรายการ</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
