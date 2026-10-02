<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
$pdo = getDbConnection();
$storageLocations = $pdo->query('SELECT * FROM storage_locations ORDER BY name')->fetchAll();
$materialCategories = $pdo->query("SELECT * FROM categories WHERE item_type = 'material' ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>จัดการวัสดุสิ้นเปลือง</h3>
    <div>
        <a href="qr_print.php?type=material" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-qr-code"></i> พิมพ์ QR Code</a>
        <a href="catalog_print.php?type=material" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-journal-richtext"></i> พิมพ์แคตตาล็อก</a>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> นำเข้า Excel</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#materialModal" onclick="resetMaterialForm()"><i class="bi bi-plus-lg"></i> เพิ่มวัสดุ</button>
    </div>
</div>

<div class="mb-3 row g-2">
    <div class="col-md-8">
        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาชื่อวัสดุ หรือรหัส...">
    </div>
    <div class="col-md-4">
        <div class="form-check pt-2">
            <input type="checkbox" class="form-check-input" id="lowStockOnly">
            <label class="form-check-label" for="lowStockOnly">แสดงเฉพาะวัสดุใกล้หมดสต๊อก</label>
        </div>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle" id="materialsTable">
    <thead>
    <tr>
        <th style="width:56px;">รูป</th><th>รหัส</th><th>ชื่อวัสดุ</th><th>หมวดหมู่</th><th>คงเหลือ</th><th>หน่วย</th><th>ต้นทุน/หน่วย</th><th>ที่จัดเก็บ</th><th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="materialModalTitle">เพิ่มวัสดุ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="materialForm">
                    <input type="hidden" id="m_id">
                    <div class="mb-3">
                        <label class="form-label">ชื่อวัสดุ</label>
                        <input type="text" class="form-control" id="m_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมวดหมู่</label>
                        <input type="text" class="form-control" id="m_category" list="m_category_list" placeholder="เว้นว่างเพื่อใช้ตัวอักษรไทยตัวแรกของชื่ออัตโนมัติ">
                        <datalist id="m_category_list">
                            <?php foreach ($materialCategories as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-text">พิมพ์หมวดหมู่เองได้ตามต้องการ หรือเว้นว่างไว้เพื่อให้ระบบกำหนดจากตัวอักษรไทยตัวแรกของชื่อวัสดุให้อัตโนมัติ</div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">หน่วยนับ</label>
                            <input type="text" class="form-control" id="m_unit" value="ชิ้น">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">จำนวนคงเหลือเริ่มต้น</label>
                            <input type="number" class="form-control" id="m_stock_qty" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">ต้นทุน/หน่วย (บาท)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="m_unit_cost" value="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">จุดสั่งซื้อขั้นต่ำ (แจ้งเตือน)</label>
                            <input type="number" class="form-control" id="m_min_stock" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">สถานที่จัดเก็บ</label>
                        <select class="form-select" id="m_storage_location">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($storageLocations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc['name']) ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">จัดการรายการสถานที่จัดเก็บได้ที่เมนู "ตั้งค่าระบบ"</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="m_note"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รูปภาพ</label>
                        <div class="mb-2"><img id="m_image_preview" src="" alt="" style="max-height:120px; display:none;" class="border rounded"></div>
                        <input type="file" class="form-control" id="m_image" accept="image/*">
                        <div class="form-text">JPG, PNG, WEBP หรือ GIF ขนาดไม่เกิน 5MB</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" onclick="saveMaterial()">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<!-- Import modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">นำเข้าวัสดุจากไฟล์ Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-1">รองรับ 2 รูปแบบไฟล์ - ระบบตรวจจับอัตโนมัติ (หมวดหมู่กำหนดให้อัตโนมัติจากชื่อวัสดุเสมอ ไม่ต้องระบุมาในไฟล์):</p>
                <ul class="text-muted small">
                    <li>แบบฟอร์มของระบบ: คอลัมน์ name, unit, unit_cost, stock_qty, min_stock, storage_location, note</li>
                    <li>แบบฟอร์ม "รายละเอียดพัสดุ" ของสำนักงาน (ลำดับที่ / รายละเอียดของพัสดุ / ราคาที่ได้มาจากการสืบราคา(หน่วยละ) / จำนวน(หน่วย)) - ใช้ไฟล์เดิมได้เลยโดยไม่ต้องปรับคอลัมน์</li>
                </ul>
                <a href="<?= BASE_URL ?>api/import_template.php?type=material" class="btn btn-sm btn-outline-primary mb-3">
                    <i class="bi bi-download"></i> ดาวน์โหลดแบบฟอร์มเปล่า (Excel)
                </a>
                <form id="importForm" enctype="multipart/form-data">
                    <input type="hidden" name="type" value="material">
                    <input type="file" name="excel_file" accept=".xlsx" class="form-control" required>
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

<script src="<?= BASE_URL ?>assets/js/materials.js<?= assetVersion('assets/js/materials.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
