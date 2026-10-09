<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$materialCount = $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
?>

<h3 class="mb-4">แดชบอร์ด</h3>

<div class="row g-3">
    <div class="col-md-4">
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>public/materials.php" class="text-decoration-none">
            <div class="card text-bg-primary h-100 dashboard-clickable">
                <div class="card-body">
                    <div class="fs-2 fw-bold"><?= (int) $materialCount ?></div>
                    <div>วัสดุสิ้นเปลืองทั้งหมด</div>
                    <div class="small mt-1"><i class="bi bi-eye"></i> ดูรายละเอียด</div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= (int) $materialCount ?></div>
                <div>วัสดุสิ้นเปลืองทั้งหมด</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
