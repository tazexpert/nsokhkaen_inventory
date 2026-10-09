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
            tbody.append(`
                <tr>
                    <td><a href="${REQ_VIEW_URL}?id=${r.id}">${r.requisition_no}</a></td>
                    <td>${r.created_at}</td>
                    <td>${r.requester_name || '-'}</td>
                    <td>${r.purpose || '-'}</td>
                    <td>${r.item_count}</td>
                    <td>${r.created_by_name}</td>
                    <td class="text-end">
                        <a href="${REQ_PRINT_URL}?id=${r.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-printer"></i> พิมพ์
                        </a>
                    </td>
                </tr>
            `);
        });
        if (!res.data.length) {
            tbody.append('<tr><td colspan="7" class="text-center text-muted">ไม่พบข้อมูลใบเบิก</td></tr>');
        }
    });
}

$('#f_keyword').on('keypress', function (e) {
    if (e.which === 13) loadRequisitions();
});

$(document).ready(loadRequisitions);
