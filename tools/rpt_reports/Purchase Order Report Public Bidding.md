# Report: Purchase Order Report Public Bidding.rpt

Source: `vb/Purchase Order/Crystal Reports/Purchase Order Report Public Bidding.rpt` + `Purchase Order Report Public Bidding.vb`

## OLE Compound File Stream Inventory

*(names/types/sizes only — Crystal's internal stream encoding isn't plain text, so contents aren't decoded)*

| Name | Type | Size (bytes) |
|---|---|---|
| Root Entry | root | 3392 |
| Contents | stream | 8984 |
| SummaryInformation | stream | 469 |
| QESession | stream | 2273 |
| Embedding 8 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 111994 |
| Embedding 11 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 403230 |
| ReportInfo | stream | 58 |
| CrystalReportDesignerStream | stream | 110 |

## Runtime Contract (from the paired VB.NET ReportClass wrapper)

- **Class**: `Purchase_Order_Report_Public_Bidding`
- **Resource**: `Purchase Order Report Public Bidding.rpt`
- **Sections**: 5 — Section1, Section2, Section3, Section4, Section5
- **Parameters** (must be supplied via `SetParameterValue` before display):
  - `Items_Description`
  - `initial`
  - `city_mayor`
  - `Days`
  - `Place_of_Delivery`
  - `BAC_Reso`
  - `Ref_Number`
  - `Date_Printed`
  - `Note`
  - `PDays`
  - `Mode_of_Procurement`

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
