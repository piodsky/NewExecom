<?php
require_once 'config.php';
requireSystemUsersAdmin();
$currentUser    = getCurrentUser();
$activePage     = 'settings';
// requireSystemUsersAdmin() above already turned everyone else away, so
// anyone who reaches this page can manage users — kept as its own flag
// rather than inlining `true` since the markup below still branches on it.
$canManageUsers = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Users | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Username reads as the identifier it is, not as body text. */
.su-username { font-weight: 700; color: var(--navy); }
/* Chip and username on one line: without the flex row a long username wraps
   under its own avatar, which reads as two separate people. */
.su-user-cell { display: flex; align-items: center; gap: .5rem; }
.su-avatar {
    flex: 0 0 26px;
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--primary); color: #fff; font-size: .7rem; font-weight: 700;
}
.su-name { font-weight: 500; }
.su-dept { color: var(--muted); font-size: .82rem; }

/* ── Add / Edit User dialog ──────────────────────────────────────────────
   Three numbered cards on a tinted body: pick the person, set the
   credentials, set the access. The steps are numbered because the first one
   gates the rest — a username means nothing until an employee is chosen. */
.su-modal { border: none; border-radius: 16px; overflow: hidden; }
.su-modal-header {
    background: #fff; border-bottom: 1px solid var(--border);
    padding: 1.1rem 1.4rem; align-items: flex-start;
}
.su-modal-heading { display: flex; gap: .9rem; align-items: center; }
.su-modal-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 46px; height: 46px; border-radius: 14px; flex: 0 0 46px;
    background: var(--primary); color: #fff; font-size: 1.15rem;
}
.su-modal-header .modal-title { font-weight: 800; letter-spacing: -.01em; }
.su-modal-sub { color: var(--muted); font-size: .85rem; margin-top: .1rem; }
.su-modal-close {
    border: none; background: #f1f5f9; color: #475569;
    width: 36px; height: 36px; border-radius: 50%; font-size: .95rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.su-modal-close:hover { background: #e2e8f0; color: #0f172a; }

.su-modal-body { background: #f8fafc; padding: 1.1rem 1.4rem; }
.su-card {
    background: #fff; border: 1px solid var(--border); border-radius: 14px;
    padding: 1.1rem 1.2rem; margin-bottom: 1rem;
}
.su-card:last-child { margin-bottom: 0; }
.su-card-head { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .9rem; }
.su-step-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 10px; flex: 0 0 34px;
    background: rgba(37,99,235,.10); color: var(--primary); font-size: .9rem;
}
.su-step-title { margin: 0; font-weight: 700; font-size: 1rem; color: var(--navy); }
.su-step-hint { margin: .15rem 0 0; font-size: .82rem; color: var(--muted); }

/* Icon sits inside the control rather than in a Bootstrap input-group addon,
   so the field keeps one continuous border instead of reading as two boxes. */
.su-field { position: relative; }
.su-field > i {
    position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem; pointer-events: none;
}
.su-field .form-control { padding-left: 2.25rem; }
.su-field .su-eye {
    position: absolute; right: .5rem; top: 50%; transform: translateY(-50%);
    border: none; background: none; color: #94a3b8; padding: .25rem .35rem;
    cursor: pointer; line-height: 1;
}
.su-field .su-eye:hover { color: #475569; }
.su-field:has(.su-eye) .form-control { padding-right: 2.4rem; }

.su-results { border: 1px solid var(--border); border-radius: 12px; overflow: auto; max-height: 300px; }
.su-results table { margin: 0; font-size: .84rem; }
.su-results thead th {
    position: sticky; top: 0; z-index: 1;
    background: #eff6ff; color: var(--navy); font-weight: 700;
    font-size: .76rem; letter-spacing: .02em; border-bottom: 1px solid var(--border);
}
.su-results tbody tr { cursor: pointer; }
.su-results tbody tr.su-taken { cursor: not-allowed; color: var(--muted); }
.su-empty { text-align: center; color: var(--muted); padding: 2rem 1rem; font-size: .85rem; }
.su-empty i { display: block; font-size: 1.8rem; color: #cbd5e1; margin-bottom: .5rem; }

.su-chosen { border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 12px; padding: .8rem 1rem; }
.su-chosen-name { font-weight: 700; color: var(--navy); }
.su-chosen-details { font-size: .82rem; color: #334155; margin-top: .35rem; }

.su-modal-footer { background: #fff; border-top: 1px solid var(--border); padding: .9rem 1.4rem; }

/* Role colour is by authority, not by module: what matters when scanning a
   user list is who can change things, and the module is already spelled out
   in the label. Three tiers, matching deriveAppRole() in config.php —
   role_id 1, anything called "Administrator", everyone else. */
.su-role {
    display: inline-block; border-radius: 999px; padding: .15rem .6rem;
    font-size: .72rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
}
.su-role.tier-super { background: #fee2e2; color: #991b1b; }
.su-role.tier-admin { background: #fef3c7; color: #92400e; }
.su-role.tier-user  { background: #e0e7ff; color: #3730a3; }

/* Same pill shape as .su-role, own color pair — green/gray reads as
   on/off at a glance without borrowing the role tiers' red/amber/indigo,
   which already mean something else in this same list. */
.su-status {
    display: inline-block; border-radius: 999px; padding: .15rem .6rem;
    font-size: .72rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
}
.su-status.is-active   { background: #dcfce7; color: #15803d; }
.su-status.is-inactive { background: #f1f5f9; color: #64748b; }
</style>
</head>
<body>
<div class="wrapper">
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <div class="page-title">System Users</div>
                <div class="page-subtitle">Logins for the shared HRMIS platform &mdash; this app, the VB desktop app and every other module</div>
            </div>
        </div>
        <?php include 'topbar.php'; ?>
    </div>

    <div class="content-area">
        <!-- The Audit Log used to be a second tab here. It is its own page
             now (audit_logs.php) with date/user/module filters and CSV export,
             so this one is left pointing at it rather than keeping a second,
             worse copy of the same table. -->
        <div class="text-muted small mb-3">
            <i class="fas fa-clock-rotate-left me-1"></i>Looking for who changed what?
            <a href="audit_logs.php">Open the Activity Log</a>.
        </div>

        <?php if ($canManageUsers): ?>
        <!-- User Management — the real, shared HRMIS login directory. This
             app's own login (api/auth.php) authenticates against this same
             live table, so accounts created here can log into this app, the
             legacy VB.NET desktop app, and every other HRMIS module. -->
        <div id="tab-hrmis">
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title"><i class="fas fa-users me-2 text-success"></i>User Management</span>
                    <button class="btn btn-primary-app btn-sm" onclick="openHrmisModal()"><i class="fas fa-plus me-1"></i>Add User</button>
                </div>
                <div class="panel-body">
                    <table id="hrmisUsersTable" class="app-table w-100">
                        <thead><tr><th>Username</th><th>Full Name</th><th>Department</th><th>Role</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
                        <tbody id="hrmisUsersBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>


    </div>
</div>
</div>

<?php if ($canManageUsers): ?>
<!-- HRMIS User Modal — create/edit a real shared-login account. Picking an
     employee (Choose_User.vb's search) is skipped entirely when editing,
     since the person is already linked. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="hrmisModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content su-modal">
            <div class="modal-header su-modal-header">
                <div class="su-modal-heading">
                    <span class="su-modal-icon"><i class="fas fa-user-plus"></i></span>
                    <div>
                        <h5 class="modal-title" id="hrmisModalTitle">Add New User</h5>
                        <div class="su-modal-sub" id="hrmisModalSub">Create a new user account from HRMIS employee records</div>
                    </div>
                </div>
                <button type="button" class="su-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="modal-body su-modal-body">
                <input type="hidden" id="hrmisUserId">
                <input type="hidden" id="hrmisPiid">

                <!-- Step 1 — Choose_User.vb: three independent search boxes over
                     the personnel list, and its grid columns. Typing in one
                     clears the other two, exactly as the desktop form did. -->
                <section class="su-card" id="hrmisEmployeePickWrap">
                    <div class="su-card-head">
                        <span class="su-step-icon"><i class="fas fa-magnifying-glass"></i></span>
                        <div>
                            <h6 class="su-step-title">1. Select Employee</h6>
                            <p class="su-step-hint">Pick the person from the HRMIS employee records. Accounts are only ever created for someone already on file.</p>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><div class="su-field">
                            <i class="fas fa-id-card"></i>
                            <input type="text" class="form-control" id="hrmisSearchIdNum"
                                   placeholder="Search ID Number" autocomplete="off" oninput="hrmisPickerTyped('hrmisSearchIdNum')">
                        </div></div>
                        <div class="col-md-4"><div class="su-field">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-control" id="hrmisSearchFirst"
                                   placeholder="Search First Name" autocomplete="off" oninput="hrmisPickerTyped('hrmisSearchFirst')">
                        </div></div>
                        <div class="col-md-4"><div class="su-field">
                            <i class="fas fa-user"></i>
                            <input type="text" class="form-control" id="hrmisSearchSurname"
                                   placeholder="Search Surname" autocomplete="off" oninput="hrmisPickerTyped('hrmisSearchSurname')">
                        </div></div>
                    </div>
                    <div class="su-results">
                        <table class="table table-hover mb-0" id="hrmisEmployeeTable">
                            <thead><tr>
                                <th style="width:140px;">ID Number</th><th>Name</th><th>Address</th><th style="width:80px;">Sex</th>
                            </tr></thead>
                            <tbody id="hrmisEmployeeResults"></tbody>
                        </table>
                    </div>
                </section>

                <!-- Step 2: the person's own record, read-only. Sys_User.vb
                     filled these same fields from tblpersonalinformation so
                     whoever is creating the login can see they picked the
                     right person before typing a password. -->
                <section class="su-card d-none" id="hrmisChosenEmployeeWrap">
                    <div class="su-card-head">
                        <span class="su-step-icon"><i class="fas fa-user-check"></i></span>
                        <div>
                            <h6 class="su-step-title">1. Selected Employee</h6>
                            <p class="su-step-hint">From the HRMIS employee records.</p>
                        </div>
                    </div>
                    <div class="su-chosen">
                        <div class="su-chosen-name" id="hrmisChosenEmployeeName"></div>
                        <div class="row g-1 su-chosen-details" id="hrmisChosenEmployeeDetails"></div>
                        <button type="button" class="btn btn-link btn-sm p-0 mt-2" id="hrmisChangeEmployee"
                                onclick="hrmisReopenPicker()">Choose a different employee</button>
                    </div>
                </section>

                <section class="su-card">
                    <div class="su-card-head">
                        <span class="su-step-icon"><i class="fas fa-lock"></i></span>
                        <div><h6 class="su-step-title">2. Account Credentials</h6></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="hrmisUsername">Username <span class="text-danger">*</span></label>
                            <div class="su-field">
                                <i class="fas fa-user"></i>
                                <input type="text" class="form-control" id="hrmisUsername" placeholder="Enter username (e.g. jdoe)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="hrmisPassword">Password <span class="text-danger" id="hrmisPwRequired">*</span></label>
                            <div class="su-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" class="form-control" id="hrmisPassword" placeholder="Minimum 6 characters" oninput="hrmisCheckMatch()">
                                <button type="button" class="su-eye" onclick="hrmisPeek('hrmisPassword', this)" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
                            </div>
                            <small class="text-muted" id="hrmisPwHint">Required for new accounts</small>
                        </div>
                        <!-- Sys_User.vb refused to save unless password and confirm
                             matched, and showed a "not match" label rather than a
                             popup. Same here. -->
                        <div class="col-md-6">
                            <label class="form-label" for="hrmisPasswordConfirm">Confirm Password <span class="text-danger" id="hrmisPwConfirmRequired">*</span></label>
                            <div class="su-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" class="form-control" id="hrmisPasswordConfirm" placeholder="Re-type the password" oninput="hrmisCheckMatch()">
                                <button type="button" class="su-eye" onclick="hrmisPeek('hrmisPasswordConfirm', this)" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
                            </div>
                            <small class="text-danger d-none" id="hrmisPwMismatch">Passwords do not match.</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="hrmisShowPassword" onchange="hrmisTogglePassword()">
                                <label class="form-check-label small" for="hrmisShowPassword">Show password</label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="su-card">
                    <div class="su-card-head">
                        <span class="su-step-icon"><i class="fas fa-building"></i></span>
                        <div><h6 class="su-step-title">3. Account Details</h6></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="hrmisDepartment">Department</label>
                            <select class="form-select" id="hrmisDepartment"><option value="">— Select Department —</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="hrmisRole">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="hrmisRole"></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="hrmisIsActive" id="hrmisActiveYes" value="1" checked>
                                <label class="form-check-label" for="hrmisActiveYes">Active</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="hrmisIsActive" id="hrmisActiveNo" value="0">
                                <label class="form-check-label" for="hrmisActiveNo">Inactive</label>
                            </div>
                            <!-- A deactivated account can't log in, not "isn't shown
                                 anymore" — said here so it isn't discovered by surprise
                                 the next time this person tries to sign in. -->
                            <div class="su-step-hint mt-1">An inactive account can't log in until switched back to Active.</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer su-modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary-app" onclick="saveHrmisUser()"><i class="fas fa-floppy-disk me-1"></i> Save Account</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script>
const actorRole = '<?= e($currentUser['role']) ?>';
let dtInstances = {};
function showToast(msg,type='success'){const id='t'+Date.now();const bg=type==='success'?'bg-success':type==='error'?'bg-danger':'bg-warning text-dark';document.getElementById('toastContainer').insertAdjacentHTML('beforeend',`<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);const t=new bootstrap.Toast(document.getElementById(id),{delay:4000});t.show();document.getElementById(id).addEventListener('hidden.bs.toast',()=>document.getElementById(id)?.remove());}



function initDT(id, order=[]) {
    if(dtInstances[id]){dtInstances[id].destroy();delete dtInstances[id];}
    dtInstances[id]=$('#'+id).DataTable({pageLength:15,order,language:{search:'',searchPlaceholder:'Search…'},columnDefs:[{orderable:false,targets:-1}]});
}

// User Management — the real, shared HRMIS login directory (see the tab's
// own comment above for why this is the actual login table). Server caps
// the list at 500 rows; the table's own DataTables search box (same as
// Audit below) filters within that.
function escapeHtml(v){if(v===null||v===undefined)return '';return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
// Mirrors api/hrmis_users.php's roleAssignable() — an 'admin' actor can't
// manage role_id=1 or any role whose name contains "Administrator"; only
// super_admin can. Client-side copy is UX only, the API enforces this too.
function canManageHrmis(roleId, roleName){
    if (actorRole === 'super_admin') return true;
    if (actorRole !== 'admin') return false;
    return Number(roleId) !== 1 && !/administrator/i.test(roleName || '');
}
let hrmisData = [];
async function loadHrmisUsers(){
    let res,data;
    try{res=await fetch('api/hrmis_users.php');data=await res.json();}
    catch(err){showToast('Failed to load HRMIS users.','error');console.error(err);return;}
    if(!data.success) return showToast(data.message||'Failed to load HRMIS users.','error');
    hrmisData = data.rows;
    document.getElementById('hrmisUsersBody').innerHTML = data.rows.map(r=>`
        <tr><td><div class="su-user-cell"><span class="su-avatar">${escapeHtml(initialsOf(r.full_name || r.user_name))}</span><span class="su-username">${escapeHtml(r.user_name)}</span></div></td>
        <td class="su-name">${escapeHtml(r.full_name) || '—'}</td>
        <td class="su-dept">${escapeHtml(r.department) || '—'}</td>
        <td>${r.role ? `<span class="su-role ${roleTier(r.role_id, r.role)}">${escapeHtml(r.role)}</span>` : '—'}</td>
        <td>${Number(r.is_active) === 0 ? '<span class="su-status is-inactive">Inactive</span>' : '<span class="su-status is-active">Active</span>'}</td>
        <td class="text-center">
            ${canManageHrmis(r.role_id, r.role) ? `
            <button class="btn btn-outline-primary btn-sm btn-sm-action me-1" onclick="editHrmisUser(${r.user_id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-outline-danger btn-sm btn-sm-action" onclick="deleteHrmisUser(${r.user_id},'${escapeHtml(r.user_name).replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
            ` : `<span class="text-muted small">—</span>`}
        </td></tr>`).join('')
        || '<tr><td colspan="6" class="text-center text-muted py-4">No matching users.</td></tr>';
    initDT('hrmisUsersTable');
}

// Mirrors deriveAppRole() in config.php: role_id 1 outranks everything, then
// any role whose name carries "Administrator", then everyone else. Kept in
// step with it deliberately — a badge that disagreed with what the account can
// actually do would be worse than no badge.
function roleTier(roleId, roleName) {
    if (Number(roleId) === 1) return 'tier-super';
    return /administrator/i.test(String(roleName || '')) ? 'tier-admin' : 'tier-user';
}

// Two letters for the avatar chip — first and last word of the name, so
// "ANGELA GWYNETH GODINEZ" gives AG rather than three initials crammed in.
function initialsOf(name) {
    const words = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return '?';
    const first = words[0][0] || '';
    const last  = words.length > 1 ? (words[words.length - 1][0] || '') : '';
    return (first + last).toUpperCase();
}

let hrmisRoles = [];
async function loadHrmisRoles(){
    try{
        const res=await fetch('api/hrmis_users.php?lookup=roles');
        const data=await res.json();
        if(!data.success) return;
        hrmisRoles = data.rows;
        document.getElementById('hrmisRole').innerHTML = hrmisRoles.map(r=>`<option value="${r.role_id}">${escapeHtml(r.role)}</option>`).join('');
    } catch(err){ console.error(err); }
}

// Departments come from Master Data (ref_department) rather than being typed
// free-hand. All 124 accounts that carry a department already hold a value
// from that list, so nothing existing is lost by making it a picklist — and
// it stops the 25th spelling of an office name being invented here.
let hrmisDepartments = null;
async function loadHrmisDepartments(selected) {
    const sel = document.getElementById('hrmisDepartment');
    if (hrmisDepartments === null) {
        try {
            const res = await fetch('api/master_data.php');
            const data = await res.json();
            // ref_department carries a blank-named row (ID 0). An option with
            // no label next to "none" is just confusing, so it is dropped.
            hrmisDepartments = data.success
                ? (data.categories.departments || []).map(d => (d.name || "").trim()).filter(Boolean)
                : [];
        } catch (err) {
            hrmisDepartments = [];
            console.error(err);
        }
    }
    const chosen = selected || '';
    const list = hrmisDepartments.slice();
    // An account whose department is not in the master list keeps it rather
    // than being silently blanked the next time somebody edits them.
    if (chosen && !list.some(d => d === chosen)) list.unshift(chosen);
    sel.innerHTML = '<option value="">— Select Department —</option>' + list.map(d =>
        `<option value="${escapeHtml(d)}"${d === chosen ? ' selected' : ''}>${escapeHtml(d)}</option>`).join('');
    sel.value = chosen;
}

const HRMIS_SEARCH_FIELDS = ['hrmisSearchIdNum','hrmisSearchFirst','hrmisSearchSurname'];
let hrmisEmployeeSearchTimer = null;
let hrmisEmployeeRows = [];

// Choose_User.vb reset the other two boxes whenever one was typed into, so a
// search is always on a single field. Kept because mixing them silently
// narrows the list in a way nobody expects from that form.
function hrmisPickerTyped(activeId){
    HRMIS_SEARCH_FIELDS.forEach(id => { if (id !== activeId) document.getElementById(id).value = ''; });
    clearTimeout(hrmisEmployeeSearchTimer);
    hrmisEmployeeSearchTimer = setTimeout(searchHrmisEmployees, 300);
}

function hrmisPickerRow(text, icon){
    return `<tr><td colspan="4" class="su-empty"><i class="fas ${icon || 'fa-inbox'}"></i>${text}</td></tr>`;
}

async function searchHrmisEmployees(){
    const idNum   = document.getElementById('hrmisSearchIdNum').value.trim();
    const first   = document.getElementById('hrmisSearchFirst').value.trim();
    const surname = document.getElementById('hrmisSearchSurname').value.trim();
    const box = document.getElementById('hrmisEmployeeResults');
    if (!idNum && !first && !surname) {
        hrmisEmployeeRows = [];
        box.innerHTML = hrmisPickerRow('Search by ID number, first name or surname.');
        return;
    }
    const params = new URLSearchParams({ lookup:'employees', id_num:idNum, first, surname });
    try{
        const res = await fetch('api/hrmis_users.php?' + params.toString());
        const data = await res.json();
        if (!data.success) return;
        hrmisEmployeeRows = data.rows;
        if (!data.rows.length) { box.innerHTML = hrmisPickerRow('No employee matches that search.', 'fa-user-slash'); return; }
        box.innerHTML = data.rows.map((r, i) => {
            const taken = r.already_registered > 0;
            return `<tr class="${taken ? 'su-taken' : ''}" ${taken ? '' : `onclick="selectHrmisEmployee(${i})"`}>
                <td class="fw-semibold">${escapeHtml(r.id_num)}</td>
                <td>${escapeHtml(r.full_name)}
                    ${taken ? '<span class="badge bg-secondary ms-1">has an account</span>' : ''}</td>
                <td>${escapeHtml(r.address || '')}</td>
                <td>${escapeHtml(r.sex || '')}</td>
            </tr>`;
        }).join('');
    } catch(err){ console.error(err); }
}

function selectHrmisEmployee(index){
    const r = hrmisEmployeeRows[index];
    if (!r) return;
    document.getElementById('hrmisPiid').value = r.piid;
    document.getElementById('hrmisEmployeePickWrap').classList.add('d-none');
    document.getElementById('hrmisChosenEmployeeWrap').classList.remove('d-none');
    document.getElementById('hrmisChosenEmployeeName').textContent = r.full_name;

    const bit = (label, value) => value
        ? `<div class="col-md-6"><strong>${label}:</strong> ${escapeHtml(value)}</div>` : '';
    document.getElementById('hrmisChosenEmployeeDetails').innerHTML =
          bit('ID Number', r.id_num)
        + bit('Sex', r.sex)
        + bit('Civil Status', r.civil_status)
        + bit('Birth Date', r.birth_date && !String(r.birth_date).startsWith('0000') ? r.birth_date : '')
        + bit('Address', r.address)
        + bit('Email', r.email)
        + bit('Contact No.', r.cell_no);

    // Sys_User.vb's Next button did exactly this: txt_user.Text = id_num2.Text.
    // The ID number is the office's own default username; it stays editable.
    const username = document.getElementById('hrmisUsername');
    if (!username.value.trim()) username.value = r.id_num || '';
}

function hrmisReopenPicker(){
    document.getElementById('hrmisPiid').value = '';
    document.getElementById('hrmisEmployeePickWrap').classList.remove('d-none');
    document.getElementById('hrmisChosenEmployeeWrap').classList.add('d-none');
}

// Clears both password boxes together and drops the mismatch warning — they
// are a pair, and a stale "do not match" showing on a freshly opened form is
// its own small bug.
function hrmisResetPasswordFields(){
    document.getElementById('hrmisPassword').value = '';
    document.getElementById('hrmisPasswordConfirm').value = '';
    document.getElementById('hrmisShowPassword').checked = false;
    hrmisTogglePassword();
    document.getElementById('hrmisPwMismatch').classList.add('d-none');
}

// The eye button on a single field. The "Show password" checkbox below still
// drives both at once; this keeps the two in agreement rather than letting the
// checkbox claim a state one field is not in.
function hrmisPeek(fieldId, btn){
    const field = document.getElementById(fieldId);
    const show = field.type === 'password';
    field.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye' : 'fas fa-eye-slash';
    const both = ['hrmisPassword','hrmisPasswordConfirm']
        .every(id => document.getElementById(id).type === 'text');
    document.getElementById('hrmisShowPassword').checked = both;
}

function hrmisSyncEyes(){
    ['hrmisPassword','hrmisPasswordConfirm'].forEach(id => {
        const btn = document.getElementById(id).parentElement.querySelector('.su-eye i');
        if (btn) btn.className = document.getElementById(id).type === 'text' ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
}

function hrmisTogglePassword(){
    const show = document.getElementById('hrmisShowPassword').checked;
    ['hrmisPassword','hrmisPasswordConfirm'].forEach(id =>
        document.getElementById(id).type = show ? 'text' : 'password');
    hrmisSyncEyes();
}

// Returns true when the pair is usable. Blank/blank is fine on edit, where it
// means "keep the current password".
function hrmisCheckMatch(){
    const pw = document.getElementById('hrmisPassword').value;
    const cf = document.getElementById('hrmisPasswordConfirm').value;
    const bad = (pw !== '' || cf !== '') && pw !== cf;
    document.getElementById('hrmisPwMismatch').classList.toggle('d-none', !bad);
    return !bad;
}
async function openHrmisModal(){
    document.getElementById('hrmisUserId').value='';
    document.getElementById('hrmisPiid').value='';
    document.getElementById('hrmisUsername').value='';
    document.getElementById('hrmisPassword').value='';
    hrmisResetPasswordFields();
    HRMIS_SEARCH_FIELDS.forEach(id => document.getElementById(id).value='');
    hrmisEmployeeRows = [];
    document.getElementById('hrmisEmployeeResults').innerHTML =
        hrmisPickerRow('Search by ID number, first name or surname.');
    document.getElementById('hrmisEmployeePickWrap').classList.remove('d-none');
    document.getElementById('hrmisChosenEmployeeWrap').classList.add('d-none');
    document.getElementById('hrmisChangeEmployee').classList.remove('d-none');
    document.getElementById('hrmisUsername').disabled=false;
    document.getElementById('hrmisPwRequired').classList.remove('d-none');
    document.getElementById('hrmisPwConfirmRequired').classList.remove('d-none');
    document.getElementById('hrmisPwHint').textContent='Required for new accounts';
    document.getElementById('hrmisActiveYes').checked = true;
    await loadHrmisDepartments('');
    document.getElementById('hrmisModalTitle').textContent = 'Add New User';
    document.getElementById('hrmisModalSub').textContent = 'Create a new user account from HRMIS employee records';
    await loadHrmisRoles();
    new bootstrap.Modal(document.getElementById('hrmisModal')).show();
}
async function editHrmisUser(userId){
    const u = hrmisData.find(r => r.user_id === userId);
    if (!u) return showToast('Account not found.','error');
    document.getElementById('hrmisUserId').value = u.user_id;
    document.getElementById('hrmisPiid').value = u.piid;
    document.getElementById('hrmisUsername').value = u.user_name;
    document.getElementById('hrmisPassword').value = '';
    await loadHrmisDepartments(u.department || '');
    // Editing an existing login never re-picks the employee — they're
    // already linked via piid.
    document.getElementById('hrmisEmployeePickWrap').classList.add('d-none');
    document.getElementById('hrmisChosenEmployeeWrap').classList.remove('d-none');
    document.getElementById('hrmisChosenEmployeeName').textContent = u.full_name || u.user_name;
    // The directory list carries no personal details, and re-picking the person
    // on an existing login was not possible in the desktop form either — the
    // account is tied to that piid.
    document.getElementById('hrmisChosenEmployeeDetails').innerHTML = '';
    document.getElementById('hrmisChangeEmployee').classList.add('d-none');
    hrmisResetPasswordFields();
    document.getElementById('hrmisPwRequired').classList.add('d-none');
    document.getElementById('hrmisPwConfirmRequired').classList.add('d-none');
    document.getElementById('hrmisPwHint').textContent='Leave blank to keep current password';
    document.getElementById('hrmisModalTitle').textContent = 'Edit User';
    document.getElementById('hrmisModalSub').textContent = 'Update this account\u2019s credentials, department and role';
    await loadHrmisRoles();
    document.getElementById('hrmisRole').value = u.role_id;
    // Missing/undefined (a directory row loaded before the column existed)
    // reads as Active, matching the migration's own DEFAULT 1.
    document.getElementById(Number(u.is_active) === 0 ? 'hrmisActiveNo' : 'hrmisActiveYes').checked = true;
    new bootstrap.Modal(document.getElementById('hrmisModal')).show();
}
async function saveHrmisUser(){
    const userId = document.getElementById('hrmisUserId').value;
    const piid = document.getElementById('hrmisPiid').value;
    const username = document.getElementById('hrmisUsername').value.trim();
    const password = document.getElementById('hrmisPassword').value;
    if (!userId && !piid) return showToast('Please pick an employee first.','warning');
    // The same three checks Sys_User.vb ran before save(), in the same order:
    // username present, password present, password equals confirm.
    if (!username) return showToast('Username is required.','warning');
    if (!userId && !password) return showToast('Password is required for a new account.','warning');
    if (!hrmisCheckMatch()) {
        document.getElementById('hrmisPasswordConfirm').focus();
        return showToast('The two passwords do not match.','warning');
    }
    const payload = {
        piid: parseInt(piid)||undefined,
        user_id: parseInt(userId)||undefined,
        username, password,
        department: document.getElementById('hrmisDepartment').value.trim(),
        role_id: parseInt(document.getElementById('hrmisRole').value),
        is_active: document.getElementById('hrmisActiveYes').checked ? 1 : 0,
    };
    try{
        const res = await fetch('api/hrmis_users.php', {method: userId?'PUT':'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
        const data = await res.json();
        bootstrap.Modal.getInstance(document.getElementById('hrmisModal'))?.hide();
        showToast(data.message, data.success?'success':'error');
        if (data.success) loadHrmisUsers();
    } catch(err){
        bootstrap.Modal.getInstance(document.getElementById('hrmisModal'))?.hide();
        showToast('Request failed. Please try again.','error');
        console.error(err);
    }
}
async function deleteHrmisUser(userId, name){
    if (!confirm(`Delete the account "${name}"? This removes their login from every HRMIS module, not just this app.`)) return;
    try{
        const res = await fetch('api/hrmis_users.php?user_id='+userId, {method:'DELETE'});
        const data = await res.json();
        showToast(data.message, data.success?'success':'error');
        if (data.success) loadHrmisUsers();
    } catch(err){
        showToast('Request failed. Please try again.','error');
        console.error(err);
    }
}

loadHrmisUsers();

</script>
<?php include 'profile_modal.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
