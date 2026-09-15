<?php
require_once 'config.php';
requireSystemToolsAdmin();
$currentUser = getCurrentUser();
$activePage  = 'master_data';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Data | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.md-card { height: 100%; }
.md-add-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.md-add-row input { flex: 1 1 140px; min-width: 120px; }
.md-add-row select { flex: 1 1 160px; min-width: 140px; }
.md-add-row button { width: 42px; flex-shrink: 0; }
.md-tags { display: flex; flex-wrap: wrap; gap: .5rem; min-height: 2rem; }
/* Solid colors, not a tinted/low-opacity blend — a translucent pill washed
   out to near-unreadable against the card behind it; solid blue + white text
   stays legible regardless of what's behind it. */
.md-tag {
    display: inline-flex; align-items: center; gap: .5rem;
    background: var(--primary); color: #fff;
    border-radius: 999px; padding: .35rem .5rem .35rem .9rem;
    font-size: .82rem; font-weight: 600;
}
.md-tag button {
    border: none; background: rgba(255,255,255,.25); color: #fff;
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; cursor: pointer; padding: 0;
}
.md-tag button:hover { background: rgba(255,255,255,.4); }
.md-empty { color: var(--muted); font-size: .82rem; }
.md-table-wrap .md-parent-cell { color: var(--muted); font-size: .82rem; }
/* An entry whose parent is 0/missing. It is not merely untidy — a division
   with no department (12 live rows) is invisible to anything that walks the
   department -> division -> section chain, so it gets called out rather than
   shown as an empty cell. */
.md-table-wrap .md-parent-cell.md-orphan { color: #b45309; font-weight: 600; }

/* Supplier spelling cleanup */
.sc-group { border: 1px solid var(--border); border-radius: 10px; padding: .75rem .9rem; margin-bottom: .75rem; }
.sc-group + .sc-group { margin-top: 0; }
.sc-head { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: .5rem; }
.sc-keep { font-weight: 700; color: var(--navy); }
.sc-badge { font-size: .68rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
            border-radius: 999px; padding: .1rem .5rem; background: #e0e7ff; color: #3730a3; }
.sc-badge.usage { background: #fef3c7; color: #92400e; }
.sc-variant { display: flex; align-items: center; gap: .5rem; font-size: .84rem; padding: .12rem 0; }
.sc-variant .sc-count { min-width: 46px; text-align: right; color: var(--muted); font-variant-numeric: tabular-nums; }
/* The row that survives, versus the rows whose text gets overwritten. */
.sc-variant.is-keep .sc-name { font-weight: 600; color: var(--navy); }
.sc-variant.is-drop .sc-name { color: #b91c1c; text-decoration: line-through; text-decoration-color: rgba(185,28,28,.4); }
.sc-pick { font-size: .8rem; max-width: 320px; }

/* ── Long lists render as a scrollable table instead ── */
.md-table-wrap { max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; }
.md-table-wrap table { margin-bottom: 0; }

/* Solid colors on a table that always sits on a white card background, same
   reasoning as .md-tag above. One color per column gives the eye a fast
   visual anchor when scanning a long supplier list instead of every column
   reading as the same flat gray.
   Selectors are doubled up (.md-table-wrap .md-name-cell, not just
   .md-name-cell) because Bootstrap's own `.table > :not(caption) > * > *`
   rule outweighs a single class selector and was silently winning. */
.md-table-wrap .md-name-cell { font-weight: 600; color: var(--navy); }
.md-table-wrap .md-address-cell { color: #0891b2; font-size: .82rem; }
.md-table-wrap .md-contact-cell { color: #16a34a; font-size: .82rem; font-weight: 600; }
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
                <div class="page-title">Master Data</div>
                <div class="page-subtitle">Manage the shared picklists that Purchase Order autosuggest fields draw from</div>
            </div>
        </div>
        <?php include 'topbar.php'; ?>
    </div>

    <div class="content-area">
        <div class="row g-3" id="mdCards">
            <div class="col-12 text-center text-muted py-5" id="mdLoading">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="small mt-2">Loading master data…</div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Shared edit modal — one for every category (its fields toggle per
     openEditModal(), same idea as purchase_orders.php's single reused
     #poModal) rather than a separate modal per category. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="mdEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="mdEditModalTitle">Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mdEditKey">
        <input type="hidden" id="mdEditId">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Name</label>
          <input type="text" class="form-control" id="mdEditName" onkeydown="if(event.key==='Enter'){event.preventDefault();saveEdit();}">
        </div>
        <div class="mb-3 d-none" id="mdEditParentGroup">
          <label class="form-label small fw-semibold" id="mdEditParentLabel">Parent</label>
          <select class="form-select" id="mdEditParent"></select>
        </div>
        <div class="mb-3 d-none" id="mdEditExtraGroup">
          <label class="form-label small fw-semibold" id="mdEditExtraLabel">Extra</label>
          <input type="text" class="form-control" id="mdEditExtra" onkeydown="if(event.key==='Enter'){event.preventDefault();saveEdit();}">
        </div>
        <div class="mb-3 d-none" id="mdEditExtra2Group">
          <label class="form-label small fw-semibold" id="mdEditExtra2Label">Extra</label>
          <input type="text" class="form-control" id="mdEditExtra2" onkeydown="if(event.key==='Enter'){event.preventDefault();saveEdit();}">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" onclick="saveEdit()"><i class="fas fa-floppy-disk me-1"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg,type='success'){const id='t'+Date.now();const bg=type==='success'?'bg-success':type==='error'?'bg-danger':'bg-warning text-dark';document.getElementById('toastContainer').insertAdjacentHTML('beforeend',`<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);const t=new bootstrap.Toast(document.getElementById(id),{delay:4000});t.show();document.getElementById(id).addEventListener('hidden.bs.toast',()=>document.getElementById(id)?.remove());}
function escapeHtml(value){if(value===null||value===undefined)return '';return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

const CATEGORY_META = {
    // Long lists (dozens to hundreds of rows) render as a scrollable,
    // filterable table instead of a wrapped tag cloud — a wall of pills
    // gets unreadable fast at that size.
    categories:    { label: 'Categories',             icon: 'fa-tags',          placeholder: 'e.g. Catering Services and Accomodation', asTable: true },
    departments:   { label: 'Departments',           icon: 'fa-building',      placeholder: 'e.g. City Agriculture Office', asTable: true },
    divisions:     { label: 'Divisions',              icon: 'fa-sitemap',       placeholder: 'e.g. Administrative Division', asTable: true,
                     parentKey: 'departments', parentLabel: 'Department' },
    sections:      { label: 'Sections',               icon: 'fa-diagram-project', placeholder: 'e.g. Records Section', asTable: true,
                     parentKey: 'divisions', parentLabel: 'Division' },
    suppliers:     { label: 'Suppliers',              icon: 'fa-truck',         placeholder: 'e.g. ABC Trading Corp', asTable: true, cleanup: true,
                     hasExtra: true, extraLabel: 'Address', extraPlaceholder: 'Address, e.g. Malaybalay City',
                     hasExtra2: true, extra2Label: 'Contact Number', extra2Placeholder: 'Contact Number, e.g. 09171234567' },
    modes:         { label: 'Mode of Procurement',    icon: 'fa-file-contract', placeholder: 'e.g. Small Value Procurement' },
    po_users:      { label: 'PR Received By (Names)', icon: 'fa-user-check',    placeholder: 'e.g. Juan Dela Cruz',
                     hasExtra: true, extraLabel: 'Initials', extraPlaceholder: 'Initials, e.g. JDC', extraMax: 10 },
    units:         { label: 'Units of Issue',         icon: 'fa-ruler-combined', placeholder: 'e.g. box' },
    place_of_delivery: { label: 'Place of Delivery',  icon: 'fa-truck-fast',    placeholder: 'e.g. Pick Up', preserveOrder: true },
    signatories:   { label: 'Signatories',            icon: 'fa-signature',     placeholder: 'e.g. Juan Dela Cruz', hasExtra: true, extraLabel: 'Title', extraPlaceholder: 'Title, e.g. City Mayor' },
    remarks:       { label: 'Remarks',                icon: 'fa-comment-dots',  placeholder: 'e.g. Forwarded to CGSO' },
    working_days:  { label: 'Working Days',           icon: 'fa-calendar-days',  placeholder: 'e.g. 15', preserveOrder: true },
    // The value typed here is printed on the purchase order VERBATIM — the
    // print code appends no unit of its own — so the entry has to carry it:
    // "30 Calendar Days", "5 Working Days". An entry of just "30" prints as
    // just "30".
    payment_term:  { label: 'Payment Term',           icon: 'fa-money-check-dollar', placeholder: 'e.g. 5 Working Days', preserveOrder: true },
    inventory_classification: { label: 'Property Classification', icon: 'fa-boxes-stacked', placeholder: 'e.g. OFFICE EQUIPMENT AND FURNITURE', asTable: true },
    // Purpose is full free-text, not a picklist someone curates by hand —
    // there are ~11,000 distinct values across 26,000+ POs. The API sources
    // this from past POs themselves (top 300 by reuse) instead of a table,
    // so it's read-only here and has no add/remove controls.
    purposes:      { label: 'Purpose (most reused, from past POs)', icon: 'fa-bullseye', asTable: true, readOnly: true, wide: true },
};

// Renders the <option> list for a parent picker straight out of the data
// already loaded for that category — no extra request, and it stays correct
// when a department is added or renamed on the same screen.
function parentOptions(parentKey, selected) {
    const rows = categoryData[parentKey] || [];
    return '<option value="">— choose —</option>' + rows.map(r =>
        `<option value="${escapeHtml(r.id)}"${String(r.id) === String(selected) ? ' selected' : ''}>${escapeHtml(r.name)}</option>`
    ).join('');
}

function parentNameOf(parentKey, parentId) {
    if (!parentId || String(parentId) === '0') return '';
    const row = (categoryData[parentKey] || []).find(r => String(r.id) === String(parentId));
    // A parent id pointing at a row that is gone (3 live divisions, 1 section)
    // reads the same as having none — both mean "not reachable from the top".
    return row ? row.name : '';
}

// Adding or renaming a department has to show up in the Divisions picker
// immediately, otherwise the only way to use a value you just created is a
// page reload.
function refreshDependents(parentKey) {
    Object.entries(CATEGORY_META).forEach(([key, meta]) => {
        if (meta.parentKey !== parentKey) return;
        const sel = document.getElementById(`mdInputParent-${key}`);
        if (sel) {
            const keep = sel.value;
            sel.innerHTML = parentOptions(parentKey, keep);
        }
        renderTags(key);
    });
}

let categoryData = {};

function renderCard(key) {
    const meta = CATEGORY_META[key];
    const body = meta.asTable
        ? `<input type="text" class="form-control form-control-sm mb-2" placeholder="Filter…" oninput="filterTable('${key}', this.value)">
           <div class="md-table-wrap">
               <table class="app-table table table-sm table-hover mb-0"><tbody id="mdTags-${key}"></tbody></table>
           </div>`
        : `<div class="md-tags" id="mdTags-${key}"></div>`;
    // Mirrors the column width the API enforces, so the limit is visible while
    // typing rather than only on save.
    const extraMax = meta.extraMax ? ` maxlength="${meta.extraMax}"` : '';
    const extraInput = meta.hasExtra
        ? `<input type="text" class="form-control" id="mdInputExtra-${key}"${extraMax} placeholder="${meta.extraPlaceholder}" onkeydown="if(event.key==='Enter'){event.preventDefault();addValue('${key}');}">`
        : '';
    const extra2Input = meta.hasExtra2
        ? `<input type="text" class="form-control" id="mdInputExtra2-${key}" placeholder="${meta.extra2Placeholder}" onkeydown="if(event.key==='Enter'){event.preventDefault();addValue('${key}');}">`
        : '';
    const parentInput = meta.parentKey
        ? `<select class="form-select" id="mdInputParent-${key}" title="${meta.parentLabel}">${parentOptions(meta.parentKey)}</select>`
        : '';
    const addRow = meta.readOnly
        ? `<div class="text-muted small mb-2"><i class="fas fa-circle-info me-1"></i>Auto-collected from past purchase orders — not manually edited here.</div>`
        : `<div class="md-add-row">
                    <input type="text" class="form-control" id="mdInput-${key}" placeholder="${meta.placeholder}" onkeydown="if(event.key==='Enter'){event.preventDefault();addValue('${key}');}">
                    ${parentInput}
                    ${extraInput}
                    ${extra2Input}
                    <button type="button" class="btn btn-outline-primary" onclick="addValue('${key}')"><i class="fas fa-plus"></i></button>
                </div>`;
    return `
    <div class="${meta.wide ? 'col-12' : 'col-lg-6'}">
        <div class="panel md-card">
            <div class="panel-header">
                <span class="panel-title"><i class="fas ${meta.icon} me-2 text-primary"></i>${meta.label}</span>
                <span class="d-flex align-items-center gap-2">
                    ${meta.cleanup ? `<button type="button" class="btn btn-sm btn-outline-warning rounded-pill" onclick="openSupplierCleanup()"><i class="fas fa-broom me-1"></i>Clean up spellings</button>` : ''}
                    <span class="text-muted small" id="mdCount-${key}"></span>
                </span>
            </div>
            <div class="panel-body">
                ${addRow}
                ${body}
            </div>
        </div>
    </div>`;
}

function renderTags(key) {
    const meta = CATEGORY_META[key];
    const box = document.getElementById(`mdTags-${key}`);
    const rows = categoryData[key] || [];
    const countEl = document.getElementById(`mdCount-${key}`);
    if (countEl) countEl.textContent = rows.length ? `${rows.length} item${rows.length===1?'':'s'}` : '';

    if (!rows.length) {
        box.innerHTML = meta.asTable
            ? '<tr><td class="md-empty">Nothing added yet.</td></tr>'
            : '<span class="md-empty">Nothing added yet.</span>';
        return;
    }

    if (meta.readOnly) {
        box.innerHTML = rows.map(r => `
            <tr data-name="${escapeHtml(r.name).toLowerCase()}">
                <td>${escapeHtml(r.name)}</td>
                <td class="text-end text-muted small" style="width:70px;">${r.uses ? r.uses + '×' : ''}</td>
            </tr>
        `).join('');
    } else if (meta.asTable) {
        box.innerHTML = rows.map(r => {
            const parent = meta.parentKey ? parentNameOf(meta.parentKey, r.parent_id) : '';
            // The parent name is part of the filter key so typing a department
            // narrows the Divisions list to that department.
            return `
            <tr data-name="${escapeHtml(r.name + ' ' + parent).toLowerCase()}">
                <td class="md-name-cell">${escapeHtml(r.name)}</td>
                ${meta.parentKey ? `<td class="md-parent-cell${parent ? '' : ' md-orphan'}">${parent ? escapeHtml(parent) : 'no ' + meta.parentLabel.toLowerCase()}</td>` : ''}
                ${meta.hasExtra ? `<td class="md-address-cell">${r.extra ? `<i class="fas fa-location-dot me-1"></i>${escapeHtml(r.extra)}` : ''}</td>` : ''}
                ${meta.hasExtra2 ? `<td class="md-contact-cell">${r.extra2 ? `<i class="fas fa-phone me-1"></i>${escapeHtml(r.extra2)}` : ''}</td>` : ''}
                <td class="text-end" style="width:100px; white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-sm-action me-1" onclick="openEditModal('${key}','${r.id}')"><i class="fas fa-pen"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-sm-action" onclick="removeValue('${key}','${r.id}','${escapeHtml(r.name).replace(/'/g,"\\'")}')"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        }).join('');
    } else {
        box.innerHTML = rows.map(r => `
            <span class="md-tag">${escapeHtml(r.name)}${r.extra ? ' — ' + escapeHtml(r.extra) : ''}<button type="button" onclick="openEditModal('${key}','${r.id}')" title="Edit"><i class="fas fa-pen"></i></button><button type="button" onclick="removeValue('${key}','${r.id}','${escapeHtml(r.name).replace(/'/g,"\\'")}')" title="Remove"><i class="fas fa-times"></i></button></span>
        `).join('');
    }
}

function filterTable(key, query) {
    const q = query.trim().toLowerCase();
    document.querySelectorAll(`#mdTags-${key} tr`).forEach(tr => {
        tr.style.display = (tr.dataset.name || '').includes(q) ? '' : 'none';
    });
}

async function loadMasterData() {
    try {
        const res = await fetch('api/master_data.php');
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load master data.', 'error');
        categoryData = data.categories;
        document.getElementById('mdLoading').remove();
        document.getElementById('mdCards').innerHTML = Object.keys(CATEGORY_META).map(renderCard).join('');
        Object.keys(CATEGORY_META).forEach(renderTags);
    } catch (err) {
        showToast('Unable to load master data.', 'error');
        console.error(err);
    }
}

async function addValue(key) {
    const input = document.getElementById(`mdInput-${key}`);
    const name = input.value.trim();
    if (!name) return;
    const meta = CATEGORY_META[key];
    const extraInput = meta.hasExtra ? document.getElementById(`mdInputExtra-${key}`) : null;
    const extra = extraInput ? extraInput.value.trim() : '';
    const extra2Input = meta.hasExtra2 ? document.getElementById(`mdInputExtra2-${key}`) : null;
    const extra2 = extra2Input ? extra2Input.value.trim() : '';
    const parentInput = meta.parentKey ? document.getElementById(`mdInputParent-${key}`) : null;
    const parent = parentInput ? parentInput.value : '';
    if (parentInput && !parent) return showToast(`Choose the ${meta.parentLabel.toLowerCase()} this belongs to.`, 'warning');
    try {
        const res = await fetch('api/master_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category: key, name, extra, extra2, parent })
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to add.', 'error');
        input.value = '';
        if (extraInput) extraInput.value = '';
        if (extra2Input) extra2Input.value = '';
        categoryData[key] = categoryData[key] || [];
        categoryData[key].push({ id: data.id, name, extra, extra2, parent_id: parent });
        // Working Days/Payment Term have a deliberate seeded order — new
        // entries just go on the end. Everything else stays alphabetized.
        if (!CATEGORY_META[key].preserveOrder) {
            categoryData[key].sort((a, b) => a.name.localeCompare(b.name));
        }
        renderTags(key);
        refreshDependents(key);
        showToast('Added.');
    } catch (err) {
        showToast('Unable to add.', 'error');
        console.error(err);
    }
}

function openEditModal(key, id) {
    const meta = CATEGORY_META[key];
    const row = (categoryData[key] || []).find(r => String(r.id) === String(id));
    if (!row) return;
    document.getElementById('mdEditKey').value = key;
    document.getElementById('mdEditId').value = id;
    document.getElementById('mdEditModalTitle').textContent = `Edit ${meta.label.replace(/s$/, '')}`;
    document.getElementById('mdEditName').value = row.name || '';
    document.getElementById('mdEditParentLabel').textContent = meta.parentLabel || 'Parent';
    document.getElementById('mdEditParentGroup').classList.toggle('d-none', !meta.parentKey);
    document.getElementById('mdEditParent').innerHTML = meta.parentKey
        ? parentOptions(meta.parentKey, row.parent_id) : '';
    document.getElementById('mdEditExtraLabel').textContent = meta.extraLabel || 'Extra';
    document.getElementById('mdEditExtraGroup').classList.toggle('d-none', !meta.hasExtra);
    if (meta.extraMax) document.getElementById('mdEditExtra').setAttribute('maxlength', meta.extraMax);
    else document.getElementById('mdEditExtra').removeAttribute('maxlength');
    document.getElementById('mdEditExtra').value = row.extra || '';
    document.getElementById('mdEditExtra2Label').textContent = meta.extra2Label || 'Extra';
    document.getElementById('mdEditExtra2Group').classList.toggle('d-none', !meta.hasExtra2);
    document.getElementById('mdEditExtra2').value = row.extra2 || '';
    if (!window.mdEditModalInstance) window.mdEditModalInstance = new bootstrap.Modal(document.getElementById('mdEditModal'));
    window.mdEditModalInstance.show();
}

async function saveEdit() {
    const key = document.getElementById('mdEditKey').value;
    const id = document.getElementById('mdEditId').value;
    const name = document.getElementById('mdEditName').value.trim();
    if (!name) return showToast('Please enter a value.', 'warning');
    const extra = document.getElementById('mdEditExtra').value.trim();
    const extra2 = document.getElementById('mdEditExtra2').value.trim();
    const meta = CATEGORY_META[key];
    const parent = meta.parentKey ? document.getElementById('mdEditParent').value : '';
    if (meta.parentKey && !parent) return showToast(`Choose the ${meta.parentLabel.toLowerCase()} this belongs to.`, 'warning');
    try {
        const res = await fetch('api/master_data.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category: key, id, name, extra, extra2, parent })
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to update.', 'error');
        const row = (categoryData[key] || []).find(r => String(r.id) === String(id));
        if (row) { row.name = name; row.extra = extra; row.extra2 = extra2; if (meta.parentKey) row.parent_id = parent; }
        if (!CATEGORY_META[key].preserveOrder) {
            categoryData[key].sort((a, b) => a.name.localeCompare(b.name));
        }
        renderTags(key);
        refreshDependents(key);
        window.mdEditModalInstance.hide();
        showToast('Updated.');
    } catch (err) {
        showToast('Unable to update.', 'error');
        console.error(err);
    }
}

async function removeValue(key, id, name) {
    if (!confirm(`Remove "${name}"?`)) return;
    try {
        const res = await fetch(`api/master_data.php?category=${key}&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to remove.', 'error');
        categoryData[key] = (categoryData[key] || []).filter(r => String(r.id) !== String(id));
        renderTags(key);
        refreshDependents(key);
        showToast('Removed.');
    } catch (err) {
        showToast('Unable to remove.', 'error');
        console.error(err);
    }
}

// ── Supplier spelling cleanup ───────────────────────────────────────────────
// Shows every supplier that is on file under more than one spelling and
// rewrites the odd ones out to a single chosen spelling. See
// api/supplier_cleanup.php for why grouping is exact-after-normalisation
// rather than fuzzy.
let scGroups = [];

async function openSupplierCleanup() {
    if (!window.scModalInstance) window.scModalInstance = new bootstrap.Modal(document.getElementById('scModal'));
    document.getElementById('scList').innerHTML = '<div class="text-muted small">Loading…</div>';
    document.getElementById('scIntro').textContent = '';
    window.scModalInstance.show();
    await loadCleanup();
}

async function loadCleanup() {
    try {
        const res = await fetch('api/supplier_cleanup.php');
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load.', 'error');
        scGroups = data.groups;
        const s = data.summary;
        document.getElementById('scIntro').innerHTML = s.groups
            ? `<strong>${s.groups}</strong> supplier${s.groups === 1 ? ' is' : 's are'} on file under more than one spelling —
               <strong>${s.variants}</strong> spelling${s.variants === 1 ? '' : 's'} to retire across
               <strong>${s.changes}</strong> purchase order${s.changes === 1 ? '' : 's'}.
               Merging rewrites the supplier name on those purchase orders to the one you keep. Every run writes an
               undo file to <code>uploads/supplier_cleanup/</code> first, so a merge can be reversed.`
            : 'Nothing to clean up — every supplier is spelled one way.';
        document.getElementById('scMergeAll').classList.toggle('d-none', !s.groups);
        renderCleanup();
    } catch (err) {
        showToast('Unable to load supplier cleanup.', 'error');
        console.error(err);
    }
}

function renderCleanup() {
    const box = document.getElementById('scList');
    if (!scGroups.length) { box.innerHTML = '<div class="text-muted small">Nothing to clean up.</div>'; return; }
    box.innerHTML = scGroups.map(g => {
        const opts = g.variants.map(v =>
            `<option value="${escapeHtml(v.name)}"${v.name === g.target ? ' selected' : ''}>${escapeHtml(v.name)}</option>`).join('');
        return `
        <div class="sc-group" data-key="${escapeHtml(g.key)}" data-search="${escapeHtml(g.variants.map(v => v.name).join(' ')).toLowerCase()}">
            <div class="sc-head">
                <span class="sc-keep">${escapeHtml(g.target)}</span>
                <span class="sc-badge ${g.target_from === 'ref' ? '' : 'usage'}">${g.target_from === 'ref' ? 'in supplier list' : 'most used'}</span>
                <span class="text-muted small">${g.variants.length} spellings · ${g.changes} PO${g.changes === 1 ? '' : 's'} to update</span>
                <span class="ms-auto d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm sc-pick" onchange="repickTarget('${escapeHtml(g.key)}', this.value)">${opts}</select>
                    <button type="button" class="btn btn-sm btn-warning" onclick="mergeSupplierGroup('${escapeHtml(g.key)}')">Merge</button>
                </span>
            </div>
            ${g.variants.map(v => `
                <div class="sc-variant ${v.name === g.target ? 'is-keep' : 'is-drop'}">
                    <span class="sc-count">${v.pos}</span>
                    <span class="sc-name">${escapeHtml(v.name)}</span>
                </div>`).join('')}
        </div>`;
    }).join('');
    filterCleanup(document.getElementById('scFilter').value);
}

// Choosing a different spelling to keep only redraws that one group — the
// whole list is 146 blocks and rebuilding it loses the scroll position.
function repickTarget(key, target) {
    const g = scGroups.find(x => x.key === key);
    if (!g) return;
    g.target = target;
    g.changes = g.variants.filter(v => v.name !== target).reduce((n, v) => n + v.pos, 0);
    // target_from no longer describes a hand-picked value.
    g.target_from = 'usage';
    const el = document.querySelector(`.sc-group[data-key="${CSS.escape(key)}"]`);
    const scroll = document.querySelector('#scModal .modal-body').scrollTop;
    renderCleanup();
    document.querySelector('#scModal .modal-body').scrollTop = scroll;
    if (el) el.scrollIntoView({ block: 'nearest' });
}

function filterCleanup(query) {
    const q = (query || '').trim().toLowerCase();
    document.querySelectorAll('#scList .sc-group').forEach(el => {
        el.style.display = (el.dataset.search || '').includes(q) ? '' : 'none';
    });
}

async function postCleanup(body, confirmText) {
    if (!confirm(confirmText)) return;
    try {
        const res = await fetch('api/supplier_cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        // Read as text first, then parse. res.json() on a PHP fatal (which
        // comes back as an HTML error page) throws a SyntaxError that says
        // nothing about what actually went wrong, and the old catch below then
        // reported a bare "The merge did not go through." — leaving the real
        // reason only in the server log. Now the status and the start of the
        // body are shown, which is enough to act on.
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch {
            const detail = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
            console.error('supplier_cleanup returned non-JSON', res.status, raw);
            return showToast(
                `The server returned an error (HTTP ${res.status})${detail ? ': ' + detail : '.'}`,
                'error');
        }
        if (!data.success) return showToast(data.message || 'Nothing was changed.', 'error');
        showToast(data.message + (data.undo ? ` Undo file: ${data.undo}` : ''));
        await loadCleanup();
    } catch (err) {
        showToast('The merge did not go through: ' + (err.message || err), 'error');
        console.error(err);
    }
}

function mergeSupplierGroup(key) {
    const g = scGroups.find(x => x.key === key);
    if (!g) return;
    postCleanup({ key, target: g.target },
        `Rewrite ${g.changes} purchase order${g.changes === 1 ? '' : 's'} to "${g.target}"?`);
}

function mergeAllSuppliers() {
    const groups = scGroups.length;
    const changes = scGroups.reduce((n, g) => n + g.changes, 0);
    const targets = Object.fromEntries(scGroups.map(g => [g.key, g.target]));
    postCleanup({ all: true, targets },
        `Merge all ${groups} suppliers, rewriting ${changes} purchase orders?\n\n`
      + `Groups you have not looked at use the suggested spelling.`);
}

loadMasterData();
</script>
<!-- Supplier spelling cleanup. Its own modal rather than a page so it sits
     next to the Suppliers list it fixes, and so the main screen stays a
     picklist editor. -->
<div class="modal fade" id="scModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-broom me-2 text-warning"></i>Supplier spelling cleanup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="scIntro" class="text-muted small mb-3"></div>
        <input type="text" class="form-control form-control-sm mb-3" id="scFilter" placeholder="Filter suppliers…" oninput="filterCleanup(this.value)">
        <div id="scList"><div class="text-muted small">Loading…</div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-warning" id="scMergeAll" onclick="mergeAllSuppliers()">
          <i class="fas fa-wand-magic-sparkles me-1"></i> Merge all suggested
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include 'profile_modal.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
