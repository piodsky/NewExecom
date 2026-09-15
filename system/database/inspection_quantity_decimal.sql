-- Inspection & Acceptance quantity columns are INT, unlike the Purchase Order
-- items table's decimal(11,3) Quantity — a delivery measured in fractions
-- (e.g. 0.3 cu.m. of sand) silently truncates to 0 when the inspection is
-- saved. Match the PO table's precision so fractional deliveries survive.
--
-- Existing whole-number rows are unaffected (INT -> DECIMAL is a widening
-- change, no data loss).

ALTER TABLE tbl_inspection_items_details
    MODIFY COLUMN Quantity DECIMAL(11,3) NULL;

ALTER TABLE tbl_inspection_items_details_progress_billing
    MODIFY COLUMN Quantity DECIMAL(11,3) NULL;
