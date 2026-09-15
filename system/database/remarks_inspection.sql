-- New reference table for the Inspection & Acceptance module's Remarks
-- fields (both the main Inspect Delivery dialog and Progress Billing) — user
-- requested 2026-09-09, with the ten values to seed it with. NOT executed
-- automatically — reviewed here first since it changes the live server's
-- schema.
--
-- Shaped like ref_remarks (system/database/tracking_dates_and_remarks.sql,
-- the PO/Competitive Bidding Remarks picklist): a value column plus
-- isDeleted, nothing else — this is a suggestion list, not a foreign key
-- anything else references, and neither Remarks field stops being free text
-- because of it. A SEPARATE table from ref_remarks rather than reusing it,
-- as asked for by name (tbl_remarks_inspection) — these ten are inspection/
-- billing status labels (CANCELLED P.O, WAITING FOR PAR/ICS, FOR
-- TERMINATION...), a different vocabulary from ref_remarks' general
-- procurement remarks, and merging the two lists would surface PO wording
-- while typing an inspection remark and vice versa.
--
-- utf8mb4 from the start (unlike ref_signatory/ref_place_of_delivery, which
-- matched this HRMIS server's older utf8 tables when they were created) —
-- this server is confirmed MySQL 8.4 now (see database/items_description_charset.sql
-- and friends), so there is no reason to seed a brand new table with anything
-- less than what the connection itself already speaks.
CREATE TABLE IF NOT EXISTS `tbl_remarks_inspection` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Value` VARCHAR(100) NOT NULL,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seeded in the order given, not alphabetised — that order reads like
-- frequency/priority, and alphabetising would bury "CANCELLED P.O" and
-- "PROGRESS BILLING" (presumably the common ones) under "FOR OBLIGATION" /
-- "FOR TERMINATION".
INSERT INTO `tbl_remarks_inspection` (`Value`) VALUES
  ('CANCELLED P.O'),
  ('CASH BASIS'),
  ('RETURN TO END USER'),
  ('WAITING FOR PAR/ICS'),
  ('WAIVED'),
  ('REIMBURSEMENT'),
  ('RETURN TO BAC'),
  ('FOR OBLIGATION'),
  ('PROGRESS BILLING'),
  ('FOR TERMINATION');
