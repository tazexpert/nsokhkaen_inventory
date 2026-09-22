<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<h3 class="mb-4">ตั้งค่าระบบ</h3>

<ul class="nav nav-tabs mb-4" id="settingsTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUsers">ผู้ใช้งาน</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLocations">สถานที่จัดเก็บ</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApp">ค่าพื้นฐานระบบ</button>
    </li>
</ul>

<div class="tab-content">
    <!-- ผู้ใช้งาน -->
    <div class="tab-pane fade show active" id="tabUsers">
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
                <i class="bi bi-plus-lg"></i> เพิ่มผู้ใช้งาน
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle" id="usersTable">
                <thead>
                <tr><th>ชื่อผู้ใช้</th><th>ชื่อ-นามสกุล</th><th>สิทธิ์</th><th>สถานะ</th><th></th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- สถานที่จัดเก็บ -->
    <div class="tab-pane fade" id="tabLocations">
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">เพิ่มสถานที่จัดเก็บ</div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" id="newLocationName" class="form-control" placeholder="เช่น ห้องพัสดุ ชั้น 1">
                            <button class="btn btn-primary" onclick="addStorageLocation()">เพิ่ม</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="locationsTable">
                        <thead><tr><th>สถานที่จัดเก็บ</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ค่าพื้นฐานระบบ -->
    <div class="tab-pane fade" id="tabApp">
        <div class="card" style="max-width: 600px;">
            <div class="card-body">
                <form id="appSettingsForm">
                    <div class="mb-3">
                        <label class="form-label">ชื่อระบบ (APP_NAME)</label>
                        <input type="text" class="form-control" id="s_app_name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL ของระบบ (APP_URL)</label>
                        <input type="text" class="form-control" id="s_app_url" placeholder="http://192.168.1.50/nsokhkaen_inventory/">
                        <div class="form-text">
                            ที่อยู่นี้จะถูกฝังลงใน QR Code ทุกใบที่พิมพ์ใหม่ ต้องเป็นที่อยู่ที่ <strong>มือถือที่จะสแกน</strong> เข้าถึงได้จริง
                            (ห้ามใช้ <code>localhost</code> ถ้าจะสแกนด้วยมือถือเครื่องอื่น — ดูวิธีหา LAN IP ใน README)
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">พาธของระบบ (BASE_URL)</label>
                        <input type="text" class="form-control" id="s_base_url" disabled>
                        <div class="form-text">คำนวณอัตโนมัติจาก URL ของระบบด้านบน ไม่สามารถแก้ไขแยกได้</div>
                    </div>
                </form>
                <div id="appSettingsResult"></div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" onclick="saveAppSettings()">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit user modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">เพิ่มผู้ใช้งาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="u_id">
                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" class="form-control" id="u_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="u_full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">สิทธิ์การใช้งาน</label>
                        <select class="form-select" id="u_role">
                            <option value="staff">เจ้าหน้าที่ (Staff)</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="u_password_label">รหัสผ่าน</label>
                        <input type="password" class="form-control" id="u_password" placeholder="อย่างน้อย 6 ตัวอักษร">
                        <div class="form-text" id="u_password_hint" style="display:none;">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" onclick="saveUser()">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/settings.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
