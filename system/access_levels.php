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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
</head>
<body>
<div class="wrapper">
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <div class="page-title">Access Levels</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Access Levels</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
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
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const roleLabels = { branch_admin: 'Branch Administrator', branch_user: 'Branch User' };

async function loadMatrix() {
    const res  = await fetch('api/access_levels.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);

    document.getElementById('matrixHead').innerHTML =
        '<th>Module</th>' + data.roles.map(r => `<th class="text-center">${roleLabels[r] || r}</th>`).join('');

    document.getElementById('matrixBody').innerHTML = Object.entries(data.modules).map(([key, label]) => `
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
        </tr>`).join('');
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
