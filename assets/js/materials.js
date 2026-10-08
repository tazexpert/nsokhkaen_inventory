const API = BASE_URL_JS + 'api/materials_api.php';
const IMPORT_API = BASE_URL_JS + 'api/import_api.php';

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

// Column sort state for the materials table - defaults to รหัส (material_code)
// ascending, toggles direction when the same header is clicked again.
let materialSort = { key: 'material_code', dir: 'asc' };

function sortMaterials(data) {
    const { key, dir } = materialSort;
    const sign = dir === 'asc' ? 1 : -1;
    return [...data].sort((a, b) => {
        const av = a[key] ?? '';
        const bv = b[key] ?? '';
        if (typeof av === 'number' && typeof bv === 'number') {
            return (av - bv) * sign;
        }
        return String(av).localeCompare(String(bv), 'th') * sign;
    });
}

function updateSortIndicators() {
    $('#materialsTable th.sortable i.bi').attr('class', 'bi');
    const $active = $(`#materialsTable th.sortable[data-sort="${materialSort.key}"] i`);
    $active.addClass(materialSort.dir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill');
}

function loadMaterials(keyword = '') {
    const lowStock = $('#lowStockOnly').is(':checked') ? '1' : '';
    $.get(API, { action: 'list', keyword, low_stock: lowStock }, function (res) {
        const tbody = $('#materialsTable tbody').empty();
        updateSortIndicators();
        sortMaterials(res.data).forEach((m) => {
            const lowStock = m.stock_qty <= m.min_stock;
            const thumb = m.image_path
                ? `<img src="${imageUrl(m.image_path)}" alt="" style="width:40px;height:40px;object-fit:cover;" class="rounded">`
                : `<span class="text-muted d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="bi bi-image"></i></span>`;
            tbody.append(`
                <tr>
                    <td>${thumb}</td>
                    <td>${m.material_code}</td>
                    <td>${m.name}</td>
                    <td>${m.category_name || '-'}</td>
                    <td class="${lowStock ? 'text-danger fw-bold' : ''}">${m.stock_qty}</td>
                    <td>${m.unit}</td>
                    <td>${Number(m.unit_cost).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
                    <td>${m.storage_location || '-'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-success" title="รับเข้าสต็อก" onclick="openReceiveStock(${m.id}, '${(m.name || '').replace(/'/g, "\\'")}', ${m.stock_qty}, '${m.unit}')"><i class="bi bi-box-arrow-in-down"></i></button>
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
    $('#m_category_code').val('');
    $('#m_image_preview').attr('src', '').hide();
    $('#m_stock_qty_label').text('จำนวนคงเหลือเริ่มต้น');
    $('#materialModalTitle').text('เพิ่มวัสดุ');
}

function editMaterial(id) {
    $.get(API, { action: 'get', id }, function (res) {
        const m = res.data;
        $('#m_id').val(m.id);
        $('#m_name').val(m.name);
        $('#m_unit').val(m.unit);
        $('#m_unit_cost').val(m.unit_cost);
        $('#m_stock_qty').val(m.stock_qty);
        $('#m_stock_qty_label').text('จำนวนคงเหลือ');
        $('#m_min_stock').val(m.min_stock);
        ensureSelectOption('#m_storage_location', m.storage_location);
        $('#m_note').val(m.note);
        $('#m_category_code').val(m.category_code || '');
        if (m.image_path) {
            $('#m_image_preview').attr('src', imageUrl(m.image_path)).show();
        } else {
            $('#m_image_preview').attr('src', '').hide();
        }
        $('#materialModalTitle').text('แก้ไขวัสดุ: ' + m.name);
        new bootstrap.Modal('#materialModal').show();
    });
}

function saveMaterial() {
    const id = $('#m_id').val();
    const formData = new FormData();
    formData.append('action', id ? 'update' : 'create');
    formData.append('id', id);
    formData.append('name', $('#m_name').val());
    formData.append('unit', $('#m_unit').val());
    formData.append('unit_cost', $('#m_unit_cost').val());
    formData.append('stock_qty', $('#m_stock_qty').val());
    formData.append('min_stock', $('#m_min_stock').val());
    formData.append('storage_location', $('#m_storage_location').val());
    formData.append('note', $('#m_note').val());
    formData.append('category_code', $('#m_category_code').val());
    const imageFile = $('#m_image')[0].files[0];
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
            bootstrap.Modal.getInstance(document.getElementById('materialModal')).hide();
            loadMaterials();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function openReceiveStock(id, name, currentQty, unit) {
    $('#rs_id').val(id);
    $('#rs_current_qty').text(currentQty);
    $('#rs_unit').text(unit || '');
    $('#rs_quantity').val('');
    $('#rs_unit_cost').val('');
    $('#rs_note').val('');
    $('#receiveStockModalTitle').text('รับเข้าสต็อก: ' + name);
    new bootstrap.Modal('#receiveStockModal').show();
}

function saveReceiveStock() {
    const payload = {
        action: 'receive_stock',
        id: $('#rs_id').val(),
        quantity: $('#rs_quantity').val(),
        unit_cost: $('#rs_unit_cost').val(),
        note: $('#rs_note').val(),
    };
    $.post(API, payload)
        .done((res) => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('receiveStockModal')).hide();
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

$('#lowStockOnly').on('change', function () {
    loadMaterials($('#searchInput').val());
});

$('#materialsTable thead').on('click', 'th.sortable', function () {
    const key = $(this).data('sort');
    if (materialSort.key === key) {
        materialSort.dir = materialSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        materialSort = { key, dir: 'asc' };
    }
    loadMaterials($('#searchInput').val());
});

$(document).ready(() => {
    // Came from a dashboard link like materials.php?filter=low_stock
    const params = new URLSearchParams(window.location.search);
    if (params.get('filter') === 'low_stock') {
        $('#lowStockOnly').prop('checked', true);
    }
    loadMaterials($('#searchInput').val());
});
