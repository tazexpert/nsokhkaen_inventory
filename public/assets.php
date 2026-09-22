<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$pdo = getDbConnection();
$categories = $pdo->query("SELECT * FROM categories WHERE item_type = 'asset' ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>จัดการครุภัณฑ์</h3>
    <div>
        <a href="qr_print.php?type=asset" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-qr-code"></i> พิมพ์ QR Code</a>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> นำเข้า Excel</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="resetAssetForm()"><i class="bi bi-plus-lg"></i> เพิ่มครุภัณฑ์</button>
    </div>
</div>

<div class="mb-3">
    <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาชื่อครุภัณฑ์ หรือรหัส...">
</div>

<div class="table-responsive">
<table class="table table-striped align-middle" id="assetsTable">
    <thead>
    <tr>
        <th>รหัส</th><th>ชื่อครุภัณฑ์</th><th>หมวดหมู่</th><th>ยี่ห้อ/รุ่น</th><th>สถานะ</th><th>ที่จัดเก็บ</th><th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalTitle">เพิ่มครุภัณฑ์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assetForm">
                    <input type="hidden" id="a_id">
                    <div class="mb-3">
                        <label class="form-label">ชื่อครุภัณฑ์</label>
                        <input type="text" class="form-control" id="a_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมวดหมู่</label>
                        <select class="form-select" id="a_category_id">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">ยี่ห้อ/รุ่น</label>
                            <input type="text" class="form-control" id="a_brand_model">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">หมายเลขเครื่อง</label>
                            <input type="text" class="form-control" id="a_serial_number">
                        </div>
                    </div>
                    <div class="mb-3" id="statusWrapper" style="display:none;">
                        <label class="form-label">สถานะ</label>
                        <select class="form-select" id="a_status">
                            <option value="available">พร้อมใช้งาน</option>
                            <option value="borrowed">ถูกยืมอยู่</option>
                            <option value="maintenance">ซ่อมบำรุง</option>
                            <option value="disposed">จำหน่ายแล้ว</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่ได้มา</label>
                        <input type="date" class="form-control" id="a_acquired_date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">สถานที่จัดเก็บ</label>
                        <input type="text" class="form-control" id="a_storage_location">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="a_note"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" onclick="saveAsset()">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<!-- Import modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">นำเข้าครุภัณฑ์จากไฟล์ Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">คอลัมน์ที่ต้องมี: name, category_id (ไม่บังคับ), brand_model, serial_number, storage_location, acquired_date, note</p>
                <form id="importForm" enctype="multipart/form-data">
                    <input type="hidden" name="type" value="asset">
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" class="form-control" required>
                </form>
                <div id="importResult" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button class="btn btn-success" onclick="doImport()">นำเข้าข้อมูล</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/assets.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
