const REQ_API = BASE_URL_JS + 'api/requisition_api.php';
const REQ_PRINT_URL = BASE_URL_JS + 'public/requisition_print.php';
const REQ_VIEW_URL = BASE_URL_JS + 'public/requisition_view.php';

function loadRequisitions() {
    const params = {
        action: 'list',
        keyword: $('#f_keyword').val(),
        start_date: thaiDateSelectGet('f_start'),
        end_date: thaiDateSelectGet('f_end'),
    };

    $.get(REQ_API, params, function (res) {
        const tbody = $('#requisitionsTable tbody').empty();
        res.data.forEach((r) => {
            const cancelBtn = IS_ADMIN_JS ? `
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelRequisition(${r.id}, '${r.requisition_no}')">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>` : '';
            tbody.append(`
                <tr>
                    <td><a href="${REQ_VIEW_URL}?id=${r.id}">${r.requisition_no}</a></td>
                    <td>${formatThaiDateTime(r.created_at)}</td>
                    <td>${r.requester_name || '-'}</td>
                    <td>${r.purpose || '-'}</td>
                    <td>${r.item_count}</td>
                    <td>${r.created_by_name}</td>
                    <td class="text-end">
                        <a href="${REQ_PRINT_URL}?id=${r.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-printer"></i> พิมพ์
                        </a>
                        ${cancelBtn}
                    </td>
                </tr>
            `);
        });
        if (!res.data.length) {
            tbody.append('<tr><td colspan="7" class="text-center text-muted">ไม่พบข้อมูลใบเบิก</td></tr>');
        }
    });
}

// Admin only. Deletes the requisition entirely and restores every
// withdrawn quantity back to stock - see api/requisition_api.php's
// cancelRequisition() for why this is a hard delete, not a status flag.
function cancelRequisition(id, requisitionNo) {
    if (!confirm(`ยืนยันยกเลิกใบเบิกเลขที่ ${requisitionNo}?\n\nระบบจะลบใบเบิกนี้ออกอย่างถาวร และคืนจำนวนวัสดุที่เบิกไปทั้งหมดกลับเข้าสต๊อก ไม่สามารถย้อนกลับได้`)) {
        return;
    }
    $.post(REQ_API, { action: 'cancel', id }, function (res) {
        alert(res.message);
        if (res.success) {
            loadRequisitions();
        }
    }, 'json').fail(function (xhr) {
        alert((xhr.responseJSON && xhr.responseJSON.message) || 'เกิดข้อผิดพลาดในการยกเลิกใบเบิก');
    });
}

$('#f_keyword').on('keypress', function (e) {
    if (e.which === 13) loadRequisitions();
});

$(document).ready(loadRequisitions);
