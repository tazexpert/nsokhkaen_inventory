const API = BASE_URL_JS + 'api/materials_api.php';
const IMPORT_API = BASE_URL_JS + 'api/import_api.php';

function loadMaterials(keyword = '') {
    $.get(API, { action: 'list', keyword }, function (res) {
        const tbody = $('#materialsTable tbody').empty();
        res.data.forEach((m) => {
            const lowStock = m.stock_qty <= m.min_stock;
            tbody.append(`
                <tr>
                    <td>${m.material_code}</td>
                    <td>${m.name}</td>
                    <td>${m.category_name || '-'}</td>
                    <td class="${lowStock ? 'text-danger fw-bold' : ''}">${m.stock_qty}</td>
                    <td>${m.unit}</td>
                    <td>${Number(m.unit_cost).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
                    <td>${m.storage_location || '-'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="editMaterial(${m.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMaterial(${m.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    });
}

function resetMaterialForm() {
    $('#materialForm')[0].reset();
    $('#m_id').val('');
    $('#materialModalTitle').text('เพิ่มวัสดุ');
}

function editMaterial(id) {
    $.get(API, { action: 'get', id }, function (res) {
        const m = res.data;
        $('#m_id').val(m.id);
        $('#m_name').val(m.name);
        $('#m_category_id').val(m.category_id || '');
        $('#m_unit').val(m.unit);
        $('#m_unit_cost').val(m.unit_cost);
        $('#m_stock_qty').val(m.stock_qty).prop('disabled', true);
        $('#m_min_stock').val(m.min_stock);
        $('#m_storage_location').val(m.storage_location);
        $('#m_note').val(m.note);
        $('#materialModalTitle').text('แก้ไขวัสดุ: ' + m.name);
        new bootstrap.Modal('#materialModal').show();
    });
}

function saveMaterial() {
    const id = $('#m_id').val();
    const payload = {
        action: id ? 'update' : 'create',
        id,
        name: $('#m_name').val(),
        category_id: $('#m_category_id').val(),
        unit: $('#m_unit').val(),
        unit_cost: $('#m_unit_cost').val(),
        stock_qty: $('#m_stock_qty').val(),
        min_stock: $('#m_min_stock').val(),
        storage_location: $('#m_storage_location').val(),
        note: $('#m_note').val(),
    };
    $.post(API, payload)
        .done((res) => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('materialModal')).hide();
            $('#m_stock_qty').prop('disabled', false);
            loadMaterials();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function deleteMaterial(id) {
    if (!confirm('ยืนยันการลบรายการนี้?')) return;
    $.post(API, { action: 'delete', id })
        .done((res) => { alert(res.message); loadMaterials(); })
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
        if (res.success) loadMaterials();
    }).fail((xhr) => {
        $('#importResult').html(`<div class="alert alert-danger">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
    });
}

$('#searchInput').on('input', function () {
    loadMaterials($(this).val());
});

$(document).ready(() => loadMaterials());
