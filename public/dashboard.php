<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$materialCount = $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
$lowStockCount = $pdo->query('SELECT COUNT(*) FROM materials WHERE stock_qty <= min_stock')->fetchColumn();
$assetCount = $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn();
$borrowedCount = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'borrowed'")->fetchColumn();

$recentMaterialTx = $pdo->query("
    SELECT mt.*, m.name AS material_name, u.full_name
    FROM material_transactions mt
    JOIN materials m ON m.id = mt.material_id
    JOIN users u ON u.id = mt.user_id
    ORDER BY mt.created_at DESC LIMIT 5
")->fetchAll();

$recentAssetTx = $pdo->query("
    SELECT at.*, a.name AS asset_name, u.full_name
    FROM asset_transactions at
    JOIN assets a ON a.id = at.asset_id
    JOIN users u ON u.id = at.user_id
    ORDER BY at.created_at DESC LIMIT 5
")->fetchAll();
?>

<h3 class="mb-4">แดชบอร์ด</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int)$materialCount ?></div>
                <div>รายการวัสดุสิ้นเปลือง</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int)$lowStockCount ?></div>
                <div>วัสดุใกล้หมดสต๊อก</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-success h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int)$assetCount ?></div>
                <div>รายการครุภัณฑ์</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-danger h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int)$borrowedCount ?></div>
                <div>ครุภัณฑ์ที่ถูกยืมอยู่</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">การเบิกวัสดุล่าสุด</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>รายการ</th><th>จำนวน</th><th>ผู้ทำรายการ</th><th>เวลา</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentMaterialTx as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['material_name']) ?></td>
                            <td><?= (int)$tx['quantity'] ?></td>
                            <td><?= htmlspecialchars($tx['full_name']) ?></td>
                            <td><?= htmlspecialchars($tx['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentMaterialTx): ?>
                        <tr><td colspan="4" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">การยืม/คืนครุภัณฑ์ล่าสุด</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>รายการ</th><th>สถานะ</th><th>ผู้ทำรายการ</th><th>เวลา</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentAssetTx as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['asset_name']) ?></td>
                            <td><?= $tx['action'] === 'borrow' ? 'ยืม' : 'คืน' ?></td>
                            <td><?= htmlspecialchars($tx['full_name']) ?></td>
                            <td><?= htmlspecialchars($tx['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentAssetTx): ?>
                        <tr><td colspan="4" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
