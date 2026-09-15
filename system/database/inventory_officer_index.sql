-- Index for the new Personnel Memorandum Receipt (MR) feature — the first
-- place either table is ever queried BY Accountable_Officer rather than by
-- PO_Number/Inv_ID. Same caution as inventory_indexes.sql: these two tables
-- are the legacy VB.NET app's, not ours; this file only ADDs indexes, it
-- touches no column and no row.
--
-- Both columns are varchar(255), well under InnoDB's key-length limit, so a
-- plain (not prefix) index on each. ALGORITHM=INPLACE / LOCK=NONE, same as
-- inventory_indexes.sql, so this is safe to run during office hours.
ALTER TABLE tbl_inventory_items_details
    ADD INDEX idx_invitem_officer_deleted (Accountable_Officer, isDeleted),
    ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE tbl_inventory_sub_details
    ADD INDEX idx_invsub_officer_deleted (Accountable_Officer, isDeleted),
    ALGORITHM=INPLACE, LOCK=NONE;

-- Verify:
--   SHOW INDEX FROM tbl_inventory_items_details;
--   SHOW INDEX FROM tbl_inventory_sub_details;
--
-- To undo:
--   ALTER TABLE tbl_inventory_items_details DROP INDEX idx_invitem_officer_deleted;
--   ALTER TABLE tbl_inventory_sub_details DROP INDEX idx_invsub_officer_deleted;
