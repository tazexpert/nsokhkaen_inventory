const USERS_API = BASE_URL_JS + 'api/users_api.php';
const LOCATIONS_API = BASE_URL_JS + 'api/storage_locations_api.php';
const SETTINGS_API = BASE_URL_JS + 'api/settings_api.php';
const BORROWERS_API = BASE_URL_JS + 'api/borrowers_api.php';

const ROLE_LABEL = { admin: 'ผู้ดูแลระบบ', staff: 'เจ้าหน้าที่' };

/* ---------------- Users ---------------- */

function loadUsers() {
    $.get(USERS_API, { action: 'list' }, function (res) {
        const tbody = $('#usersTable tbody').empty();
        res.data.forEach((u) => {
            tbody.append(`
                <tr>
                    <td>${u.username}</td>
                    <td>${u.full_name}</td>
                    <td>${ROLE_LABEL[u.role] || u.role}</td>
                    <td>${Number(u.is_active) ? '<span class="badge bg-success">ใช้งานอยู่</span>' : '<span class="badge bg-secondary">ปิดใช้งาน</span>'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(${u.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-warning" onclick="toggleUserActive(${u.id})"><i class="bi bi-power"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    });
}

function resetUserForm() {
    $('#userForm')[0].reset();
    $('#u_id').val('');
    $('#u_username').prop('disabled', false);
    $('#u_password_hint').hide();
    $('#u_password').attr('placeholder', 'อย่างน้อย 6 ตัวอักษร');
    $('#userModalTitle').text('เพิ่มผู้ใช้งาน');
}

function editUser(id) {
    $.get(USERS_API, { action: 'get', id }, function (res) {
        const u = res.data;
        $('#u_id').val(u.id);
        $('#u_username').val(u.username).prop('disabled', true);
        $('#u_full_name').val(u.full_name);
        $('#u_role').val(u.role);
        $('#u_password').val('').attr('placeholder', '');
        $('#u_password_hint').show();
        $('#userModalTitle').text('แก้ไขผู้ใช้งาน: ' + u.username);
        new bootstrap.Modal('#userModal').show();
    });
}

function saveUser() {
    const id = $('#u_id').val();
    const payload = {
        action: id ? 'update' : 'create',
        id,
        username: $('#u_username').val(),
        full_name: $('#u_full_name').val(),
        role: $('#u_role').val(),
        password: $('#u_password').val(),
    };
    $.post(USERS_API, payload)
        .done((res) => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            loadUsers();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function toggleUserActive(id) {
    $.post(USERS_API, { action: 'toggle_active', id })
        .done((res) => { alert(res.message); loadUsers(); })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function deleteUser(id) {
    if (!confirm('ยืนยันการลบผู้ใช้งานนี้?')) return;
    $.post(USERS_API, { action: 'delete', id })
        .done((res) => { alert(res.message); loadUsers(); })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

/* ---------------- Storage locations ---------------- */

function loadStorageLocations() {
    $.get(LOCATIONS_API, { action: 'list' }, function (res) {
        const tbody = $('#locationsTable tbody').empty();
        res.data.forEach((loc) => {
            tbody.append(`
                <tr>
                    <td>${loc.name}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteStorageLocation(${loc.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    });
}

function addStorageLocation() {
    const name = $('#newLocationName').val().trim();
    if (!name) return;
    $.post(LOCATIONS_API, { action: 'create', name })
        .done((res) => {
            $('#newLocationName').val('');
            loadStorageLocations();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function deleteStorageLocation(id) {
    if (!confirm('ยืนยันการลบสถานที่จัดเก็บนี้?')) return;
    $.post(LOCATIONS_API, { action: 'delete', id })
        .done(() => loadStorageLocations())
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

/* ---------------- Borrowers (PIN) ---------------- */

let borrowersCache = [];

function loadBorrowers() {
    $.get(BORROWERS_API, { action: 'list' }, function (res) {
        borrowersCache = res.data;
        const tbody = $('#borrowersTable tbody').empty();
        res.data.forEach((b) => {
            tbody.append(`
                <tr>
                    <td>${b.full_name}</td>
                    <td>${b.position || '-'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="editBorrower(${b.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteBorrower(${b.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    });
}

function resetBorrowerForm() {
    $('#borrowerForm')[0].reset();
    $('#b_id').val('');
    $('#b_pin_hint').hide();
    $('#b_pin').attr('placeholder', 'เช่น 123456').prop('required', true);
    $('#borrowerModalTitle').text('เพิ่มผู้ยืม');
}

function editBorrower(id) {
    const b = borrowersCache.find((x) => x.id === id);
    if (!b) return;
    $('#b_id').val(b.id);
    $('#b_full_name').val(b.full_name);
    $('#b_position').val(b.position);
    $('#b_pin').val('').attr('placeholder', '').prop('required', false);
    $('#b_pin_hint').show();
    $('#borrowerModalTitle').text('แก้ไขผู้ยืม: ' + b.full_name);
    new bootstrap.Modal('#borrowerModal').show();
}

function saveBorrower() {
    const id = $('#b_id').val();
    const payload = {
        action: id ? 'update' : 'create',
        id,
        full_name: $('#b_full_name').val(),
        position: $('#b_position').val(),
        pin: $('#b_pin').val(),
    };
    $.post(BORROWERS_API, payload)
        .done((res) => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('borrowerModal')).hide();
            loadBorrowers();
        })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function deleteBorrower(id) {
    if (!confirm('ยืนยันการลบผู้ยืมนี้?')) return;
    $.post(BORROWERS_API, { action: 'delete', id })
        .done((res) => { alert(res.message); loadBorrowers(); })
        .fail((xhr) => alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

/* ---------------- App settings ---------------- */

function loadAppSettings() {
    $.get(SETTINGS_API, { action: 'get' }, function (res) {
        $('#s_app_name').val(res.data.app_name || '');
        $('#s_app_url').val(res.data.app_url || '');
        $('#s_base_url').val(res.data.base_url || '');
    });
}

function saveAppSettings() {
    const payload = {
        action: 'update',
        app_name: $('#s_app_name').val(),
        app_url: $('#s_app_url').val(),
    };
    $.post(SETTINGS_API, payload)
        .done((res) => {
            $('#appSettingsResult').html(`<div class="alert alert-success mt-3">${res.message}</div>`);
            loadAppSettings();
        })
        .fail((xhr) => {
            $('#appSettingsResult').html(`<div class="alert alert-danger mt-3">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
        });
}

$(document).ready(() => {
    loadUsers();
    loadStorageLocations();
    loadBorrowers();
    loadAppSettings();
});
