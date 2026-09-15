# Report: Obligation Request Report.rpt

Source: `vb/Purchase Order/Crystal Reports/Obligation Request Report.rpt` + `Obligation Request Report.vb`

## OLE Compound File Stream Inventory

*(names/types/sizes only — Crystal's internal stream encoding isn't plain text, so contents aren't decoded)*

| Name | Type | Size (bytes) |
|---|---|---|
| Root Entry | root | 2816 |
| Contents | stream | 5416 |
| SummaryInformation | stream | 469 |
| QESession | stream | 1920 |
| Embedding 1 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 111994 |
| ReportInfo | stream | 58 |
| CrystalReportDesignerStream | stream | 110 |

## Runtime Contract (from the paired VB.NET ReportClass wrapper)

- **Class**: `Obligation_Request_Report`
- **Resource**: `Obligation Request Report.rpt`
- **Sections**: 5 — Section1, Section2, Section3, Section4, Section5
- **Parameters** (must be supplied via `SetParameterValue` before display):
  - `supplier_name`
  - `supplier_address`
  - `City_Budget`
  - `Category`
  - `End_User`
  - `Position`
  - `Department`

## Data Source

Data is pushed in, not queried live: the WinForms app builds an in-memory
`DataSet`, names each table to match what the report expects, and calls
`ReportDocument.SetDataSource(myDs)`. The table names and the exact SQL
live in the print button's click handler in the module's own forms —
`add_time_po.vb` for Purchase Order, `Inspect_Print.vb` for Inspection &
Acceptance — which is also where the `SetParameterValue` calls that fill
the parameters above are made.

Note that a report can bind a field the pushed DataSet never supplies; that
field simply renders blank. Check the SELECT list against the bindings
before assuming a placement is fed.
