const API = BASE_URL_JS + 'api/assets_api.php';
const IMPORT_API = BASE_URL_JS + 'api/import_api.php';

const STATUS_LABEL = { available: 'พร้อมใช้งาน', borrowed: 'ถูกยืมอยู่', maintenance: 'ซ่อมบำรุง', disposed: 'จำหน่ายแล้ว' };
const STATUS_BADGE = { available: 'success', borrowed: 'warning', maintenance: 'secondary', disposed: 'dark' };

// If the item's saved storage_location isn't in the current master list
// (e.g. it was removed from Settings), add it so the dropdown still shows
// the real current value instead of silently resetting to blank.
function ensureSelectOption(selectEl, value) {
    if (!value) return;
    const $select = $(selectEl);
    if ($select.find(`option[value="${CSS.escape(value)}"]`).length === 0) {
        $select.append(`<option value="${value}">${value} (ไม่อยู่ในรายการ)</option>`);
    }
    $select.val(value);
}

function imageUrl(path) {
    return path ? BASE_URL_JS + path : '';
}

function loadAssets(keyword = '') {
    const status = $('#statusFilter').val();
    $.get(API, { action: 'list', keyword, status }, function (res) {
        const tbody = $('#assetsTable tbody').empty();
        res.data.forEach((a) => {
            const thumb = a.image_path
                ? `<img src="${imageUrl(a.image_path)}" alt="" style="width:40px;height:40px;object-fit:cover;" class="rounded">`
                : `<span class="text-muted"><i class="bi bi-image"></i></span>`;
            tbody.append(`
                <tr>
                    <td>${thumb}</td>
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
    $('#a_image_preview').attr('src', '').hide();
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
        ensureSelectOption('#a_storage_location', a.storage_location);
        $('#a_note').val(a.note);
        $('#statusWrapper').show();
        if (a.image_path) {
            $('#a_image_preview').attr('src', imageUrl(a.image_path)).show();
        } else {
            $('#a_image_preview').attr('src', '').hide();
        }
        $('#assetModalTitle').text('แก้ไขครุภัณฑ์: ' + a.name);
        new bootstrap.Modal('#assetModal').show();
    });
}

function saveAsset() {
    const id = $('#a_id').val();
    const formData = new FormData();
    formData.append('action', id ? 'update' : 'create');
    formData.append('id', id);
    formData.append('name', $('#a_name').val());
    formData.append('category_id', $('#a_category_id').val());
    formData.append('brand_model', $('#a_brand_model').val());
    formData.append('serial_number', $('#a_serial_number').val());
    formData.append('status', $('#a_status').val() || 'available');
    formData.append('acquired_date', $('#a_acquired_date').val());
    formData.append('storage_location', $('#a_storage_location').val());
    formData.append('note', $('#a_note').val());
    const imageFile = $('#a_image')[0].files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    $.ajax({
        url: API,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
    })
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

$('#statusFilter').on('change', function () {
    loadAssets($('#searchInput').val());
});

$(document).ready(() => {
    // Came from a dashboard link like assets.php?filter=borrowed
    const params = new URLSearchParams(window.location.search);
    if (params.get('filter')) {
        $('#statusFilter').val(params.get('filter'));
    }
    loadAssets($('#searchInput').val());
});
