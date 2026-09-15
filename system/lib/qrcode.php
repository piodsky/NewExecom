<?php
// Dependency-free QR Code encoder — byte mode, error-correction level M,
// versions 1–10 (up to 216 bytes). Written from the ISO/IEC 18004 spec
// because this app is deliberately offline-first: no Composer, no CDN, and
// no vendored third-party QR library to keep in sync.
//
// Only what the Purchase Order printout actually needs is implemented:
// a single byte-mode segment at a fixed EC level. Kanji/numeric/alphanumeric
// modes, ECI, and versions above 10 are intentionally absent — the
// verification payload is a short ASCII string and always will be.
//
// Entry point: qrMatrix($text) -> array of rows of 0/1 (1 = dark module).

// GF(256) log/antilog tables, primitive polynomial 0x11D (x^8+x^4+x^3+x^2+1).
function qrGfTables(): array {
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i]  = $x;
            $log[$x]  = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    }
    return [$exp, $log];
}

// Reed-Solomon generator polynomial of the given degree, highest term first.
function qrRsGenerator(int $degree): array {
    [$exp, $log] = qrGfTables();
    $g = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($g) + 1, 0);
        for ($k = 0, $n = count($g); $k < $n; $k++) {
            $next[$k]     ^= $g[$k];                                        // × x
            $next[$k + 1] ^= $g[$k] ? $exp[($log[$g[$k]] + $i) % 255] : 0;   // × α^i
        }
        $g = $next;
    }
    return $g;
}

function qrRsEcc(array $data, int $ecLen): array {
    [$exp, $log] = qrGfTables();
    $gen = qrRsGenerator($ecLen);
    $res = array_merge($data, array_fill(0, $ecLen, 0));
    $n   = count($data);
    for ($i = 0; $i < $n; $i++) {
        $coef = $res[$i];
        if ($coef === 0) continue;
        $lc = $log[$coef];
        for ($j = 0; $j <= $ecLen; $j++) {
            $res[$i + $j] ^= $gen[$j] ? $exp[($log[$gen[$j]] + $lc) % 255] : 0;
        }
    }
    return array_slice($res, $n, $ecLen);
}

// Per-version EC layout: [ec codewords per block, [[block count, data codewords per block], ...]]
//
// Level L (7% recovery) and M (15%) are the two that matter here. L is the
// right default for the verification URL: the extra capacity keeps the symbol
// at a lower version, so each module prints larger, and module size is what
// actually limits whether a phone can read a QR off paper.
function qrVersionSpec(string $ec = 'M'): array {
    $tables = [
        'L' => [
            1  => [7,  [[1, 19]]],
            2  => [10, [[1, 34]]],
            3  => [15, [[1, 55]]],
            4  => [20, [[1, 80]]],
            5  => [26, [[1, 108]]],
            6  => [18, [[2, 68]]],
            7  => [20, [[2, 78]]],
            8  => [24, [[2, 97]]],
            9  => [30, [[2, 116]]],
            10 => [18, [[2, 68], [2, 69]]],
        ],
        'M' => [
            1  => [10, [[1, 16]]],
            2  => [16, [[1, 28]]],
            3  => [26, [[1, 44]]],
            4  => [18, [[2, 32]]],
            5  => [24, [[2, 43]]],
            6  => [16, [[4, 27]]],
            7  => [18, [[4, 31]]],
            8  => [22, [[2, 38], [2, 39]]],
            9  => [22, [[3, 36], [2, 37]]],
            10 => [26, [[4, 43], [1, 44]]],
        ],
    ];
    if (!isset($tables[$ec])) {
        throw new InvalidArgumentException("Unsupported QR EC level: {$ec} (expected L or M).");
    }
    return $tables[$ec];
}

function qrAlignmentCenters(int $version): array {
    return [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ][$version];
}

// Bits left over after the interleaved codeword stream, per version group.
function qrRemainderBits(int $version): int {
    if ($version === 1) return 0;
    if ($version <= 6)  return 7;
    return 0; // versions 7–13
}

function qrDataCapacity(int $version, string $ec = 'M'): int {
    [, $blocks] = qrVersionSpec($ec)[$version];
    $total = 0;
    foreach ($blocks as [$count, $size]) $total += $count * $size;
    return $total;
}

// Byte-mode bitstream: mode indicator, character count, payload, terminator,
// then the alternating 0xEC/0x11 pad bytes the spec mandates.
function qrEncodeData(string $text, int $version, string $ec = 'M'): array {
    $capacity = qrDataCapacity($version, $ec);
    $len      = strlen($text);
    $bits     = [];

    $push = function (int $value, int $count) use (&$bits) {
        for ($i = $count - 1; $i >= 0; $i--) $bits[] = ($value >> $i) & 1;
    };

    $push(0b0100, 4);                          // byte mode
    $push($len, $version <= 9 ? 8 : 16);       // character count indicator
    for ($i = 0; $i < $len; $i++) $push(ord($text[$i]), 8);

    $capacityBits = $capacity * 8;
    $push(0, min(4, $capacityBits - count($bits)));   // terminator
    while (count($bits) % 8 !== 0) $bits[] = 0;       // byte-align

    $codewords = [];
    for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | $bits[$i + $j];
        $codewords[] = $byte;
    }
    $pads = [0xEC, 0x11];
    for ($i = 0; count($codewords) < $capacity; $i++) $codewords[] = $pads[$i % 2];

    return $codewords;
}

// Split into blocks, append per-block ECC, then interleave both streams.
function qrInterleave(array $codewords, int $version, string $ec = 'M'): array {
    [$ecLen, $groups] = qrVersionSpec($ec)[$version];

    $dataBlocks = [];
    $ecBlocks   = [];
    $offset     = 0;
    foreach ($groups as [$count, $size]) {
        for ($b = 0; $b < $count; $b++) {
            $block        = array_slice($codewords, $offset, $size);
            $offset      += $size;
            $dataBlocks[] = $block;
            $ecBlocks[]   = qrRsEcc($block, $ecLen);
        }
    }

    $result  = [];
    $maxData = max(array_map('count', $dataBlocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($dataBlocks as $block) {
            if (isset($block[$i])) $result[] = $block[$i];
        }
    }
    for ($i = 0; $i < $ecLen; $i++) {
        foreach ($ecBlocks as $block) $result[] = $block[$i];
    }
    return $result;
}

function qrSmallestVersion(string $text, string $ec = 'M'): int {
    $len = strlen($text);
    foreach (array_keys(qrVersionSpec($ec)) as $version) {
        // 4 mode bits + the character-count indicator + the payload itself.
        $needed = 4 + ($version <= 9 ? 8 : 16) + $len * 8;
        if ($needed <= qrDataCapacity($version, $ec) * 8) return $version;
    }
    $max = qrDataCapacity(10, $ec) - 3;
    throw new InvalidArgumentException("QR payload too long (max {$max} bytes at EC level {$ec}, version 10).");
}

// 15-bit format information: EC level + mask, BCH(15,5) protected, XOR-masked.
// The EC-level indicator is deliberately not in numeric order in the spec.
function qrEcIndicator(string $ec): int {
    return ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10][$ec];
}

function qrFormatBits(int $mask, string $ec = 'M'): int {
    $data = (qrEcIndicator($ec) << 3) | $mask;
    $rem  = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    return (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
}

// 18-bit version information, BCH(18,6) protected. Only used for versions 7+.
function qrVersionBits(int $version): int {
    $rem = $version;
    for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
    return ($version << 12) | ($rem & 0xFFF);
}

function qrMaskBit(int $mask, int $row, int $col): bool {
    switch ($mask) {
        case 0: return ($row + $col) % 2 === 0;
        case 1: return $row % 2 === 0;
        case 2: return $col % 3 === 0;
        case 3: return ($row + $col) % 3 === 0;
        case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0;
        case 5: return (($row * $col) % 2) + (($row * $col) % 3) === 0;
        case 6: return (((($row * $col) % 2) + (($row * $col) % 3)) % 2) === 0;
        default: return (((($row + $col) % 2) + (($row * $col) % 3)) % 2) === 0;
    }
}

// The four penalty rules from the spec — lower total is a better mask choice.
function qrPenalty(array $m): int {
    $size    = count($m);
    $penalty = 0;

    // Rule 1: runs of five or more same-coloured modules in a line.
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            $run = 1;
            for ($b = 1; $b < $size; $b++) {
                $prev = $pass === 0 ? $m[$a][$b - 1] : $m[$b - 1][$a];
                $cur  = $pass === 0 ? $m[$a][$b]     : $m[$b][$a];
                if ($cur === $prev) {
                    $run++;
                } else {
                    if ($run >= 5) $penalty += 3 + ($run - 5);
                    $run = 1;
                }
            }
            if ($run >= 5) $penalty += 3 + ($run - 5);
        }
    }

    // Rule 2: every 2×2 block of one colour.
    for ($r = 0; $r < $size - 1; $r++) {
        for ($c = 0; $c < $size - 1; $c++) {
            $v = $m[$r][$c];
            if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                $penalty += 3;
            }
        }
    }

    // Rule 3: the 1:1:3:1:1 finder-lookalike pattern with four light modules
    // on either side — the sequence a scanner could mistake for a finder.
    $a1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
    $a2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c <= $size - 11; $c++) {
            $h1 = $h2 = $v1 = $v2 = true;
            for ($k = 0; $k < 11; $k++) {
                if ($m[$r][$c + $k] !== $a1[$k]) $h1 = false;
                if ($m[$r][$c + $k] !== $a2[$k]) $h2 = false;
                if ($m[$c + $k][$r] !== $a1[$k]) $v1 = false;
                if ($m[$c + $k][$r] !== $a2[$k]) $v2 = false;
            }
            if ($h1) $penalty += 40;
            if ($h2) $penalty += 40;
            if ($v1) $penalty += 40;
            if ($v2) $penalty += 40;
        }
    }

    // Rule 4: deviation of the dark-module ratio from 50%.
    $dark = 0;
    foreach ($m as $row) $dark += array_sum($row);
    $ratio = ($dark * 100) / ($size * $size);
    $penalty += intdiv((int)floor(abs($ratio - 50)), 5) * 10;

    return $penalty;
}

// Draws every function pattern (finders, separators, timing, alignment, the
// always-dark module) and marks those cells reserved so data placement skips
// them. Format/version areas are reserved too, then overwritten for real once
// the mask is chosen.
function qrDrawFunctionPatterns(int $version, array &$m, array &$reserved): void {
    $size = count($m);

    $setFn = function (int $r, int $c, int $v) use (&$m, &$reserved, $size) {
        if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) return;
        $m[$r][$c]        = $v;
        $reserved[$r][$c] = true;
    };

    // Finder patterns plus their light separators, one per corner.
    foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$fr, $fc]) {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $inRing = ($r === 0 || $r === 6) && $c >= 0 && $c <= 6;
                $inCol  = ($c === 0 || $c === 6) && $r >= 0 && $r <= 6;
                $inCore = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $setFn($fr + $r, $fc + $c, ($inRing || $inCol || $inCore) ? 1 : 0);
            }
        }
    }

    // Timing patterns along row 6 and column 6.
    for ($i = 8; $i < $size - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        $setFn(6, $i, $bit);
        $setFn($i, 6, $bit);
    }

    // Alignment patterns, except where they would collide with a finder.
    $centers = qrAlignmentCenters($version);
    foreach ($centers as $cr) {
        foreach ($centers as $cc) {
            if (($cr === 6 && $cc === 6) ||
                ($cr === 6 && $cc === $size - 7) ||
                ($cr === $size - 7 && $cc === 6)) continue;
            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $edge = max(abs($r), abs($c));
                    $setFn($cr + $r, $cc + $c, ($edge !== 1) ? 1 : 0);
                }
            }
        }
    }

    $setFn($size - 8, 8, 1); // the always-dark module

    // Reserve the two format-information strips.
    for ($i = 0; $i <= 8; $i++) {
        if ($i !== 6) { $setFn(8, $i, 0); $setFn($i, 8, 0); }
    }
    for ($i = 0; $i < 8; $i++) {
        $setFn(8, $size - 1 - $i, 0);
        if ($size - 1 - $i !== $size - 8) $setFn($size - 1 - $i, 8, 0);
    }

    // Reserve the two version-information blocks (versions 7 and up only).
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $setFn($b, $a, 0);
            $setFn($a, $b, 0);
        }
    }
}

function qrPlaceData(array $codewords, int $version, array &$m, array $reserved): void {
    $size = count($m);
    $bits = [];
    foreach ($codewords as $cw) {
        for ($i = 7; $i >= 0; $i--) $bits[] = ($cw >> $i) & 1;
    }
    for ($i = 0, $n = qrRemainderBits($version); $i < $n; $i++) $bits[] = 0;

    $idx   = 0;
    $upward = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--; // the vertical timing pattern is not a data column
        for ($k = 0; $k < $size; $k++) {
            $row = $upward ? ($size - 1 - $k) : $k;
            for ($c = 0; $c < 2; $c++) {
                $cc = $col - $c;
                if (!empty($reserved[$row][$cc])) continue;
                $m[$row][$cc] = $bits[$idx] ?? 0;
                $idx++;
            }
        }
        $upward = !$upward;
    }
}

function qrDrawFormat(array &$m, int $mask, string $ec = 'M'): void {
    $size = count($m);
    $bits = qrFormatBits($mask, $ec);
    $bit  = fn(int $i) => ($bits >> $i) & 1;

    for ($i = 0; $i <= 5; $i++) $m[$i][8] = $bit($i);
    $m[7][8] = $bit(6);
    $m[8][8] = $bit(7);
    $m[8][7] = $bit(8);
    for ($i = 9; $i < 15; $i++) $m[8][14 - $i] = $bit($i);

    for ($i = 0; $i < 8; $i++)  $m[8][$size - 1 - $i]  = $bit($i);
    for ($i = 8; $i < 15; $i++) $m[$size - 15 + $i][8] = $bit($i);

    $m[$size - 8][8] = 1;
}

function qrDrawVersion(array &$m, int $version): void {
    if ($version < 7) return;
    $size = count($m);
    $bits = qrVersionBits($version);
    for ($i = 0; $i < 18; $i++) {
        $bit = ($bits >> $i) & 1;
        $a = $size - 11 + $i % 3;
        $b = intdiv($i, 3);
        $m[$b][$a] = $bit;
        $m[$a][$b] = $bit;
    }
}

// Encode $text and return the finished module matrix (1 = dark).
function qrMatrix(string $text, string $ec = 'M'): array {
    $version = qrSmallestVersion($text, $ec);
    $size    = 21 + ($version - 1) * 4;

    $base     = array_fill(0, $size, array_fill(0, $size, 0));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));
    qrDrawFunctionPatterns($version, $base, $reserved);
    qrPlaceData(
        qrInterleave(qrEncodeData($text, $version, $ec), $version, $ec),
        $version, $base, $reserved
    );

    // Try all eight masks, keep the one the spec's penalty rules like best.
    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = $base;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (empty($reserved[$r][$c]) && qrMaskBit($mask, $r, $c)) {
                    $candidate[$r][$c] ^= 1;
                }
            }
        }
        qrDrawFormat($candidate, $mask, $ec);
        qrDrawVersion($candidate, $version);

        $score = qrPenalty($candidate);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best      = $candidate;
        }
    }
    return $best;
}

// PNG data URI — preferred over SVG for the print preview because that page is
// rasterised by html2canvas, whose inline-SVG support is unreliable.
function qrPngDataUri(string $text, int $scale = 4, int $quiet = 4, string $ec = 'M'): string {
    $m    = qrMatrix($text, $ec);
    $n    = count($m);
    $dim  = ($n + $quiet * 2) * $scale;
    $im   = imagecreatetruecolor($dim, $dim);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $dim, $dim, $white);
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if (!$m[$r][$c]) continue;
            $x = ($c + $quiet) * $scale;
            $y = ($r + $quiet) * $scale;
            imagefilledrectangle($im, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
        }
    }
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);
    return 'data:image/png;base64,' . base64_encode($png);
}

function qrSvg(string $text, int $size = 140, int $quiet = 4, string $ec = 'M'): string {
    $m    = qrMatrix($text, $ec);
    $n    = count($m);
    $span = $n + $quiet * 2;
    $svg  = "<svg viewBox=\"0 0 {$span} {$span}\" width=\"{$size}\" height=\"{$size}\" "
          . "xmlns=\"http://www.w3.org/2000/svg\" shape-rendering=\"crispEdges\">"
          . "<rect width=\"{$span}\" height=\"{$span}\" fill=\"#fff\"/>";
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if (!$m[$r][$c]) continue;
            $x = $c + $quiet;
            $y = $r + $quiet;
            $svg .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\" fill=\"#000\"/>";
        }
    }
    return $svg . '</svg>';
}
