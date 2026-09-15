<?php
/**
 * Crystal Reports (.rpt) structure documenter for the Purchase Order module.
 *
 * .rpt files are OLE/CFB compound binaries — Crystal encodes field/formula/
 * layout data inside proprietary streams (e.g. CrystalReportDesignerStream)
 * that aren't plain text, so this tool does NOT attempt to reconstruct pixel
 * layout. What IS reliably recoverable:
 *   1. The compound file's stream/storage inventory (name, type, size) —
 *      confirms the file's internal shape without decoding stream contents.
 *   2. The report's real parameter/section contract, straight from the
 *      paired VB.NET `ReportClass` wrapper (`Public ReadOnly Property
 *      Parameter_*` / `SectionN` declarations) — this is what the WinForms
 *      app actually binds at runtime, so it's a more reliable source of
 *      truth than the binary layout would be even if we could parse it.
 *
 * Usage: php tools/rpt_analyzer.php ["vb/<module>/Crystal Reports"]
 * Reads every *.rpt (and its paired *.vb, if present) from the given folder —
 * Purchase Order's when none is named — and writes one Markdown spec per
 * report into tools/rpt_reports/.
 */

// Relative to the repository root, so the argument reads the same as the path
// printed in the spec it produces.
const RPT_DEFAULT_SOURCE = 'vb/Purchase Order/Crystal Reports';
$sourceLabel = (isset($argv[1]) && $argv[1] !== '')
    ? trim($argv[1], "/\\")
    : RPT_DEFAULT_SOURCE;
$sourceDir   = __DIR__ . '/../' . $sourceLabel;
$outDir    = __DIR__ . '/rpt_reports';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

// ── Minimal OLE/Compound File Binary (CFB) directory reader ──
// Enumerates every storage/stream entry (name, type, size) without
// resolving the tree structure or following FAT chains to read content —
// that's all "confirm the file's internal shape" needs.
function readCfbEntries(string $path): ?array {
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 512) return null;
    if (substr($data, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") return null;

    $u16 = fn($off) => unpack('v', substr($data, $off, 2))[1];
    $u32 = fn($off) => unpack('V', substr($data, $off, 4))[1];

    $sectorSize      = 1 << $u16(30);
    $firstDirSector  = $u32(48);
    $sectorOffset    = fn($sec) => 512 + $sec * $sectorSize;

    // FAT sector IDs live in the first 109 header DIFAT slots; .rpt files
    // are small enough that additional DIFAT sectors are never needed.
    $fatSectors = [];
    for ($i = 0; $i < 109; $i++) {
        $v = $u32(76 + $i * 4);
        if ($v !== 0xFFFFFFFF) $fatSectors[] = $v;
    }

    $fat = [];
    foreach ($fatSectors as $fatSecId) {
        $off = $sectorOffset($fatSecId);
        for ($i = 0; $i < intdiv($sectorSize, 4); $i++) {
            $fat[] = $u32($off + $i * 4);
        }
    }

    $dirData = '';
    $sec = $firstDirSector;
    $guard = 0;
    while ($sec !== 0xFFFFFFFE && $sec !== 0xFFFFFFFF && isset($fat[$sec]) && $guard < 20000) {
        $dirData .= substr($data, $sectorOffset($sec), $sectorSize);
        $sec = $fat[$sec];
        $guard++;
    }

    $entries = [];
    $count = intdiv(strlen($dirData), 128);
    for ($i = 0; $i < $count; $i++) {
        $entry   = substr($dirData, $i * 128, 128);
        $nameLen = unpack('v', substr($entry, 64, 2))[1];
        if ($nameLen <= 0) continue;
        $name = @mb_convert_encoding(substr($entry, 0, max(0, $nameLen - 2)), 'UTF-8', 'UTF-16LE');
        $type = ord($entry[66]);
        if ($type === 0) continue; // unused slot
        $entries[] = [
            'name' => $name,
            'type' => match ($type) { 1 => 'storage', 2 => 'stream', 5 => 'root', default => "unknown($type)" },
            'size' => unpack('V', substr($entry, 120, 4))[1],
        ];
    }
    return $entries;
}

// ── Pull the real parameter/section contract out of the paired .vb wrapper ──
function readReportClassContract(string $vbPath): array {
    $src = file_get_contents($vbPath);
    $result = ['class' => null, 'resource_name' => null, 'sections' => [], 'parameters' => []];

    if (preg_match('/Public Class (\S+)/', $src, $m)) $result['class'] = $m[1];
    if (preg_match('/Return "([^"]+\.rpt)"/', $src, $m)) $result['resource_name'] = $m[1];
    preg_match_all('/Public ReadOnly Property (Section\d+)\(\)/', $src, $m);
    $result['sections'] = $m[1];
    preg_match_all('/Public ReadOnly Property Parameter_(\w+)\(\)/', $src, $m);
    $result['parameters'] = $m[1];

    return $result;
}

$rptFiles = glob($sourceDir . '/*.rpt');
if (!$rptFiles) {
    fwrite(STDERR, "No .rpt files found in $sourceDir\n");
    exit(1);
}

foreach ($rptFiles as $rptPath) {
    $base   = pathinfo($rptPath, PATHINFO_FILENAME);
    $vbPath = $sourceDir . '/' . $base . '.vb';

    $entries  = readCfbEntries($rptPath);
    $contract = file_exists($vbPath) ? readReportClassContract($vbPath) : null;

    $md = "# Report: {$base}.rpt\n\n";
    $md .= "Source: `{$sourceLabel}/{$base}.rpt`" . ($contract ? " + `{$base}.vb`" : '') . "\n\n";
    $md .= "## OLE Compound File Stream Inventory\n\n";
    $md .= "*(names/types/sizes only — Crystal's internal stream encoding isn't plain text, so contents aren't decoded)*\n\n";
    if ($entries === null) {
        $md .= "_Could not parse as an OLE compound file (unexpected header)._\n\n";
    } else {
        $md .= "| Name | Type | Size (bytes) |\n|---|---|---|\n";
        foreach ($entries as $e) {
            $md .= "| {$e['name']} | {$e['type']} | {$e['size']} |\n";
        }
        $md .= "\n";
    }

    $md .= "## Runtime Contract (from the paired VB.NET ReportClass wrapper)\n\n";
    if ($contract) {
        $md .= "- **Class**: `{$contract['class']}`\n";
        $md .= "- **Resource**: `{$contract['resource_name']}`\n";
        $md .= "- **Sections**: " . (count($contract['sections']) ?: 'none') . " — " . implode(', ', $contract['sections']) . "\n";
        $md .= "- **Parameters** (must be supplied via `SetParameterValue` before display):\n";
        foreach ($contract['parameters'] as $p) $md .= "  - `{$p}`\n";
    } else {
        $md .= "_No paired .vb wrapper found — parameters/sections unknown._\n";
    }

    $md .= "\n## Data Source\n\n";
    $md .= "Data is pushed in, not queried live: the WinForms app builds an in-memory\n";
    $md .= "`DataSet`, names each table to match what the report expects, and calls\n";
    $md .= "`ReportDocument.SetDataSource(myDs)`. The table names and the exact SQL\n";
    $md .= "live in the print button's click handler in the module's own forms —\n";
    $md .= "`add_time_po.vb` for Purchase Order, `Inspect_Print.vb` for Inspection &\n";
    $md .= "Acceptance — which is also where the `SetParameterValue` calls that fill\n";
    $md .= "the parameters above are made.\n\n";
    $md .= "Note that a report can bind a field the pushed DataSet never supplies; that\n";
    $md .= "field simply renders blank. Check the SELECT list against the bindings\n";
    $md .= "before assuming a placement is fed.\n";

    file_put_contents($outDir . '/' . $base . '.md', $md);
    echo "Wrote {$outDir}/{$base}.md\n";
}
