<?php
// Dependency-free Code 128 barcode encoder (subsets B and C).
//
// Written from the spec for the same reason as lib/qrcode.php: this app is
// offline-first — no Composer, no CDN, no vendored third-party barcode library
// to keep in sync.
//
// Code set B covers ASCII 32–126, which is every character a PO number can
// contain. Set C packs pairs of digits into one symbol, so it is used for the
// long numeric tail ("PO-sys2026826219" → "PO-sys" in B, "2026826219" in C).
// That shortens the symbol by about a fifth, which matters: a narrower barcode
// means wider bars in the same printed space, and bar width is what decides
// whether a scanner can read it off paper.
//
// Entry point: code128Modules($text) -> array of 0/1 (1 = bar).

// The 107 Code 128 patterns, as bar/space run lengths. Each entry alternates
// bar, space, bar, space, bar, space; widths sum to 11 (the stop pattern has a
// seventh run and sums to 13). code128SelfTest() below asserts exactly that.
function code128Patterns(): array {
    return [
        '212222','222122','222221','121223','121322','131222','122213','122312',
        '132212','221213','221312','231212','112232','122132','122231','113222',
        '123122','123221','223211','221132','221231','213212','223112','312131',
        '311222','321122','321221','312212','322112','322211','212123','212321',
        '232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121',
        '313121','211331','231131','213113','213311','213131','311123','311321',
        '331121','312113','312311','332111','314111','221411','431111','111224',
        '111422','121124','121421','141122','141221','112214','112412','122114',
        '122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112',
        '421211','212141','214121','412121','111143','111341','131141','114113',
        '114311','411113','411311','113141','114131','311141','411131','211412',
        '211214','211232','2331112',
    ];
}

const CODE128_START_B = 104;
const CODE128_START_C = 105;
const CODE128_STOP    = 106;
const CODE128_TO_B    = 100;   // switch to set B
const CODE128_TO_C    = 99;    // switch to set C

// How many digits run consecutively from $i.
function code128DigitRun(string $s, int $i): int {
    $n = 0;
    $len = strlen($s);
    while ($i + $n < $len && ctype_digit($s[$i + $n])) $n++;
    return $n;
}

// Text -> the list of Code 128 symbol values, excluding checksum and stop.
//
// Switching to C costs one symbol and saves one per digit pair, so it only
// pays for runs of 4+ digits (6+ mid-string, where a switch back is also
// needed). Odd-length runs encode their first digit in B so the remainder
// pairs up cleanly.
function code128Symbols(string $text): array {
    $len  = strlen($text);
    $out  = [];
    $i    = 0;

    $startRun = code128DigitRun($text, 0);
    $useC     = $startRun >= 4 || ($startRun === $len && $len >= 2 && $len % 2 === 0);
    if ($useC && $startRun % 2 === 1) $useC = false;   // keep pairs aligned

    $mode = $useC ? 'C' : 'B';
    $out[] = $useC ? CODE128_START_C : CODE128_START_B;

    while ($i < $len) {
        if ($mode === 'C') {
            if ($i + 1 < $len && ctype_digit($text[$i]) && ctype_digit($text[$i + 1])) {
                $out[] = (int)substr($text, $i, 2);
                $i += 2;
                continue;
            }
            $out[] = CODE128_TO_B;
            $mode  = 'B';
            continue;
        }

        // In set B: consider switching to C for a long enough digit run.
        $run = code128DigitRun($text, $i);
        $atEnd = ($i + $run) >= $len;
        $pairs = intdiv($run, 2);
        if ($pairs >= 2 && ($run >= 6 || ($atEnd && $run >= 4))) {
            if ($run % 2 === 1) {            // encode the odd leading digit in B
                $out[] = ord($text[$i]) - 32;
                $i++;
            }
            $out[] = CODE128_TO_C;
            $mode  = 'C';
            continue;
        }

        $c = ord($text[$i]);
        if ($c < 32 || $c > 126) {
            throw new InvalidArgumentException('Code 128 set B cannot encode byte ' . $c . '.');
        }
        $out[] = $c - 32;
        $i++;
    }
    return $out;
}

// Modulo-103 weighted checksum: start value + position * symbol, positions 1-based.
function code128Checksum(array $symbols): int {
    $sum = $symbols[0];
    for ($i = 1, $n = count($symbols); $i < $n; $i++) {
        $sum += $i * $symbols[$i];
    }
    return $sum % 103;
}

// Encode to a flat module array: 1 = bar, 0 = space.
function code128Modules(string $text): array {
    if ($text === '') throw new InvalidArgumentException('Code 128 payload is empty.');
    $patterns = code128Patterns();
    $symbols  = code128Symbols($text);
    $symbols[] = code128Checksum($symbols);
    $symbols[] = CODE128_STOP;

    $modules = [];
    foreach ($symbols as $sym) {
        $pattern = $patterns[$sym];
        $isBar   = true;                       // every pattern starts with a bar
        for ($k = 0, $n = strlen($pattern); $k < $n; $k++) {
            $runLength = (int)$pattern[$k];
            for ($j = 0; $j < $runLength; $j++) $modules[] = $isBar ? 1 : 0;
            $isBar = !$isBar;
        }
    }
    return $modules;
}

// PNG data URI. PNG rather than SVG because the printout is rasterised by
// html2canvas, whose inline-SVG support is unreliable.
//
// $quiet is the mandatory light margin either side; Code 128 requires at least
// 10 modules, without which a scanner cannot find the symbol's edges.
function code128PngDataUri(string $text, int $scale = 2, int $height = 38, int $quiet = 10): string {
    $modules = code128Modules($text);
    $count   = count($modules);
    $width   = ($count + $quiet * 2) * $scale;

    $im    = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $width, $height, $white);
    foreach ($modules as $idx => $bit) {
        if (!$bit) continue;
        $x = ($idx + $quiet) * $scale;
        imagefilledrectangle($im, $x, 0, $x + $scale - 1, $height - 1, $black);
    }
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);
    return 'data:image/png;base64,' . base64_encode($png);
}

// ── Verification helpers (used by the test script, not by the app) ──

// Every pattern must alternate bar/space and sum to 11 modules (13 for stop).
function code128SelfTest(): array {
    $errors = [];
    $patterns = code128Patterns();
    if (count($patterns) !== 107) $errors[] = 'expected 107 patterns, got ' . count($patterns);
    foreach ($patterns as $i => $p) {
        $sum = array_sum(str_split($p));
        $expectedSum  = ($i === 106) ? 13 : 11;
        $expectedRuns = ($i === 106) ? 7  : 6;
        if ($sum !== $expectedSum)          $errors[] = "pattern $i sums to $sum, expected $expectedSum";
        if (strlen($p) !== $expectedRuns)   $errors[] = "pattern $i has " . strlen($p) . " runs, expected $expectedRuns";
    }
    return $errors;
}

// Independent decoder — reads a module array back to text. Exists so the
// encoder can be proven by round-trip rather than inspection; a set-switch or
// checksum bug shows up here as a mismatch.
function code128Decode(array $modules): ?string {
    // Modules -> run lengths.
    $runs = [];
    $cur  = $modules[0];
    $len  = 0;
    foreach ($modules as $m) {
        if ($m === $cur) { $len++; continue; }
        $runs[] = $len; $cur = $m; $len = 1;
    }
    $runs[] = $len;

    $lookup = array_flip(code128Patterns());
    $symbols = [];
    $i = 0;
    while ($i < count($runs)) {
        $take = (count($runs) - $i === 7) ? 7 : 6;
        if ($i + $take > count($runs)) return null;
        $key = implode('', array_slice($runs, $i, $take));
        if (!isset($lookup[$key])) return null;
        $symbols[] = $lookup[$key];
        $i += $take;
    }
    if (count($symbols) < 3 || end($symbols) !== CODE128_STOP) return null;

    array_pop($symbols);                       // stop
    $check = array_pop($symbols);
    if ($check !== code128Checksum($symbols)) return null;

    // Switch codes must be read mode-aware: the value 99 is "switch to C" only
    // while in set B — inside set C it is the ordinary digit pair "99".
    // Likewise 100 means "switch to B" only while in set C. Treating either as
    // a switch unconditionally silently eats data (it did, for "…99…").
    $mode = $symbols[0] === CODE128_START_C ? 'C' : 'B';
    $text = '';
    for ($k = 1; $k < count($symbols); $k++) {
        $s = $symbols[$k];
        if ($mode === 'C') {
            if ($s === CODE128_TO_B) { $mode = 'B'; continue; }
            $text .= str_pad((string)$s, 2, '0', STR_PAD_LEFT);
        } else {
            if ($s === CODE128_TO_C) { $mode = 'C'; continue; }
            $text .= chr($s + 32);
        }
    }
    return $text;
}
