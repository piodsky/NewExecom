-- Procurement tracking dates, and a Remarks picklist shared by Purchase Orders
-- and Competitive Bidding.
--
--     mysql -u prroot -p purchase_request < database/tracking_dates_and_remarks.sql
--
-- Read the note on tbl_purchase_order below before running: it is the only
-- statement here that touches a table this app did not create.
--
-- MySQL 5.5 has no ADD COLUMN IF NOT EXISTS, so re-running errors with
-- "Duplicate column name". That is a loud, harmless failure meaning it has
-- already been applied.


-- ── 1. Date of the Purchase Request ──────────────────────────────────────
--
-- On BOTH documents, so the time between a request being raised and the
-- resulting paperwork being issued can actually be measured. That comparison
-- is the whole point: tbl_purchase_order already records when the PO was cut
-- (Date_PO), when the supplier received it and when COA saw it, but nothing
-- anywhere records when the request that started it was made, so every
-- lead-time figure currently begins in the middle of the story.
--
-- CAUTION, and the reason this file is separate: tbl_purchase_order is not
-- ours. It holds 26,000+ live rows and is written by the legacy VB.NET app and
-- other HRMIS modules as well as by this one. ADD COLUMN on InnoDB in MySQL
-- 5.5 REBUILDS THE TABLE and holds a write lock for the duration — on a table
-- this size expect it to be locked for a short while, so run it outside office
-- hours. It is additive: no existing column is touched and every existing row
-- gets NULL, which every reader already tolerates.
--
-- DATE, not the VARCHAR(255) that the older date columns on that table use.
-- That shape is legacy, it is why those columns cannot be compared or sorted
-- without parsing, and it is not worth copying into a column added for the
-- express purpose of being compared against other dates.
ALTER TABLE tbl_purchase_order
    ADD COLUMN Date_PR DATE NULL AFTER PR_Number;

ALTER TABLE tbl_competitive_bidding
    ADD COLUMN Date_PR DATE NULL AFTER Contract_Location;


-- ── 2. The rest of the Competitive Bidding tracking dates ────────────────
--
-- Date_Received_NOA is distinct from the existing NOA_Date, and the pair is
-- the point: NOA_Date is the date printed on the Notice of Award, while this
-- is the date the supplier actually received it. The gap between them is what
-- the ten-calendar-day performance security clock is measured against.
ALTER TABLE tbl_competitive_bidding
    ADD COLUMN Date_Received_NOA DATE NULL AFTER NOA_Date;

-- When the performance security was posted. Contract_Date and NTP_Date, the
-- other two dates in this set, already exist on the table.
ALTER TABLE tbl_competitive_bidding
    ADD COLUMN Date_Performance_Bond DATE NULL AFTER Date_Received_NOA;


-- ── 3. Remarks picklist ──────────────────────────────────────────────────
--
-- Remarks on both documents is free text, which is how "Forwarded to CGSO"
-- ends up on file as five different spellings and stops being something you
-- can filter or count. Same problem, and same fix, as the supplier names this
-- module already had to clean up.
--
-- A picklist, NOT a foreign key: both Remarks columns stay free text, because
-- a remark that is genuinely one-off must still be typeable. This only offers
-- the common ones so the common ones stay identical.
CREATE TABLE IF NOT EXISTS ref_remarks (
    ID        INT AUTO_INCREMENT PRIMARY KEY,
    Value     VARCHAR(255) NOT NULL,
    isDeleted INT(10)      NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seeded with the one the user named plus the obvious siblings. Master Data ->
-- Remarks is where the rest get added; nothing here is special-cased in code.
-- Guarded individually so re-running cannot duplicate a row.
INSERT INTO ref_remarks (Value, isDeleted)
SELECT * FROM (
    SELECT 'Forwarded to CGSO'        AS Value, 0 AS isDeleted
    UNION ALL SELECT 'Forwarded to Accounting', 0
    UNION ALL SELECT 'Forwarded to Budget',     0
    UNION ALL SELECT 'Forwarded to COA',        0
    UNION ALL SELECT 'For signature',           0
    UNION ALL SELECT 'For pick-up',             0
    UNION ALL SELECT 'Released to supplier',    0
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM ref_remarks WHERE ref_remarks.Value = seed.Value);
    