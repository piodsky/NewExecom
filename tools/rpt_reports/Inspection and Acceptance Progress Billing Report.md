# Report: Inspection and Acceptance Progress Billing Report.rpt

Source: `vb/Inspection and Acceptance/Crystal Reports/Inspection and Acceptance Progress Billing Report.rpt` + `Inspection and Acceptance Progress Billing Report.vb`

## OLE Compound File Stream Inventory

*(names/types/sizes only — Crystal's internal stream encoding isn't plain text, so contents aren't decoded)*

| Name | Type | Size (bytes) |
|---|---|---|
| Root Entry | root | 20416 |
| Contents | stream | 8166 |
| SummaryInformation | stream | 469 |
| QESession | stream | 2244 |
| Embedding 2 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 111994 |
| Embedding 3 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 313654 |
| Subdocument 2 | storage | 0 |
| Contents | stream | 2058 |
| QESession | stream | 2244 |
| Subdocument 3 | storage | 0 |
| Contents | stream | 1846 |
| QESession | stream | 2244 |
| Subdocument 4 | storage | 0 |
| Contents | stream | 1846 |
| QESession | stream | 2244 |
| Subdocument 5 | storage | 0 |
| Contents | stream | 1844 |
| QESession | stream | 2244 |
| ReportInfo | stream | 58 |
| CrystalReportDesignerStream | stream | 238 |

## Runtime Contract (from the paired VB.NET ReportClass wrapper)

- **Class**: `Inspection_and_Acceptance_Progress_Billing_Report`
- **Resource**: `Inspection and Acceptance Progress Billing Report.rpt`
- **Sections**: 5 — Section1, Section2, Section3, Section4, Section5
- **Parameters** (must be supplied via `SetParameterValue` before display):
  - `Department1`
  - `Department2`
  - `Department3`
  - `Inspectorate1`
  - `Inspectorate2`
  - `Inspectorate3`
  - `initial`
  - `DateToBilling`
  - `DateFromBilling`

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
