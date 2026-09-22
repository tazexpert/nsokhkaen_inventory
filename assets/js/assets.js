const API = BASE_URL_JS + 'api/assets_api.php';
const IMPORT_API = BASE_URL_JS + 'api/import_api.php';

const STATUS_LABEL = { available: 'พร้อมใช้งาน', borrowed: 'ถูกยืมอยู่', maintenance: 'ซ่อมบำรุง', disposed: 'จำหน่ายแล้ว' };
const STATUS_BADGE = { available: 'success', borrowed: 'warning', maintenance: 'secondary', disposed: 'dark' };

function loadAssets(keyword = '') {
    $.get(API, { action: 'list', keyword }, function (res) {
        const tbody = $('#assetsTable tbody').empty();
        res.data.forEach((a) => {
            tbody.append(`
                <tr>
                    <td>${a.asset_code}</td>
                    <td>${a.name}</td>
                    <td>${a.category_name || '-'}</td>
                    <td>${a.brand_model || '-'}</td>
                    <td><span class="badge bg-${STATUS_BADGE[a.status]}">${STATUS_LABEL[a.status]}</span></td>
                    <td>${a.storage_location || '-'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="editAsset(${a.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAsset(${a.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    });
}

function resetAssetForm() {
    $('#assetForm')[0].reset();
    $('#a_id').val('');
    $('#statusWrapper').hide();
    $('#assetModalTitle').text('เพิ่มครุภัณฑ์');
}

function editAsset(id) {
    $.get(API, { action: 'get', id }, function (res) {
        const a = res.data;
        $('#a_id').val(a.id);
        $('#a_name').val(a.name);
        $('#a_category_id').val(a.category_id || '');
        $('#a_brand_model').val(a.brand_model);
        $('#a_serial_number').val(a.serial_number);
        $('#a_status').val(a.status);
        $('#a_acquired_date').val(a.acquired_date);
        $('#a_storage_location').val(a.storage_location);
        $('#a_note').val(a.note);
        $('#statusWrapper').show();
        $('#assetModalTitle').text('แก้ไขครุภัณฑ์: ' + a.name);
        new bootstrap.Modal('#assetModal').show();
    });
}

function saveAsset() {
    const id = $('#a_id').val();
    const payload = {
        action: id ? 'update' : 'create',
        id,
        name: $('#a_name').val(),
        category_id: $('#a_category_id').val(),
        brand_model: $('#a_brand_model').val(),
        serial_number: $('#a_serial_number').val(),
        status: $('#a_status').val() || 'available',
        acquired_date: $('#a_acquired_date').val(),
        storage_location: $('#a_storage_location').val(),
        note: $('#a_note').val(),
    };
    $.post(API, payload)
        .done((res) => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('assetModal')).hide();
            loadAssets();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function deleteAsset(id) {
    if (!confirm('ยืนยันการลบรายการนี้?')) return;
    $.post(API, { action: 'delete', id })
        .done((res) => { alert(res.message); loadAssets(); })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function doImport() {
    const formData = new FormData($('#importForm')[0]);
    $.ajax({
        url: IMPORT_API,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
    }).done((res) => {
        $('#importResult').html(`<div class="alert alert-${res.success ? 'success' : 'danger'}">${res.message}</div>`);
        if (res.success) loadAssets();
    }).fail((xhr) => {
        $('#importResult').html(`<div class="alert alert-danger">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
    });
}

$('#searchInput').on('input', function () {
    loadAssets($(this).val());
});

$(document).ready(() => loadAssets());
