# Report: PO Report Report.rpt

Source: `vb/Purchase Order/Crystal Reports/PO Report Report.rpt`

## OLE Compound File Stream Inventory

*(names/types/sizes only — Crystal's internal stream encoding isn't plain text, so contents aren't decoded)*

| Name | Type | Size (bytes) |
|---|---|---|
| Root Entry | root | 6784 |
| Contents | stream | 3713 |
| SummaryInformation | stream | 469 |
| QESession | stream | 1902 |
| Embedding 1 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 111994 |
| Embedding 2 | storage | 0 |
| Ole | stream | 20 |
| CompObj | stream | 106 |
| CONTENTS | stream | 313654 |
| ReportInfo | stream | 58 |
| CrystalReportDesignerStream | stream | 110 |

## Runtime Contract (from the paired VB.NET ReportClass wrapper)

_No paired .vb wrapper found — parameters/sections unknown._

## Data Source

Data is pushed in, not queried live: the WinForms app builds an in-memory
`DataSet` with tables named `tbl_purchase_order` and
`tbl_purchase_order_items_details` (items query additionally selects
`NumberSeries` for the By-LOT / Public-Bidding variants) and calls
`ReportDocument.SetDataSource(myDs)` — see `add_time_po.vb`, the print
buttons' click handlers, for the exact header/items SQL and the
parameter values it supplies.
