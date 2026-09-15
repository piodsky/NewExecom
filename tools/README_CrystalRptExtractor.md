# Crystal RPT Extractor

Use `Extract-CrystalRpt.ps1` before migrating any Crystal Reports `.rpt` file to PHP.

The extractor loads the report with the installed Crystal Reports .NET runtime and writes a TSV layout manifest containing:

- section heights;
- report object names and kinds;
- Crystal positions and sizes in twips and inches;
- text, database field, and formula bindings;
- formulas;
- parameters;
- database tables;
- subreport sections, objects, and tables.

## Layout Command

```powershell
powershell -ExecutionPolicy Bypass -File tools\Extract-CrystalRpt.ps1 `
  -ReportPath Reports\Templates\CTO\crptORAF51a.rpt `
  -OutputPath tools\crptORAF51a.layout.tsv
```

Optional runtime version:

```powershell
powershell -ExecutionPolicy Bypass -File tools\Extract-CrystalRpt.ps1 `
  -ReportPath Reports\Templates\CTO\crptORAF51b.rpt `
  -OutputPath tools\crptORAF51b.layout.tsv `
  -CrystalVersion 10.5.3700.0
```

## Image/Logo Command

Use `Extract-CrystalRptImages.ps1` when the layout manifest contains `PictureObject` or `BlobFieldObject` rows, or when the report visually contains logos/photos.

```powershell
powershell -ExecutionPolicy Bypass -File tools\Extract-CrystalRptImages.ps1 `
  -ReportPath Core\bin\x86\Debug\Reports\rptCAIT2023.rpt `
  -OutputDirectory tools\rptCAIT2023.images
```

The image extractor writes:

- `<report>.picture-objects.tsv` with every Crystal `PictureObject` and `BlobFieldObject`, including scope, section, object name, and coordinates.
- `<report>.binary-assets.tsv` with image candidates carved from the `.rpt` binary by JPG/PNG/GIF/BMP signatures.
- extracted image files such as `<report>_asset_001.jpg` or `<report>_asset_017.bmp`.

Important distinction:

- `PictureObject` rows are static report artwork. Crystal exposes their exact coordinates, but not a direct byte stream through the .NET runtime, so use the binary asset manifest to select the matching extracted file.
- `BlobFieldObject` rows are data-bound image fields. Those images must come from the report dataset/table in PHP, not from the static `.rpt` binary.

## Current Verified Runtime

The workspace machine has Crystal Reports assemblies in the legacy GAC. The extractor has been verified with `10.5.3700.0`, which matches the original VB project references.

## Migration Use

For PHP replication:

1. Trace the VB report caller and report path.
2. Trace the stored procedures and `DataTable.TableName` assignments used to build the report dataset.
3. Run this extractor on the exact `.rpt`.
4. Use the TSV rows as the layout manifest.
5. For subreports, add the parent subreport `LeftIn`/`TopIn` to each child object position.
6. Run the image extractor when `PictureObject` or `BlobFieldObject` rows are present.
7. Place selected static images at their extracted `PictureObject` coordinates and render data-bound images from the PHP dataset.
8. Convert Crystal formulas to PHP equivalents.
9. Render report pages without the normal app shell when printing should match VB/Crystal output.

Screenshots may still be useful for final printer alignment, but the extracted `.rpt` metadata is the authoritative starting point.
