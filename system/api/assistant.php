<?php
// Office assistant — the brain behind the animated avatar on the dashboard.
//
// WHAT THIS IS, PLAINLY: intent matching over this system's own data. It is
// not a language model and does not pretend to be one. There is no API key
// here, nothing is sent anywhere, and every figure it quotes comes from a
// query in this file — so it can be wrong about what you MEANT, but it cannot
// be wrong about the numbers, which is the right way round for a system that
// COA audits.
//
// TO PUT A REAL MODEL BEHIND IT LATER: everything routes through
// assistantAnswer(), which takes the user's text and returns a reply array.
// Swap that one function for an HTTP call and the avatar, the animation
// states, the chat UI and the suggestion chips all keep working unchanged.
// Keep the data lookups below as the model's tools rather than throwing them
// away — they are what makes the answers true.
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user   = getCurrentUser();
$access = getEffectiveModuleAccess($user['id'] ?? null, $user['role'] ?? 'user');

// ── Who the assistant is talking to ─────────────────────────────────────────
// First name only. "Good morning, TEOFILO ARANAIDO PIODOS JR" is not a
// greeting, it is a roll call.
function assistantFirstName(array $user): string {
    $full = trim((string)($user['full_name'] ?? ''));
    if ($full === '') return '';
    // Names are stored surname-first in places ("DELA CRUZ, JUAN"), so take
    // what follows the comma when there is one.
    if (str_contains($full, ',')) {
        $after = trim(substr($full, strpos($full, ',') + 1));
        if ($after !== '') $full = $after;
    }
    $first = strtok($full, ' ');
    // Title case: the source is all-caps, and SHOUTING at someone is not warm.
    return $first ? mb_convert_case($first, MB_CASE_TITLE, 'UTF-8') : '';
}

// ── Time of day ─────────────────────────────────────────────────────────────
// Server time, which is the office's clock — the same rule the sidebar
// illustration uses, kept identical here on purpose so the two never disagree
// on screen.
function assistantDaypart(): string {
    $h = (int)date('G');
    return $h < 12 ? 'morning' : ($h < 18 ? 'afternoon' : 'evening');
}

function assistantGreeting(string $name): string {
    $part = assistantDaypart();
    $who  = $name !== '' ? ', ' . $name : '';
    return match ($part) {
        'morning'   => "Good morning{$who}! 👋 How's your day going so far?",
        'afternoon' => "Good afternoon{$who}! How's everything going today?",
        default     => "Good evening{$who}! How was your day?",
    };
}

// The follow-up question. Rotated by day so the assistant does not open with
// the same sentence every single morning for a year, but is stable within a
// day so it does not feel twitchy on a page refresh.
function assistantOpener(): string {
    $lines = [
        'What would you like to work on today?',
        'Need help with something?',
        'Would you like me to check your pending tasks?',
        'Can I help you find something?',
        'What are we working on today?',
    ];
    return $lines[((int)date('z')) % count($lines)];
}

/* ────────────────────────────────────────────────────────────────────────────
   The data lookups. Each one is a small bounded query, run ONLY when the
   matched intent needs it — the assistant is called once per message typed,
   and running the whole dashboard's worth of SQL to answer "hello" would make
   a friendly avatar into a load problem.

   Every one is wrapped: a blip on the HRMIS server should make the assistant
   say it cannot reach the records, not return a 500 into a chat bubble.
   ──────────────────────────────────────────────────────────────────────────── */

function assistantPending(PDO $db, array $access): array {
    $out = [];
    if ($access['purchase_orders'] ?? true) {
        $out['po_unapproved'] = (int)$db->query(
            "SELECT COUNT(*) FROM tbl_purchase_order
             WHERE isDeleted = 0 AND (Date_Approved_PO IS NULL OR Date_Approved_PO = '')"
        )->fetchColumn();
    }
    if ($access['inspection'] ?? true) {
        // Bounded to this month, exactly like the Inspection screen's own
        // "Awaiting" tile — an all-time count would report thousands of
        // historic POs nobody is going to inspect now.
        $out['awaiting_inspection'] = (int)$db->query(
            "SELECT COUNT(*) FROM tbl_purchase_order po
             WHERE po.isDeleted = 0 AND TRIM(po.PO_Number) <> ''
               AND po.Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')
               AND NOT EXISTS (SELECT 1 FROM tbl_inspection ti
                               WHERE ti.PO_Number = po.PO_Number AND ti.isDeleted = 0)"
        )->fetchColumn();
    }
    if ($access['competitive_bidding'] ?? true) {
        try {
            // "Not yet awarded" means the Notice of Award has no date on it —
            // the same rule the Competitive Bidding screen's own Draft chip
            // uses. There is no Status column on that table, and inventing one
            // here would put a number on screen that disagreed with the number
            // on the screen it came from.
            $out['draft_bids'] = (int)$db->query(
                "SELECT COUNT(*) FROM tbl_competitive_bidding
                 WHERE isDeleted = 0
                   AND NOA_Date IS NULL"
            )->fetchColumn();
        } catch (Throwable $e) {
            // The bidding tables are this module's own and may not be there on
            // an older deployment. Silently omit rather than fail the answer.
        }
    }
    return $out;
}

function assistantMonthFigures(PDO $db): array {
    $count = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_purchase_order
         WHERE isDeleted = 0
           AND Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')
           AND Date_Created <  DATE_FORMAT(NOW(), '%Y-%m-01') + INTERVAL 1 MONTH"
    )->fetchColumn();
    // Summed with isDeleted = 0 on the ITEM rows too: editing a PO retires its
    // old lines and inserts new ones, so an unfiltered SUM counts every
    // revision the PO has ever had.
    $amount = (float)$db->query(
        "SELECT COALESCE(SUM(pd.Total_Cost), 0)
         FROM tbl_purchase_order_items_details pd
         INNER JOIN tbl_purchase_order po ON po.PO_Number = pd.PO_Number
         WHERE pd.isDeleted = 0 AND po.isDeleted = 0
           AND po.Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')
           AND po.Date_Created <  DATE_FORMAT(NOW(), '%Y-%m-01') + INTERVAL 1 MONTH"
    )->fetchColumn();
    return ['count' => $count, 'amount' => $amount];
}

function assistantRecent(PDO $db): array {
    return fixLegacyEncodingDeep($db->query(
        "SELECT PO_Number AS po_number, Supplier_Name AS supplier_name, Date_Created AS date_created
         FROM tbl_purchase_order
         WHERE isDeleted = 0 AND Date_Created < CURDATE() + INTERVAL 1 DAY
         ORDER BY Date_Created DESC LIMIT 3"
    )->fetchAll(PDO::FETCH_ASSOC));
}

// ── Where the assistant can send you ────────────────────────────────────────
// Filtered by the SAME access map the sidebar uses, so it never offers a door
// the person cannot open. Offering a link that lands on "access denied" is
// worse than not offering it.
function assistantDestinations(array $access): array {
    $all = [
        'purchase_orders'     => ['Purchase Orders',      'purchase_orders.php',     ['purchase order', 'po', 'orders']],
        'inspection'          => ['Inspection & Acceptance', 'inspection.php',       ['inspection', 'inspect', 'delivery', 'acceptance', 'accept']],
        'competitive_bidding' => ['Competitive Bidding',  'competitive_bidding.php', ['bidding', 'bid', 'competitive', 'contract']],
        'document_tracking'   => ['Document Tracking',    'document_tracking.php',   ['tracking', 'dts', 'document']],
        'reports'             => ['Reports',              'reports.php',             ['report', 'reports']],
        'po_insights'         => ['PO Insights',          'po_insights.php',         ['insight', 'insights', 'analytics']],
        'master_data'         => ['Master Data',          'master_data.php',         ['master data', 'suppliers', 'signatories', 'remarks']],
    ];
    $out = [];
    foreach ($all as $key => $row) {
        if ($access[$key] ?? true) $out[$key] = $row;
    }
    return $out;
}

function assistantMoney(float $n): string {
    return '₱' . number_format($n, 2);
}

/* ────────────────────────────────────────────────────────────────────────────
   Tracking a document.

   This is the question the office actually asks — "where is my PO", "has it
   been released yet" — and answering it is why the assistant is worth having
   at all. Three document families, one trail each:

       Purchase Order        created → approved → released to supplier
                             → COA received → inspected
       Competitive Bidding   bid opened → awarded → award received
                             → performance bond → contract signed
                             → notice to proceed
       Inspection            recorded → invoice on file → accepted by

   Every stage is a date column that already exists and that the Additional
   Details panel on each screen already maintains. Nothing is inferred: a stage
   is done when its date is filled in, and pending when it is not. There is no
   status field anywhere in this schema, and inventing one here would put a
   word on screen that the screen it came from would disagree with.
   ──────────────────────────────────────────────────────────────────────────── */

// The legacy date columns on tbl_purchase_order are VARCHAR, and after twenty
// years of hand entry they hold blanks, '0000-00-00', and the odd unparseable
// string. Anything that does not survive strtotime is treated as "not set" —
// which is the truth, because a date nobody can read is a date nobody recorded.
function assistantDateish($v): ?string {
    $v = trim((string)$v);
    if ($v === '' || str_starts_with($v, '0000')) return null;
    $ts = strtotime($v);
    return $ts ? date('j M Y', $ts) : null;
}

// Pulls a document number out of free text, so "is PO-sample2026 released yet?"
// works without the person having to phrase anything a particular way.
// Ordered longest-pattern-first: a bid number contains something that looks
// like a bare PR number, so testing the bare form first would misroute it.
function assistantDocToken(string $text): ?string {
    $patterns = [
        '/\bPO-[A-Za-z0-9][A-Za-z0-9-]*/i',        // PO-sys2026826332, PO-20228284sys
        '/\bPR-[A-Za-z0-9][A-Za-z0-9-]*/i',        // PR-sample2026
        '/\b\d{4}-\d{2,4}(?:-[A-Za-z0-9]+)?\b/',  // 2026-055-SAMPLE, 2026-130
        '/\b\d{7,}\b/',                  // bare PR numbers: 20261718
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $text, $m)) return $m[0];
    }
    return null;
}

// One stage of a trail. `done` drives the tick or the dash in the reply.
function assistantStage(string $label, ?string $date): array {
    return ['label' => $label, 'date' => $date, 'done' => $date !== null];
}

function assistantTrackPo(PDO $db, string $token): ?array {
    // Matched on either number, because half the office knows a job by its PR.
    $stmt = $db->prepare(
        'SELECT PO_Number, PR_Number, Supplier_Name, Purpose, Date_Created,
                Date_Approved_PO, Date_Received_Supplier, Date_COA_Received,
                Print_PO, Remarks
         FROM tbl_purchase_order
         WHERE isDeleted = 0 AND (PO_Number = ? OR PR_Number = ?) LIMIT 1'
    );
    $stmt->execute([$token, $token]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$po) return null;
    $po = fixLegacyEncodingDeep($po);

    // The inspection is a separate module but the same piece of paperwork's
    // life, so it belongs on the end of this trail rather than in an answer
    // the person has to go and ask for separately.
    $insp = null;
    try {
        $q = $db->prepare(
            'SELECT Date_Inspected, Invoice_Number, Accepted_by FROM tbl_inspection
             WHERE PO_Number = ? AND isDeleted = 0 ORDER BY I_ID DESC LIMIT 1'
        );
        $q->execute([$po['PO_Number']]);
        $insp = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { /* inspection is optional on older deployments */ }

    $amount = (float)$db->query(
        'SELECT COALESCE(SUM(Total_Cost), 0) FROM tbl_purchase_order_items_details
         WHERE isDeleted = 0 AND PO_Number = ' . $db->quote($po['PO_Number'])
    )->fetchColumn();

    return [
        'kind'     => 'Purchase Order',
        'number'   => $po['PO_Number'],
        'subtitle' => trim(($po['Supplier_Name'] ?: 'no supplier on file')
                    . ($po['PR_Number'] ? ' · PR ' . $po['PR_Number'] : '')),
        'amount'   => $amount,
        'purpose'  => $po['Purpose'],
        'remarks'  => trim((string)$po['Remarks']),
        'locked'   => (int)$po['Print_PO'] === 1,
        'stages'   => [
            assistantStage('Purchase order raised', assistantDateish($po['Date_Created'])),
            assistantStage('Approved',              assistantDateish($po['Date_Approved_PO'])),
            assistantStage('Released to supplier',  assistantDateish($po['Date_Received_Supplier'])),
            assistantStage('COA received',          assistantDateish($po['Date_COA_Received'])),
            assistantStage('Delivery inspected',    $insp ? assistantDateish($insp['Date_Inspected']) : null),
        ],
        'extra'    => $insp && trim((string)$insp['Accepted_by']) !== ''
                    ? 'Accepted by ' . $insp['Accepted_by'] : '',
        'go'       => 'purchase_orders.php?po_number=' . rawurlencode($po['PO_Number']) . '&view=1',
        'goLabel'  => 'Open this PO',
    ];
}

function assistantTrackBid(PDO $db, string $token): ?array {
    try {
        $stmt = $db->prepare(
            'SELECT Bid_Number, Contract_Number, Contract_Name, Supplier_Name, Contract_Amount,
                    Bid_Date, NOA_Date, Date_Received_NOA, Date_Performance_Bond,
                    Contract_Date, NTP_Date, Remarks
             FROM tbl_competitive_bidding
             WHERE isDeleted = 0 AND (Bid_Number = ? OR Contract_Number = ?) LIMIT 1'
        );
        $stmt->execute([$token, $token]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;   // module tables not present
    }
    if (!$b) return null;
    $b = fixLegacyEncodingDeep($b);

    return [
        'kind'     => 'Bid Document',
        'number'   => $b['Bid_Number'],
        'subtitle' => trim(($b['Contract_Name'] ?: 'no contract name')
                    . ($b['Supplier_Name'] ? ' · ' . $b['Supplier_Name'] : '')),
        'amount'   => (float)$b['Contract_Amount'],
        'purpose'  => '',
        'remarks'  => trim((string)$b['Remarks']),
        'locked'   => false,
        'stages'   => [
            assistantStage('Bid opened',            assistantDateish($b['Bid_Date'])),
            assistantStage('Notice of Award issued', assistantDateish($b['NOA_Date'])),
            assistantStage('Award received by supplier', assistantDateish($b['Date_Received_NOA'])),
            assistantStage('Performance security posted', assistantDateish($b['Date_Performance_Bond'])),
            assistantStage('Contract signed',       assistantDateish($b['Contract_Date'])),
            assistantStage('Notice to Proceed',     assistantDateish($b['NTP_Date'])),
        ],
        'extra'    => $b['Contract_Number'] ? 'Contract no. ' . $b['Contract_Number'] : '',
        'go'       => 'competitive_bidding.php?bid_number=' . rawurlencode($b['Bid_Number']),
        'goLabel'  => 'Open this bid',
    ];
}

// Renders a trail as the assistant's reply. Ticks for what is done, a dash for
// what is not, and one plain sentence at the end naming the NEXT step — which
// is the actual answer to "where is it", as opposed to a list to interpret.
function assistantRenderTrail(array $t): array {
    $lines = [$t['kind'] . ' ' . $t['number'], $t['subtitle']];
    if ($t['amount'] > 0) $lines[] = '₱' . number_format($t['amount'], 2);
    $lines[] = '';
    foreach ($t['stages'] as $st) {
        $lines[] = ($st['done'] ? '✓ ' : '◦ ') . $st['label']
                 . ($st['done'] ? ' — ' . $st['date'] : ' — not yet');
    }

    $next = null;
    foreach ($t['stages'] as $st) { if (!$st['done']) { $next = $st['label']; break; } }
    $lines[] = '';
    $lines[] = $next === null
        ? 'This one is complete — every stage is recorded.'
        : 'Waiting on: ' . $next . '.';

    if ($t['extra'])   $lines[] = $t['extra'];
    if ($t['remarks']) $lines[] = 'Remarks: ' . $t['remarks'];
    if ($t['locked'])  $lines[] = 'It is locked, so it has been printed and cannot be edited.';

    return [
        'text'  => implode("\n", $lines),
        // Third element = a URL. A chip with one navigates instead of asking,
        // which is what "Open this PO" has to do — sent as a message it would
        // match the same number again and redraw the trail already on screen.
        'chips' => [[$t['goLabel'], '', $t['go']], ['Track another', 'Where is my PO?']],
    ];
}

/* ────────────────────────────────────────────────────────────────────────────
   Intent matching.


   Keyword scoring rather than a wall of regexes: each intent lists the words
   that mean it, the score is how many of them appear, and the best score wins
   provided it clears a floor. That floor is the important part — an assistant
   that always finds SOMETHING to match is an assistant that confidently
   answers the wrong question. Below the floor it says it did not follow, and
   offers what it can actually do.
   ──────────────────────────────────────────────────────────────────────────── */
function assistantMatch(string $text, array $intents): ?string {
    $t = ' ' . preg_replace('/[^a-z0-9 ]+/', ' ', strtolower($text)) . ' ';
    $t = preg_replace('/\s+/', ' ', $t);
    $best = null; $bestScore = 0;
    foreach ($intents as $name => $words) {
        $score = 0;
        foreach ($words as $w) {
            // Whole words only, so "po" does not match inside "report" and
            // "bid" does not match inside "forbidden".
            if (str_contains($t, ' ' . $w . ' ')) $score += 1 + substr_count($w, ' ');
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $name; }
    }
    return $bestScore >= 1 ? $best : null;
}

/* The one function to replace if a real model is ever wired in.
   Returns: ['text' => string, 'chips' => [[label, message]…], 'go' => url|null] */
function assistantAnswer(string $message, PDO $db, array $user, array $access): array {
    $name = assistantFirstName($user);
    // Pre-punctuated, because these get glued onto the END of a phrase:
    // "Anytime" . $name is "AnytimeTeofilo". Empty when the account has
    // no full name on file, which is why it cannot just be hardcoded.
    $vocative = $name !== '' ? ', ' . $name : '';
    $dests = assistantDestinations($access);

    /* A document number in the message beats every keyword in it. Someone who
       types "PO-sample2026" wants that purchase order, whatever else the
       sentence says, and someone who types "has PO-sample2026 been released"
       would otherwise match the word "released" and get a generic explanation
       of a pipeline they were asking a specific question about. */
    $token = assistantDocToken($message);
    if ($token !== null) {
        $trail = null;
        if ($access['purchase_orders'] ?? true)     $trail = assistantTrackPo($db, $token);
        if (!$trail && ($access['competitive_bidding'] ?? true)) $trail = assistantTrackBid($db, $token);
        if ($trail) return assistantRenderTrail($trail);
        return [
            'text'  => "I can't find anything numbered {$token}. Purchase orders look like "
                     . '"PO-sys2026826332" and bid documents like "2026-055" — a PR number works too. '
                     . 'It may also have been deleted.',
            'chips' => [['Show my tasks', 'Show my pending tasks'], ["What's new?", "What's new?"]],
        ];
    }

    $intents = [

        'greeting'  => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'kamusta'],
        'thanks'    => ['thanks', 'thank you', 'salamat', 'ty'],
        'pending'   => ['pending', 'task', 'tasks', 'todo', 'to do', 'awaiting', 'outstanding', 'backlog', 'unapproved'],
        'whatsnew'  => ['new', 'latest', 'recent', 'update', 'updates', 'happening'],
        'figures'   => ['how many', 'total', 'amount', 'figure', 'figures', 'count', 'this month', 'spend', 'value'],
        'whoami'    => ['who am i', 'my role', 'my account', 'my name', 'profile'],
        'time'      => ['time', 'date', 'today'],
        'help'      => ['help', 'what can you do', 'commands', 'options', 'guide'],
        // The three things this office tracks. Each answers "how do I find one"
        // and asks for the number, because without a number there is nothing to
        // look up — the assistant has no idea which of 26,000 records is
        // "mine". "saan" and "nasaan" are in because that is how the question
        // gets asked here half the time.
        'track_po'   => ['where', 'wheres', 'nasaan', 'saan', 'released', 'release', 'track', 'trace',
                         'status', 'approved', 'locate', 'find'],
        'track_bid'  => ['bid status', 'awarded', 'award', 'noa', 'notice of award',
                         'notice to proceed', 'ntp', 'performance bond', 'contract signed'],
        'track_insp' => ['inspected', 'inspection status', 'accepted', 'delivered',
                         'delivery', 'acceptance', 'received the delivery'],
    ];

    // Every destination contributes its own keywords, so "open bidding" and
    // "where is master data" route without a separate rule each.
    foreach ($dests as $key => [$label, $url, $words]) {
        $intents['go:' . $key] = $words;
    }

    $intent = assistantMatch($message, $intents);

    $helpChips = [
        ['Show my tasks',  'Show my pending tasks'],
        ["What's new?",    "What's new?"],
        ['Help me',        'What can you do?'],
    ];

    if ($intent === null) {
        return [
            'text'  => "Sorry{$vocative}, I didn't quite follow that. Give me a PO, PR or bid number "
                     . "and I'll track it for you — or ask about pending work, this month's figures, "
                     . "or any screen you want opening.",
            'chips' => $helpChips,
        ];
    }

    if (str_starts_with($intent, 'go:')) {
        [$label, $url] = $dests[substr($intent, 3)];
        return [
            'text'  => "Opening {$label} for you now.",
            'go'    => $url,
            'chips' => [],
        ];
    }

    switch ($intent) {
        case 'greeting':
            return ['text' => assistantGreeting($name) . ' ' . assistantOpener(), 'chips' => $helpChips];

        case 'thanks':
            return ['text' => "Anytime{$vocative}. 🙂", 'chips' => $helpChips];

        case 'whoami':
            return [
                'text'  => "You're signed in as " . ($user['full_name'] ?: 'this account')
                         . ' — ' . (hrmisRoleName($user['role_id'] ?? null) ?? roleLabel($user['role'] ?? 'user')) . '.',
                'chips' => $helpChips,
            ];

        case 'time':
            return [
                'text'  => 'It is ' . date('g:i a') . ' on ' . date('l, j F Y') . '.',
                'chips' => $helpChips,
            ];

        case 'help':
            $names = array_map(fn($d) => $d[0], $dests);
            return [
                'text'  => "Here's what I can do:\n"
                         . "• Track a document — give me a PO, PR or bid number and I'll show\n"
                         . "  every stage it has been through and what it is waiting on\n"
                         . "• Check pending work — approvals, inspections, draft bids\n"
                         . "• Report this month's purchase order count and value\n"
                         . "• Show the latest purchase orders\n"
                         . '• Take you to: ' . implode(', ', $names) . "\n\n"
                         . "I answer from this system's own records, so the numbers are always current.",
                'chips' => [
                    ['Show my tasks', 'Show my pending tasks'],
                    ['This month',    "How many POs this month?"],
                    ["What's new?",   "What's new?"],
                ],
            ];

        // The three trackable document families. Each says what it can report
        // and asks for the one thing it needs, with a real example — "give me
        // the number" is a much worse prompt than showing what one looks like.
        case 'track_po':
            return [
                'text'  => "Happy to check. Give me the PO number and I'll tell you exactly where it is —\n"
                         . "raised, approved, released to the supplier, seen by COA, and whether the\n"
                         . "delivery has been inspected.\n\n"
                         . 'They look like "PO-sys2026826332". A PR number works too.',
                'chips' => [['Latest POs', "What's new?"], ['Open Purchase Orders', 'Open purchase orders']],
            ];

        case 'track_bid':
            return [
                'text'  => "Give me the bid number and I'll walk the whole set —\n"
                         . "bid opened, Notice of Award, award received, performance security,\n"
                         . "contract signed, Notice to Proceed.\n\n"
                         . 'They look like "2026-055". The contract number works too.',
                'chips' => [['Open Competitive Bidding', 'Open bidding'], ['Show my tasks', 'Show my pending tasks']],
            ];

        case 'track_insp':
            $p = assistantPending($db, $access);
            $awaiting = $p['awaiting_inspection'] ?? null;
            return [
                'text'  => 'Give me the PO number and I will tell you whether the delivery has been '
                         . "inspected, when, and who accepted it.\n\n"
                         . ($awaiting === null ? ''
                            : number_format($awaiting) . ' deliver' . ($awaiting === 1 ? 'y is' : 'ies are')
                              . " awaiting inspection this month."),
                'chips' => [['Open Inspection', 'Open inspection'], ['Show my tasks', 'Show my pending tasks']],
            ];

        case 'pending':

            $p = assistantPending($db, $access);
            if (!$p) return ['text' => "You don't have access to the modules I track work for.", 'chips' => $helpChips];
            $bits = [];
            // Worded as a standing total rather than as today's to-do list,
            // because that is what it is: the same all-time figure the
            // dashboard tile shows, and most of it is historic. Quietly
            // filing 2,000 old records under "your pending tasks" would be
            // alarming and wrong.
            if (isset($p['po_unapproved']))       $bits[] = number_format($p['po_unapproved']) . ' purchase order' . ($p['po_unapproved'] === 1 ? '' : 's') . ' still carry no approval date (all time)';
            if (isset($p['awaiting_inspection'])) $bits[] = number_format($p['awaiting_inspection']) . ' deliver' . ($p['awaiting_inspection'] === 1 ? 'y' : 'ies') . ' awaiting inspection this month';
            if (isset($p['draft_bids']))          $bits[] = number_format($p['draft_bids']) . ' bid document' . ($p['draft_bids'] === 1 ? '' : 's') . ' not yet awarded';
            $chips = [];
            if (isset($p['awaiting_inspection']) && $p['awaiting_inspection'] > 0) $chips[] = ['Open Inspection', 'Open inspection'];
            if (isset($p['po_unapproved']) && $p['po_unapproved'] > 0)             $chips[] = ['Open Purchase Orders', 'Open purchase orders'];
            return [
                'text'  => "Here's what's outstanding:\n• " . implode("\n• ", $bits),
                'chips' => $chips ?: $helpChips,
            ];

        case 'figures':
            $m = assistantMonthFigures($db);
            return [
                'text'  => 'So far this month: ' . number_format($m['count']) . ' purchase order'
                         . ($m['count'] === 1 ? '' : 's') . ', worth ' . assistantMoney($m['amount']) . '.',
                'chips' => [['Show my tasks', 'Show my pending tasks'], ['Open Purchase Orders', 'Open purchase orders']],
            ];

        case 'whatsnew':
            $rows = assistantRecent($db);
            if (!$rows) return ['text' => 'Nothing new on file yet.', 'chips' => $helpChips];
            $lines = array_map(function ($r) {
                $when = $r['date_created'] ? date('j M', strtotime($r['date_created'])) : '';
                return trim($r['po_number'] . ' — ' . ($r['supplier_name'] ?: 'no supplier') . ($when ? " ({$when})" : ''));
            }, $rows);
            return [
                'text'  => "The three most recent purchase orders:\n• " . implode("\n• ", $lines),
                'chips' => [['Open Purchase Orders', 'Open purchase orders'], ['Show my tasks', 'Show my pending tasks']],
            ];
    }

    return ['text' => "I'm not sure how to help with that yet.", 'chips' => $helpChips];
}

// ── Routes ──────────────────────────────────────────────────────────────────
try {
    $db = getPoDB();

    // The opening line, fetched once when the dashboard loads.
    if (($_GET['action'] ?? '') === 'greeting') {
        $name  = assistantFirstName($user);
        $dests = assistantDestinations($access);
        // Opening chips lead with the three things the office tracks, because
        // "where is my PO" is the question this assistant exists to answer.
        $chips = [];
        if ($dests['purchase_orders']     ?? false) $chips[] = ['Where is my PO?',    'Where is my PO?'];
        if ($dests['competitive_bidding'] ?? false) $chips[] = ['Bid status',         'bid status'];
        if ($dests['inspection']          ?? false) $chips[] = ['Inspection status',  'inspection status'];
        $chips[] = ['Show my tasks', 'Show my pending tasks'];
        $chips[] = ['Help me', 'What can you do?'];
        jsonResponse(true, '', [
            'greeting' => assistantGreeting($name),
            'opener'   => assistantOpener(),
            'name'     => $name,
            'chips'    => $chips,
        ]);
    }

    $data    = getInput();
    $message = trim((string)($data['message'] ?? ''));
    if ($message === '') jsonResponse(false, 'Say something and I will do my best.');
    // Long enough for any real question; a cap keeps a runaway paste out of
    // the matcher and out of the log.
    if (mb_strlen($message) > 500) $message = mb_substr($message, 0, 500);

    jsonResponse(true, '', assistantAnswer($message, $db, $user, $access));

} catch (Throwable $e) {
    // Surfaced as the assistant admitting it, not as a 500 landing in a chat
    // bubble as raw HTML.
    error_log('Assistant failed: ' . $e->getMessage());
    jsonResponse(true, '', [
        'text'  => "I can't reach the records right now — the purchase order server isn't answering. "
                 . 'Everything else on the dashboard will tell you the same, so it is worth a word with IT.',
        'chips' => [],
    ]);
}
