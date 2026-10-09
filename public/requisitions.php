<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h3 class="mb-4">ใบเบิกวัสดุ</h3>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">ค้นหา (เลขที่ใบเบิก / ชื่อผู้เบิก / งานที่ใช้)</label>
                <input type="text" id="f_keyword" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่เริ่มต้น</label>
                <input type="date" id="f_start" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่สิ้นสุด</label>
                <input type="date" id="f_end" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" onclick="loadRequisitions()">ค้นหา</button>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle" id="requisitionsTable">
    <thead>
    <tr>
        <th>เลขที่ใบเบิก</th><th>วันที่</th><th>ผู้เบิก</th><th>ใช้งาน</th><th>จำนวนรายการ</th><th>ผู้บันทึก</th><th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
</div>

<script src="<?= BASE_URL ?>assets/js/requisitions.js<?= assetVersion('assets/js/requisitions.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
