<?php
require_once 'config.php';
// Same gate as PO Insights and Reports: this screen only reads, but what it
// reads is every contract's value, so it belongs behind the same door.
requireReportsAdmin();
requireModule('competitive_bidding');
$currentUser = getCurrentUser();
$activePage  = 'competitive_insights';

// The Head of the Procuring Entity, read from ref_signatory rather than typed
// in here. It is the same list the Competitive Bidding form's signatory
// picklist uses and the same name that prints on every Notice of Award, so
// when the office changes hands this page follows without a code edit.
$hope = ['name' => '', 'title' => 'City Mayor'];
try {
    $stmt = getPoDB()->query(
        "SELECT Name, Title FROM ref_signatory
         WHERE isDeleted = 0 AND Title LIKE '%Mayor%' ORDER BY ID LIMIT 1"
    );
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hope = ['name' => trim($row['Name']), 'title' => trim($row['Title'])];
    }
} catch (Throwable $e) { /* the strip degrades to the placeholder below */ }

// The portrait. Looked for by name rather than hard-coded to one extension, so
// dropping in a .png works as well as a .jpg. Absent, the strip still renders
// with an initials placeholder — a broken image icon on a report page reads as
// a fault in the report itself.
$hopePhoto = '';
foreach (['mayor.jpg', 'mayor.jpeg', 'mayor.png', 'mayor.webp'] as $candidate) {
    if (is_file(__DIR__ . '/image/' . $candidate)) { $hopePhoto = 'image/' . $candidate; break; }
}
$hopeInitials = '';
foreach (preg_split('/\s+/', $hope['name']) as $part) {
    if (preg_match('/^[A-Za-z]/', $part)) $hopeInitials .= strtoupper($part[0]);
}
$hopeInitials = substr($hopeInitials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Competitive Insights | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Same plain-div charts as PO Insights, and deliberately the same class names
   and shapes: the two screens sit next to each other in the nav and should
   read as one pair, not as two people's ideas of what a chart looks like. */
.ins-bars { display: flex; align-items: flex-end; gap: 6px; height: 200px; }
.ins-bar-col { flex: 1 1 0; display: flex; flex-direction: column; justify-content: flex-end;
               align-items: center; height: 100%; min-width: 0; }
/* Two bars per year, side by side — budget and award. A stacked bar would
   suggest the two add up to something, and they do not: one contains the
   other, and the gap between them is the whole story. */
.ins-bar-pair { display: flex; align-items: flex-end; gap: 2px; width: 100%; height: 100%; }
.ins-bar { flex: 1; border-radius: 4px 4px 0 0; min-height: 2px; transition: opacity .15s; }
.ins-bar:hover { opacity: .75; }
.ins-bar.budget { background: #cbd5e1; }
.ins-bar.award  { background: var(--primary); }
.ins-bar-label { font-size: .64rem; color: var(--muted); margin-top: .35rem; white-space: nowrap;
                 overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.ins-key { display: flex; gap: 1rem; font-size: .72rem; color: var(--muted); margin-top: .6rem; }
.ins-key span::before { content: ''; display: inline-block; width: 10px; height: 10px;
                        border-radius: 2px; margin-right: .35rem; vertical-align: middle; }
.ins-key .k-budget::before { background: #cbd5e1; }
.ins-key .k-award::before  { background: var(--primary); }

.ins-rank { display: block; padding: .5rem .6rem; border-radius: 8px; text-decoration: none;
            color: inherit; position: relative; overflow: hidden; margin-bottom: .3rem; }
.ins-rank:hover { background: #f1f5f9; color: inherit; }
.ins-rank-fill { position: absolute; inset: 0 auto 0 0; background: rgba(22,163,74,.12);
                 border-radius: 8px; pointer-events: none; }
/* A supplier whose contracts came in ABOVE budget still gets a bar — it is a
   magnitude — but not the green one, which would read as money saved. */
.ins-rank.over .ins-rank-fill { background: rgba(180,83,9,.14); }
.ins-rank-row { position: relative; display: flex; align-items: baseline; gap: .5rem; }
.ins-rank-name { font-size: .82rem; font-weight: 600; color: var(--text); flex: 1;
                 white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-rank-val { font-size: .82rem; font-weight: 700; color: #15803d; font-variant-numeric: tabular-nums; }
.ins-rank-sub { position: relative; font-size: .7rem; color: var(--muted); }

.ins-table { width: 100%; font-size: .82rem; margin: 0; }
.ins-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em;
                color: var(--muted); font-weight: 700; padding: .35rem .5rem; border-bottom: 1px solid var(--border); }
.ins-table td { padding: .42rem .5rem; border-bottom: 1px solid #f1f5f9; }
.ins-table tr:last-child td { border-bottom: 0; }
.ins-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
/* Six numeric columns do not fit a third-width panel at every window size, and
   a column quietly cut off the right edge is worse than a scrollbar. */
.ins-tablewrap { overflow-x: auto; }
/* The contract name is the only free-text column here and it is usually a full
   sentence. Given a floor it wraps to two or three lines; without one the
   numeric columns win the layout and it wraps to one word per line. */
.ins-contract { display: block; min-width: 190px; }
.ins-save { color: #15803d; font-weight: 700; }
.ins-over { color: #b45309; font-weight: 700; }

/* The headline. It is the answer to the one question this page was built for,
   so it gets the whole width and a treatment nothing else on the page has. */
.sv-hero { border: 1px solid #bbf7d0; background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
           border-radius: 14px; padding: 1.4rem 1.5rem; height: 100%;
           display: flex; flex-direction: column; justify-content: center; }
.sv-hero-lab { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
               color: #166534; }
.sv-hero-val { font-size: 2.4rem; font-weight: 800; color: #15803d; line-height: 1.1;
               font-variant-numeric: tabular-nums; }
.sv-hero-sub { font-size: .86rem; color: #166534; }
.sv-hero.over { border-color: #fde68a; background: linear-gradient(135deg, #fffbeb, #fef3c7); }
.sv-hero.over .sv-hero-lab, .sv-hero.over .sv-hero-sub { color: #92400e; }
.sv-hero.over .sv-hero-val { color: #b45309; }
.sv-hero.idle { border-color: var(--border); background: #f8fafc; }
.sv-hero.idle .sv-hero-lab, .sv-hero.idle .sv-hero-sub { color: var(--muted); }
.sv-hero.idle .sv-hero-val { color: var(--muted); font-size: 1.4rem; }
.sv-mini { background: rgba(255,255,255,.7); border: 1px solid rgba(22,101,52,.15);
           border-radius: 10px; padding: .6rem .75rem; height: 100%; }
.sv-mini-val { font-size: 1.15rem; font-weight: 800; color: #14532d; line-height: 1;
               font-variant-numeric: tabular-nums; }
.sv-mini-lab { font-size: .72rem; color: #166534; margin-top: .25rem; }

/* Coverage. Stated in the same breath as the savings figure, never below the
   fold: a total whose denominator is out of sight reads as if it covered
   everything. */
.sv-cover { height: 8px; border-radius: 6px; background: #e2e8f0; overflow: hidden; }
.sv-cover > i { display: block; height: 100%; background: #16a34a; }

.ins-stage { display: flex; align-items: center; gap: .6rem; padding: .4rem 0; }
.ins-stage-lab { font-size: .8rem; font-weight: 600; color: var(--text); flex: 0 0 210px; }
.ins-stage-bar { flex: 1; height: 10px; border-radius: 6px; background: #eef2f7; overflow: hidden; }
.ins-stage-bar > i { display: block; height: 100%; background: var(--primary); }
.ins-stage-val { flex: 0 0 92px; text-align: right; font-size: .76rem; font-weight: 700;
                 color: var(--navy); font-variant-numeric: tabular-nums; }

.ins-q { display: flex; gap: .7rem; padding: .55rem 0; border-bottom: 1px solid #f1f5f9; }
.ins-q:last-child { border-bottom: 0; }
.ins-q-count { flex: 0 0 74px; text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--navy); }
.ins-q-label { font-size: .84rem; font-weight: 600; color: var(--text); }
.ins-q-note { font-size: .74rem; color: var(--muted); }
.ins-q.ok .ins-q-count { color: #16a34a; }

.ins-period .btn { font-size: .78rem; }
.ins-scope { font-size: .72rem; color: var(--muted); font-weight: 600; }
.ins-hint { font-size: .74rem; color: var(--muted); }
.ins-warn { border: 1px solid #fde68a; background: #fffbeb; border-radius: 12px;
            padding: .9rem 1.1rem; font-size: .85rem; color: #92400e; }

/* Head of the Procuring Entity. This page is a governance report — the figure
   on it is "what the city saved" — so it carries the office it answers to, the
   way the printed documents this module produces carry a signatory block. */
/* 230px is not arbitrary. `cover` on a square portrait in a box wider than it
   is tall scales by WIDTH, so the rendered photo is as tall as the card is
   wide (~300px here) and the sitter's head comes out around 130px. Under about
   210px of card there is no arrangement that fits the whole head AND a caption
   — the scrim has to start above the chin. This is the height that lets both
   exist, and it sets the row height, so the savings headline stretches to
   match. */
.hope { position: relative; overflow: hidden; display: flex; align-items: flex-end;
        background: #fff; border: 1px solid var(--border); border-radius: 14px;
        min-height: 230px; height: 100%; padding: .85rem 1rem .9rem; }
/* Two layers of the same photograph, because one cannot survive both card
   shapes. This card is ~300×230 on a wide screen but ~875×230 once the columns
   stack, and `cover` scales to whichever edge overflows: at 875 wide it blows
   the portrait up until only the top of the sitter's head is in frame.
   So the sharp layer is fitted to HEIGHT (`auto 100%`), which shows the whole
   portrait at any card width and simply gets narrower relative to the card —
   and the blurred, lightened copy behind fills whatever is left over. */
.hope-photo { position: absolute; inset: 0; background-size: cover; background-position: 50% 20%;
              filter: blur(18px) saturate(.75) brightness(1.15);
              /* Scaled up so the blur's soft edge falls outside the card
                 instead of showing as a pale border inside it. */
              transform: scale(1.15); }
.hope-portrait { position: absolute; inset: 0; background-size: auto 100%;
                 background-position: 50% 0; background-repeat: no-repeat; }
/* Bottom-up, NOT left-to-right. The card is wider than it is tall, so a square
   portrait scaled to `cover` overflows vertically and not at all horizontally —
   which means a horizontal gradient cannot be steered away from the face. It
   whitens the middle of the card, and the middle of an official portrait is
   the face. Rising from the bottom, the scrim lands on the chest instead and
   the face is left alone.
   Opaque where the text is, so legibility does not depend on what tone the
   photograph happens to have there — that differs for every portrait. */
.hope-scrim { position: absolute; inset: 0;
              background: linear-gradient(180deg,
                  rgba(255,255,255,0)   56%,
                  rgba(255,255,255,.45) 68%,
                  rgba(255,255,255,.90) 79%,
                  rgba(255,255,255,.99) 88%); }
.hope-text { position: relative; width: 100%; }
.hope-initials { position: absolute; inset: 0; display: flex; align-items: center;
                 justify-content: center; font-weight: 800; font-size: 2rem; color: var(--primary);
                 background: #eef4ff; }
.hope-name { font-size: .92rem; font-weight: 800; color: var(--navy); line-height: 1.25;
             /* The name overlaps the softest part of the scrim, where a light
                shirt or a bright window can still show through. */
             text-shadow: 0 1px 2px rgba(255,255,255,.9); }
.hope-title { font-size: .78rem; font-weight: 700; color: var(--primary); }
.hope-role { font-size: .7rem; color: var(--muted); margin-top: .2rem; }
.hope-note { font-size: .68rem; color: #92400e; margin-top: .25rem; }
/* The portrait is fitted to height, so a phone-width card shows the same crop
   as a desktop one — only the blurred surround gets wider. Nothing to correct. */
@media print { .sidebar, .top-navbar, .ins-period, .ins-noprint { display: none !important; } }
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
                <div class="page-title">Competitive Insights</div>
                <div class="page-subtitle">What competitive bidding saved — read-only.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="ins-hint" id="insFooting">Reading the live bid data…</div>
            <div class="btn-group ins-period" role="group">
                <button class="btn btn-outline-secondary btn-sm active" data-period="all">All time</button>
                <button class="btn btn-outline-secondary btn-sm" data-period="ytd">This year</button>
                <button class="btn btn-outline-secondary btn-sm" data-period="12m">Last 12 months</button>
            </div>
        </div>

        <div id="insMigration" class="ins-warn mb-3 d-none"></div>

        <div class="row g-3 mb-3">
            <div class="col-xxl-9" id="insHero"></div>
            <div class="col-xxl-3">
                <div class="hope" role="img" aria-label="<?= e($hope['name'] !== '' ? $hope['name'] . ', ' . $hope['title'] : 'No signatory on file') ?>">
                    <?php if ($hopePhoto !== ''): ?>
                        <div class="hope-photo" style="background-image:url('<?= e($hopePhoto) ?>')"></div>
                        <div class="hope-portrait" style="background-image:url('<?= e($hopePhoto) ?>')"></div>
                        <div class="hope-scrim"></div>
                    <?php else: ?>
                        <div class="hope-initials"><?= e($hopeInitials ?: '—') ?></div>
                    <?php endif; ?>
                    <div class="hope-text">
                        <div class="hope-name"><?= e($hope['name'] !== '' ? 'HON. ' . $hope['name'] : 'No signatory on file') ?></div>
                        <div class="hope-title"><?= e($hope['title']) ?></div>
                        <div class="hope-role">Head of the Procuring Entity</div>
                        <?php if ($hopePhoto === ''): ?>
                            <div class="hope-note">Save the portrait as <code>system/image/mayor.jpg</code> to show it here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3" id="insTiles"></div>

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Budget vs award, by year</h6>
                        <span class="ins-scope">the gap is the saving</span></div>
                    <div class="panel-body">
                        <div class="ins-bars" id="insYears"></div>
                        <div class="ins-key">
                            <span class="k-budget">PR amount (approved budget)</span>
                            <span class="k-award">Contract amount (awarded)</span>
                        </div>
                        <div class="mt-3" id="insYearTable"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Where the savings came from</h6>
                        <span class="ins-scope" id="insSupplierScope"></span></div>
                    <div class="panel-body"><div id="insSuppliers"></div></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Biggest savings</h6>
                        <span class="ins-scope" id="insTopScope"></span></div>
                    <div class="panel-body" id="insTop"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Awarded above the budget</h6>
                        <span class="ins-scope">worth a second look</span></div>
                    <div class="panel-body" id="insOverruns"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">How far the paperwork has got</h6>
                        <span class="ins-scope" id="insStageScope"></span></div>
                    <div class="panel-body" id="insStages"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">How long each step takes</h6>
                        <span class="ins-scope">average, calendar days</span></div>
                    <div class="panel-body" id="insLead"></div>
                </div>
            </div>
        </div>

        <div class="panel mb-3">
            <div class="panel-header"><h6 class="panel-title">What is holding the savings figure back</h6>
                <a href="competitive_bidding.php" class="ins-hint text-decoration-none ins-noprint">Open Competitive Bidding <i class="fas fa-arrow-right ms-1"></i></a></div>
            <div class="panel-body" id="insQuality"></div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<?php include 'profile_modal.php'; ?>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let insPeriod = 'all';

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
const nf = n => Number(n || 0).toLocaleString('en-US');
const peso = n => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Short form for the hero and the bar labels: a billion pesos does not fit.
function pesoShort(n) {
    const v = Number(n || 0);
    // Sign in FRONT of the currency mark: "₱-900K" reads as a currency nobody
    // has heard of, where "-₱900K" reads as nine hundred thousand pesos short.
    const sign = v < 0 ? '-' : '', a = Math.abs(v);
    if (a >= 1e9) return sign + '₱' + (a / 1e9).toFixed(2) + 'B';
    if (a >= 1e6) return sign + '₱' + (a / 1e6).toFixed(1) + 'M';
    if (a >= 1e3) return sign + '₱' + Math.round(a / 1e3) + 'K';
    return sign + '₱' + a.toFixed(0);
}
const pct  = (a, b) => b ? (a / b * 100).toFixed(1) + '%' : '—';
const rate = v => v === null || v === undefined ? '—' : Number(v).toFixed(2) + '%';
const days = v => v === null || v === undefined ? '—' : Number(v).toFixed(1) + ' d';
// Drill-through: the bid list reads ?search= off the query string.
const bidLink = s => 'competitive_bidding.php?search=' + encodeURIComponent(s);

function tile(icon, colour, value, label, sub) {
    return `<div class="col-sm-6 col-xl-3"><div class="stat-card">
        <div class="stat-icon ${colour}"><i class="fas ${icon}"></i></div>
        <div style="min-width:0">
            <div class="stat-value">${value}</div>
            <div class="stat-label">${label}</div>
            ${sub ? `<div class="ins-hint">${sub}</div>` : ''}
        </div></div></div>`;
}

// ── The headline ────────────────────────────────────────────────────────
// Three states. "Nothing measurable yet" is a real answer and gets said
// plainly, rather than being drawn as ₱0.00 saved — which would be a claim,
// and a false one.
function renderHero(d) {
    const s = d.savings;
    const el = document.getElementById('insHero');
    const covered = s.scoped ? s.measured / s.scoped * 100 : 0;

    if (!s.measured) {
        el.innerHTML = `<div class="sv-hero idle">
            <div class="sv-hero-lab">Savings</div>
            <div class="sv-hero-val">Nothing to measure yet</div>
            <div class="sv-hero-sub mt-1">
                ${nf(s.scoped)} bid document${s.scoped === 1 ? '' : 's'} in scope, none of them carrying both a
                PR amount and a contract amount. Fill in the PR Amount on a bid and it will start counting here.
            </div></div>`;
        return;
    }

    const over = s.net < 0;
    el.innerHTML = `<div class="sv-hero ${over ? 'over' : ''}">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="sv-hero-lab">${over ? 'Awarded over budget' : 'Saved through competitive bidding'}</div>
                <div class="sv-hero-val">${peso(Math.abs(s.net))}</div>
                <div class="sv-hero-sub mt-1">
                    ${rate(Math.abs(s.rate))} ${over ? 'above' : 'below'} the approved budget &mdash;
                    ${peso(s.award)} awarded against ${peso(s.pr)} requested.
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-2">
                    <div class="col-4"><div class="sv-mini">
                        <div class="sv-mini-val">${pesoShort(s.under_val)}</div>
                        <div class="sv-mini-lab">saved on ${nf(s.under_n)} bid${s.under_n === 1 ? '' : 's'} under budget</div>
                    </div></div>
                    <div class="col-4"><div class="sv-mini">
                        <div class="sv-mini-val">${pesoShort(s.over_val)}</div>
                        <div class="sv-mini-lab">over on ${nf(s.over_n)} bid${s.over_n === 1 ? '' : 's'} above budget</div>
                    </div></div>
                    <div class="col-4"><div class="sv-mini">
                        <div class="sv-mini-val">${covered.toFixed(0)}%</div>
                        <div class="sv-mini-lab">of bids measurable</div>
                        <div class="sv-cover mt-2"><i style="width:${Math.max(2, covered)}%"></i></div>
                    </div></div>
                </div>
                <div class="sv-hero-sub mt-2">
                    Computed over the ${nf(s.measured)} of ${nf(s.scoped)} bid documents that carry both figures.
                    ${s.unmeasured ? nf(s.unmeasured) + ' more have no PR amount on file and are excluded — they are not counted as zero.' : ''}
                </div>
            </div>
        </div></div>`;
}

function renderTiles(d) {
    document.getElementById('insTiles').innerHTML =
        tile('fa-gavel', 'blue', nf(d.period.bids), 'Bid documents in scope',
             nf(d.scale.awarded) + ' awarded all time') +
        tile('fa-file-signature', 'green', pesoShort(d.savings.award), 'Awarded value, measured',
             nf(d.scale.lines) + ' item lines on file') +
        tile('fa-piggy-bank', 'teal', rate(d.savings.rate), 'Average saving rate',
             'against ' + pesoShort(d.savings.pr) + ' of approved budget') +
        tile('fa-stopwatch', 'amber', days(d.cycle), 'PR to Notice to Proceed',
             'average full cycle, where both dates exist');
}

function renderYears(d) {
    const el = document.getElementById('insYears');
    if (!d.years.length) { el.innerHTML = '<div class="ins-hint">No dated bids in this period.</div>'; return; }
    const max = Math.max(...d.years.map(y => Math.max(y.pr, y.award)), 1);
    el.innerHTML = d.years.map(y => `
        <div class="ins-bar-col">
            <div class="ins-bar-pair">
                <div class="ins-bar budget" style="height:${Math.max(2, y.pr / max * 100)}%"
                     title="${y.y} budget: ${peso(y.pr)} over ${nf(y.measured)} measurable bids"></div>
                <div class="ins-bar award" style="height:${Math.max(2, y.award / max * 100)}%"
                     title="${y.y} awarded: ${peso(y.award)} over ${nf(y.bids)} bids"></div>
            </div>
            <div class="ins-bar-label">${y.y}</div>
        </div>`).join('');

    document.getElementById('insYearTable').innerHTML = `<div class="ins-tablewrap"><table class="ins-table">
        <thead><tr><th>Year</th><th class="ins-num">Bids</th><th class="ins-num">Measurable</th>
            <th class="ins-num">Budget</th><th class="ins-num">Awarded</th>
            <th class="ins-num">Saved</th><th class="ins-num">Rate</th></tr></thead>
        <tbody>${d.years.map(y => `<tr>
            <td>${y.y}</td>
            <td class="ins-num">${nf(y.bids)}</td>
            <td class="ins-num">${nf(y.measured)}</td>
            <td class="ins-num">${y.pr ? peso(y.pr) : '—'}</td>
            <td class="ins-num">${peso(y.award)}</td>
            <td class="ins-num ${y.saved < 0 ? 'ins-over' : 'ins-save'}">${y.measured ? peso(y.saved) : '—'}</td>
            <td class="ins-num">${rate(y.rate)}</td></tr>`).join('')}</tbody></table></div>
        <div class="ins-hint mt-2">A year's saving is worked out from that year's measurable bids only,
            so a year with patchy PR amounts shows a small saving on a small base rather than a wrong
            saving on the whole year's award value.</div>`;
}

function renderSuppliers(d) {
    const list = d.suppliers;
    const top = list[0] ? Math.abs(list[0].saved) : 1;
    document.getElementById('insSuppliers').innerHTML = list.map((r, i) => `
        <a class="ins-rank ${r.saved < 0 ? 'over' : ''}" href="${bidLink(r.name)}" title="Show these in the bid list">
            <div class="ins-rank-fill" style="width:${Math.max(2, Math.abs(r.saved) / top * 100)}%"></div>
            <div class="ins-rank-row">
                <span class="ins-rank-name">${i + 1}. ${escapeHtml(r.name)}</span>
                <span class="ins-rank-val ${r.saved < 0 ? 'ins-over' : ''}">${pesoShort(r.saved)}</span>
            </div>
            <div class="ins-rank-sub">${nf(r.bids)} bid${r.bids === 1 ? '' : 's'} ·
                ${pesoShort(r.award)} awarded against ${pesoShort(r.pr)} · ${rate(r.rate)}</div>
        </a>`).join('') || '<div class="ins-hint">No measurable bids in this period.</div>';
}

function savingsTable(rows, over) {
    if (!rows.length) {
        return `<div class="ins-hint">${over
            ? 'Nothing was awarded above its approved budget in this period.'
            : 'No measurable savings in this period.'}</div>`;
    }
    return `<div class="ins-tablewrap"><table class="ins-table">
        <thead><tr><th>Bid</th><th>Contract</th><th class="ins-num">Budget</th>
            <th class="ins-num">Awarded</th><th class="ins-num">${over ? 'Over' : 'Saved'}</th>
            <th class="ins-num">Rate</th></tr></thead>
        <tbody>${rows.map(r => `<tr>
            <td><a href="competitive_bidding.php?bid_number=${encodeURIComponent(r.bid)}">${escapeHtml(r.bid)}</a></td>
            <td><span class="ins-contract">${escapeHtml(r.name || '—')}
                <span class="ins-hint">${escapeHtml(r.supplier || '')}</span></span></td>
            <td class="ins-num">${peso(r.pr)}</td>
            <td class="ins-num">${peso(r.award)}</td>
            <td class="ins-num ${over ? 'ins-over' : 'ins-save'}">${peso(Math.abs(r.saving))}</td>
            <td class="ins-num">${rate(r.rate)}</td></tr>`).join('')}</tbody></table></div>`;
}

function renderStages(d) {
    document.getElementById('insStages').innerHTML = d.stages.map(s => `
        <div class="ins-stage">
            <div class="ins-stage-lab">${escapeHtml(s.label)}</div>
            <div class="ins-stage-bar"><i style="width:${s.total ? (s.count / s.total * 100) : 0}%"></i></div>
            <div class="ins-stage-val">${nf(s.count)} · ${pct(s.count, s.total)}</div>
        </div>`).join('') + `<div class="ins-hint mt-2">
            Each step is a date on the record, so "reached" means somebody entered that date.
            A step that drops sharply is usually a step nobody is recording, not a step nobody is doing.</div>`;
}

function renderLead(d) {
    document.getElementById('insLead').innerHTML = `<table class="ins-table">
        <thead><tr><th>Step</th><th class="ins-num">Average</th></tr></thead>
        <tbody>${d.lead.map(l => `<tr>
            <td>${escapeHtml(l.label)}</td>
            <td class="ins-num">${days(l.days)}</td></tr>`).join('')}
            <tr><td><strong>PR raised → Notice to Proceed</strong></td>
                <td class="ins-num"><strong>${days(d.cycle)}</strong></td></tr></tbody></table>
        <div class="ins-hint mt-2">Averaged over the bids where both ends of the step have a date.
            A step with few dates on file averages over few bids — the count is not the same for every row.</div>`;
}

function renderQuality(d) {
    document.getElementById('insQuality').innerHTML = d.quality.map(q => `
        <div class="ins-q ${q.count ? '' : 'ok'}">
            <div class="ins-q-count">${nf(q.count)}</div>
            <div style="min-width:0">
                <div class="ins-q-label">${escapeHtml(q.label)}</div>
                <div class="ins-q-note">${escapeHtml(q.note)}</div>
            </div>
        </div>`).join('');
}

async function loadInsights() {
    document.getElementById('insFooting').textContent = 'Reading the live bid data…';
    try {
        const res  = await fetch('api/competitive_insights.php?period=' + encodeURIComponent(insPeriod));
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to load insights.');

        // The savings half of this page depends on a column that may not be
        // there yet. Say so once, at the top, instead of leaving every figure
        // silently at zero and letting somebody quote it.
        const mig = document.getElementById('insMigration');
        mig.classList.toggle('d-none', !!data.has_pr_amount);
        if (!data.has_pr_amount) {
            mig.innerHTML = '<strong>PR amounts are not stored yet.</strong> Run '
                + '<code>database/cb_pr_amount.sql</code> against the HRMIS server to add the '
                + 'PR Amount column, then enter the approved budget on each bid. Until then every '
                + 'savings figure below reads as nothing to measure — which is the truth, not a fault.';
        }

        renderHero(data);
        renderTiles(data);
        renderYears(data);
        renderSuppliers(data);
        document.getElementById('insTop').innerHTML = savingsTable(data.top, false);
        document.getElementById('insOverruns').innerHTML = savingsTable(data.overruns, true);
        renderStages(data);
        renderLead(data);
        renderQuality(data);

        const scope = data.period.label.toLowerCase();
        ['insSupplierScope', 'insTopScope', 'insStageScope'].forEach(id =>
            document.getElementById(id).textContent = scope);
        document.getElementById('insFooting').textContent =
            `${nf(data.period.bids)} bid documents in scope (${scope}) · `
            + `${nf(data.scale.bids)} on file, ${nf(data.scale.awarded)} awarded, ${nf(data.scale.locked)} locked `
            + `· read straight from the live data, nothing cached.`;
    } catch (err) {
        document.getElementById('insFooting').textContent = 'Unable to load insights.';
        console.error(err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ins-period .btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ins-period .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            insPeriod = btn.dataset.period;
            loadInsights();
        });
    });
    loadInsights();
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
