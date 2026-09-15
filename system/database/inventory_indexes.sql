-- Missing indexes on the Inventory & Property module's legacy tables.
--
--     mysql -u prroot -p purchase_request < database/inventory_indexes.sql
--
-- CAUTION, same note as tracking_dates_and_remarks.sql: tbl_inventory,
-- tbl_inventory_items_details and tbl_inventory_sub_details are not ours —
-- they are the legacy VB.NET app's tables, written by CGSO_Inventory.vb and
-- friends as well as by this module. This file only ADDs secondary indexes;
-- it touches no column and no row.
--
-- Confirmed live (information_schema.STATISTICS): all three tables carry
-- ONLY their PRIMARY KEY today — nothing on PO_Number, isDeleted or Inv_ID,
-- the columns api/inventory.php actually filters and joins on. That is why
-- inventory.php is slow: invGetList()'s three correlated subqueries per row
-- (item_count / total_value / issued_count) each full-scan
-- tbl_inventory_items_details (63,712 rows) for every one of up to ~500
-- header rows — measured at 19.4s for that one query with EXPLAIN showing
-- `type: ALL` on every subquery. tbl_purchase_order / tbl_purchase_order_
-- items_details already went through exactly this fix (idx_po_date_created_
-- deleted, idx_po_number, idx_date_created below, for comparison) — Inventory
-- never got the same treatment because it is a separate legacy table family.
--
-- MySQL 8.4 InnoDB builds a secondary index ALGORITHM=INPLACE by default —
-- unlike ADD COLUMN this does not rebuild the table and does not hold a
-- long write lock, so this is safe to run during office hours. Re-running is
-- a loud, harmless "Duplicate key name" error, same as the ADD COLUMN files.

-- tbl_inventory (3,305 rows): PO_Number lookups (invGetDetail, undo) and the
-- worklist's WHERE isDeleted ORDER BY Date_Created (invGetList's outer query,
-- currently `Using where; Using filesort` over every row).
ALTER TABLE tbl_inventory
    ADD INDEX idx_inv_po_deleted (PO_Number, isDeleted),
    ADD INDEX idx_inv_deleted_date (isDeleted, Date_Created),
    ALGORITHM=INPLACE, LOCK=NONE;

-- tbl_inventory_items_details (63,712 rows): the one that actually matters.
-- Every read in api/inventory.php filters this table by PO_Number + isDeleted
-- — the three invGetList() subqueries, invGetDetail()'s item fetch, and both
-- JOINs in invStats(). PAR_ICS_Number is left out of the index on purpose:
-- once PO_Number narrows a scan to one PO's handful of lines, filtering that
-- last predicate in memory is already cheap.
ALTER TABLE tbl_inventory_items_details
    ADD INDEX idx_invitem_po_deleted (PO_Number, isDeleted),
    ALGORITHM=INPLACE, LOCK=NONE;

-- tbl_inventory_sub_details (2,155 rows): invGetDetail()'s
-- `WHERE Inv_ID IN (...) AND isDeleted = 0` batch fetch.
ALTER TABLE tbl_inventory_sub_details
    ADD INDEX idx_invsub_inv_deleted (Inv_ID, isDeleted),
    ALGORITHM=INPLACE, LOCK=NONE;

-- For comparison — the equivalent indexes tbl_purchase_order and
-- tbl_purchase_order_items_details already carry (nothing to run, informational):
--   tbl_purchase_order:              idx_po_date_created_deleted (Date_Created, isDeleted, PO_Number)
--   tbl_purchase_order_items_details: idx_po_number (PO_Number), idx_date_created (Date_Created)

-- Verify:
--   SHOW INDEX FROM tbl_inventory;
--   SHOW INDEX FROM tbl_inventory_items_details;
--   SHOW INDEX FROM tbl_inventory_sub_details;
--
-- To undo:
--   ALTER TABLE tbl_inventory DROP INDEX idx_inv_po_deleted, DROP INDEX idx_inv_deleted_date;
--   ALTER TABLE tbl_inventory_items_details DROP INDEX idx_invitem_po_deleted;
--   ALTER TABLE tbl_inventory_sub_details DROP INDEX idx_invsub_inv_deleted;
