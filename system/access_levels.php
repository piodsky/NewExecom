<?php
require_once 'config.php';
requireRole(['super_admin']);
$currentUser = getCurrentUser();
$activePage  = 'access_levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Levels | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
</head>
<body>
<?php include 'topnav.php'; ?>
<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle">Home</div>
                    <h2 class="page-title">Access Levels</h2>
                    <div class="text-muted small">Control which roles can open each system module.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Module Access by Role</div>
            </div>
            <div class="panel-body">
                <p class="text-muted small">Super Administrator always has full access and is not shown here.
                   A role with no explicit setting for a module defaults to <strong>allowed</strong>.</p>
                <div class="table-responsive">
                <table class="app-table table" id="matrixTable">
                    <thead><tr id="matrixHead"><th>Module</th></tr></thead>
                    <tbody id="matrixBody"></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
const roleLabels = { branch_admin: 'Branch Administrator', branch_user: 'Branch User' };

// Mirrors topnav.php's dropdown groupings so the matrix reads the same way
// the nav does. Any module key not listed here (e.g. a brand-new module
// added to ACCESS_MODULES before this map is updated) still shows up, under
// "Other" — nothing goes missing, it just isn't grouped yet.
const moduleSections = [
    { label: 'Internal Procurement', keys: ['po_internal', 'receiving_reports', 'payables', 'inventory', 'warehouse'] },
    { label: 'Branch Operations',    keys: ['stock_transfer', 'issuance'] },
    { label: 'Outgoing Sales',       keys: ['outgoing_rfq', 'outgoing_quotations', 'po_outgoing', 'delivery_reports'] },
    { label: 'Service & Billing',    keys: ['job_orders', 'billing', 'collections'] },
    { label: 'Reports',              keys: ['order_tracking', 'cost_profit_reports', 'reports'] },
    { label: 'Master Data',          keys: ['suppliers', 'clients', 'items', 'salesmen'] },
    { label: 'System',               keys: ['settings', 'system_guide'] },
];

async function loadMatrix() {
    const res  = await fetch('api/access_levels.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);

    document.getElementById('matrixHead').innerHTML =
        '<th>Module</th>' + data.roles.map(r => `<th class="text-center">${roleLabels[r] || r}</th>`).join('');

    const colCount = data.roles.length + 1;
    const moduleRow = (key, label) => `
        <tr>
            <td>${label}</td>
            ${data.roles.map(role => `
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox" role="switch"
                               ${data.matrix[role][key] ? 'checked' : ''}
                               onchange="toggleAccess('${role}', '${key}', this.checked)">
                    </div>
                </td>`).join('')}
        </tr>`;
    const sectionRow = (label) => `<tr class="matrix-section-row"><td colspan="${colCount}">${label}</td></tr>`;

    const remaining = new Set(Object.keys(data.modules));
    let html = '';
    moduleSections.forEach(section => {
        const present = section.keys.filter(k => remaining.has(k));
        if (!present.length) return;
        html += sectionRow(section.label);
        present.forEach(k => { html += moduleRow(k, data.modules[k]); remaining.delete(k); });
    });
    if (remaining.size) {
        html += sectionRow('Other');
        remaining.forEach(k => { html += moduleRow(k, data.modules[k]); });
    }
    document.getElementById('matrixBody').innerHTML = html;
}

async function toggleAccess(role, moduleKey, canAccess) {
    const res  = await fetch('api/access_levels.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ role, module_key: moduleKey, can_access: canAccess })
    });
    const data = await res.json();
    if (!data.success) { alert(data.message); loadMatrix(); }
}

loadMatrix();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
