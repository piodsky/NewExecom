-- Progress billing for Inspection & Acceptance.
--
-- Both tables ALREADY EXIST on the live database — the legacy VB created them
-- and Inspect.vb's save_click_progress_billing() writes to them. They are
-- empty (header 0 rows, items 3 orphans from a single attempt on 2025-08-05)
-- for a reason worth recording: save_click_progress_billing() inserts
-- Invoice_Number, BARCODE_Number, Date_BAC_Received and Remarks into
-- tbl_inspection_progress_billing, and NONE of those columns exist there. The
-- item rows went in, the header insert threw "Unknown column", and the whole
-- routine sits inside `Catch ex As Exception` with an empty body — so the
-- operator was told nothing and the feature has never once worked.
--
-- This adds the four missing columns so that path can complete, plus the few
-- extra ones the PHP flow needs. Every addition is nullable with no default
-- change, and there are no header rows to migrate.
--
-- MySQL 5.5 — no ADD COLUMN IF NOT EXISTS, so this is a one-shot script.

ALTER TABLE `tbl_inspection_progress_billing`
    -- Billing_No is the sort key. Progress_Billing keeps the legacy label
    -- ("Progress Billing - 3") because the Crystal report prints that string;
    -- ordering or finding "the next billing" by parsing it is the kind of
    -- thing that works until someone types a label by hand.
    ADD COLUMN `Billing_No`        int(11)      NULL DEFAULT NULL AFTER `Progress_Billing`,
    -- The four the VB writes and the table never had.
    ADD COLUMN `Invoice_Number`    text         NULL              AFTER `Item_Transit_Receipt_Number`,
    ADD COLUMN `BARCODE_Number`    varchar(255) NULL DEFAULT NULL AFTER `Invoice_Number`,
    ADD COLUMN `Date_BAC_Received` datetime     NULL DEFAULT NULL AFTER `BARCODE_Number`,
    ADD COLUMN `Remarks`           varchar(255) NULL DEFAULT NULL AFTER `Date_BAC_Received`,
    -- The billing period. The legacy stamps these on every ITEM row, which is
    -- one period repeated N times; it belongs on the header. Written to both,
    -- so the legacy report still reads what it expects.
    ADD COLUMN `Date_From_Billing` date         NULL DEFAULT NULL AFTER `Billing_No`,
    ADD COLUMN `Date_To_Billing`   date         NULL DEFAULT NULL AFTER `Date_From_Billing`,
    -- Pairs a header to its item rows across a revision, exactly as
    -- Date_Inspected does on tbl_inspection.
    ADD COLUMN `Date_Inspected`    datetime     NULL DEFAULT NULL AFTER `Delivery_Date`,
    ADD COLUMN `Date_Created`      datetime     NULL DEFAULT NULL AFTER `Date_Inspected`,
    ADD COLUMN `Edited_by`         varchar(255) NULL DEFAULT NULL AFTER `Accepted_by`,
    ADD KEY `idx_ipb_po_deleted` (`PO_Number`(60), `isDeleted`);

ALTER TABLE `tbl_inspection_items_details_progress_billing`
    ADD COLUMN `Billing_No`     int(11)  NULL DEFAULT NULL AFTER `Progress_Billing`,
    ADD COLUMN `Date_Inspected` datetime NULL DEFAULT NULL AFTER `Date_Created`,
    ADD KEY `idx_ipbi_po_deleted` (`PO_Number`(60), `isDeleted`);

-- The three orphaned item rows from the failed 2025-08-05 attempt. They have
-- no header, an empty Progress_Billing, and belong to a PO nobody has billed
-- since. Left in place deliberately: they are the evidence for the note at the
-- top of this file, they are invisible to every query in the new flow (which
-- requires Billing_No), and deleting live rows to tidy up is not this script's
-- job. To clear them by hand, if you ever want to:
--     UPDATE tbl_inspection_items_details_progress_billing
--        SET isDeleted = 1
--      WHERE Billing_No IS NULL AND (Progress_Billing IS NULL OR Progress_Billing = '');
