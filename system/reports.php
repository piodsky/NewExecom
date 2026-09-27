<?php
require_once 'config.php';
requireModule('reports');
$currentUser = getCurrentUser();
$activePage  = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports | <?= e(APP_NAME) ?></title>
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
                    <h2 class="page-title">Reports</h2>
                    <div class="text-muted small">Generate PDF-ready reports from the currently loaded module data.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel mb-3">
            <div class="panel-body d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label">From Date &amp; Time</label><input type="datetime-local" step="1" class="form-control form-control-sm" id="rFrom"></div>
                <div><label class="form-label">To Date &amp; Time</label><input type="datetime-local" step="1" class="form-control form-control-sm" id="rTo"></div>
                <button class="btn btn-primary-app btn-sm" onclick="loadReport()"><i class="fas fa-filter me-1"></i>Filter</button>
                <button class="btn btn-light btn-sm" onclick="exportCsv()"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div class="panel-title">Activity Report</div></div>
            <div class="panel-body">
                <p class="text-muted small mb-2">
                    Review activity for the selected date and time range (up to 500 records).
                    Use Report above to preview, print or download a PDF. Each module also
                    has its own Report button for its records.
                </p>
                <div class="table-responsive">
                <table class="app-table table" id="reportTable">
                    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Details</th></tr></thead>
                    <tbody id="reportBody"><tr><td colspan="5" class="text-muted small">Loading…</td></tr></tbody>
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
let rowsCache = [];
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
async function loadReport() {
    const params = new URLSearchParams();
    const from = document.getElementById('rFrom').value, to = document.getElementById('rTo').value;
    if (from) params.set('from', from);
    if (to)   params.set('to', to);
    const res  = await fetch('api/reports.php?' + params.toString());
    const data = await res.json();
    if (!data.success) return alert(data.message);
    window.moduleReportMeta = [
        ['From', from ? AppDateTime.format(from) : 'Any date'],
        ['To', to ? AppDateTime.format(to) : 'Any date'],
    ];
    rowsCache = data.rows;
    document.getElementById('reportBody').innerHTML = rowsCache.length ? rowsCache.map(r => `
        <tr>
            <td>${AppDateTime.format(r.created_at)}</td>
            <td>${escapeHtml(r.username || 'system')}</td>
            <td>${escapeHtml(r.action)}</td>
            <td>${escapeHtml(r.module || '—')}</td>
            <td>${escapeHtml(r.details)}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-muted small">No activity in this range.</td></tr>';
}
function exportCsv() {
    if (!rowsCache.length) return alert('Nothing to export.');
    const esc = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = ['When,User,Action,Module,Details']
        .concat(rowsCache.map(r => [r.created_at, r.username, r.action, r.module, r.details].map(esc).join(',')))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'report.csv';
    a.click();
}
loadReport();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
