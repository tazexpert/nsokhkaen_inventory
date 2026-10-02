<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$materialCount = $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
$lowStockCount = $pdo->query('SELECT COUNT(*) FROM materials WHERE stock_qty <= min_stock')->fetchColumn();
$assetCount = $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn();
$borrowedCount = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'borrowed'")->fetchColumn();

$recentRequisitions = $pdo->query("
    SELECT r.*, u.full_name AS created_by_name,
        (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = r.id) AS item_count
    FROM requisitions r
    JOIN users u ON u.id = r.created_by
    ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

$recentReturns = $pdo->query("
    SELECT at.*, a.name AS asset_name, u.full_name
    FROM asset_transactions at
    JOIN assets a ON a.id = at.asset_id
    JOIN users u ON u.id = at.user_id
    WHERE at.action = 'return'
    ORDER BY at.created_at DESC LIMIT 5
")->fetchAll();

// Each stat box links to its detail page - only for admin, since staff don't
// have access to materials.php/assets.php and would otherwise hit a 403.
function dashboardCard(string $colorClass, int $value, string $label, ?string $href): void
{
    $inner = '<div class="card ' . $colorClass . ' h-100' . ($href ? ' dashboard-clickable' : '') . '"><div class="card-body">'
        . '<div class="fs-2 fw-bold">' . $value . '</div><div>' . htmlspecialchars($label) . '</div></div></div>';
    if ($href) {
        echo '<a href="' . htmlspecialchars($href) . '" class="text-decoration-none">' . $inner . '</a>';
    } else {
        echo $inner;
    }
}
?>

<h3 class="mb-4">แดชบอร์ด</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <?php dashboardCard('text-bg-primary', (int) $materialCount, 'รายการวัสดุสิ้นเปลือง', isAdmin() ? BASE_URL . 'public/materials.php' : null) ?>
    </div>
    <div class="col-md-3">
        <?php dashboardCard('text-bg-warning', (int) $lowStockCount, 'วัสดุใกล้หมดสต๊อก', isAdmin() ? BASE_URL . 'public/materials.php?filter=low_stock' : null) ?>
    </div>
    <div class="col-md-3">
        <?php dashboardCard('text-bg-success', (int) $assetCount, 'รายการครุภัณฑ์', isAdmin() ? BASE_URL . 'public/assets.php' : null) ?>
    </div>
    <div class="col-md-3">
        <?php dashboardCard('text-bg-danger', (int) $borrowedCount, 'ครุภัณฑ์ที่ถูกยืมอยู่', isAdmin() ? BASE_URL . 'public/assets.php?filter=borrowed' : null) ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>ใบเบิกล่าสุด</span>
                <a href="<?= BASE_URL ?>public/requisitions.php" class="small">ดูทั้งหมด &rarr;</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>เลขที่ใบเบิก</th><th>ผู้เบิก</th><th>จำนวนรายการ</th><th>เวลา</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRequisitions as $req): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>public/requisition_view.php?id=<?= (int) $req['id'] ?>"><?= htmlspecialchars($req['requisition_no']) ?></a></td>
                            <td><?= htmlspecialchars($req['requester_name'] ?: '-') ?></td>
                            <td><?= (int) $req['item_count'] ?></td>
                            <td><?= htmlspecialchars($req['created_at']) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>public/requisition_print.php?id=<?= (int) $req['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">พิมพ์</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentRequisitions): ?>
                        <tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">การคืนครุภัณฑ์ล่าสุด</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>รายการ</th><th>ผู้ทำรายการ</th><th>เวลา</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReturns as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['asset_name']) ?></td>
                            <td><?= htmlspecialchars($tx['full_name']) ?></td>
                            <td><?= htmlspecialchars($tx['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentReturns): ?>
                        <tr><td colspan="3" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
