-- Date of Receipt on Inspection & Acceptance.
--
-- tbl_inspection has Date_BAC_Received, Date_Inspected and Date_Created, but
-- nowhere to record the date the goods actually arrived. Three things point at
-- the same answer:
--
--   1. tbl_inspection_progress_billing ALREADY has `Delivery_Date` (datetime),
--      and the progress-billing flow already reads and prints it. The two
--      tables are otherwise column-for-column siblings; this is the gap.
--
--   2. `Inspection and Acceptance Report.rpt` binds its Date cell to
--      tbl_inspection.Delivery_Date — a column that has never existed, which is
--      why that cell prints ruled and empty on every ordinary inspection while
--      the same cell on the progress-billing form is filled. See the note in
--      js/iar-print.js. Adding the column closes a hole the report has had
--      since it was written.
--
--   3. It is the date the office means by "date of receipt".
--
-- Nullable, no default, no backfill: every one of the ~11,000 existing
-- inspections keeps a NULL here and prints exactly as it does today. Nothing
-- reads it until somebody fills it in.
--
-- Server is MySQL 8.4.11 on 192.168.11.70. Run once.

ALTER TABLE `tbl_inspection`
    ADD COLUMN `Delivery_Date` datetime NULL DEFAULT NULL AFTER `Date_BAC_Received`;

-- Verify:
--   SHOW COLUMNS FROM `tbl_inspection` LIKE 'Delivery_Date';
--   SELECT COUNT(*) FROM `tbl_inspection` WHERE `Delivery_Date` IS NOT NULL;   -- expect 0
--
-- To undo (the column is additive and nothing depends on it until the UI ships):
--   ALTER TABLE `tbl_inspection` DROP COLUMN `Delivery_Date`;
