-- Same latin1-vs-utf8mb4 fix as database/items_description_charset.sql,
-- extended to the Inspection & Acceptance and Inventory & Property modules
-- (user-requested 2026-09-09, right after the Purchase Order fix). NOT
-- executed automatically — reviewed here first, since it changes the live
-- server's schema.
--
-- Competitive Bidding was checked too and needs nothing: both its tables
-- (tbl_competitive_bidding, tbl_competitive_bidding_items_details) are
-- already utf8mb4 top to bottom — newer tables, built after this app already
-- knew better.
--
-- Inspection and Inventory are the opposite: EVERY column on all seven of
-- their tables is latin1, not just the description field, because these are
-- the oldest tables in the schema. Rather than picking out individual
-- columns the way the PO fix started (then widened to Unit/End_User once
-- asked), this converts each table whole with CONVERT TO CHARACTER SET,
-- which is the version of MODIFY that rewrites every column at once and
-- guarantees none is missed:
--   tbl_inspection                                   16,941 rows
--   tbl_inspection_items_details                     157,875 rows
--   tbl_inspection_progress_billing                  6 rows
--   tbl_inspection_items_details_progress_billing    22 rows
--   tbl_inventory                                    3,451 rows
--   tbl_inventory_items_details                      64,430 rows
--   tbl_inventory_sub_details                         2,178 rows
--
-- Same safety case as the PO fix: every value already in these columns is
-- guaranteed valid latin1 (nothing else could have been saved past this
-- server's STRICT_TRANS_TABLES check up to now), so CONVERT TO CHARACTER SET
-- reproduces each one exactly in utf8mb4 rather than reinterpreting bytes.
-- utf8mb4_general_ci again, to match the collation this app's own connection
-- already sends (config.php's PO_DB_CHARSET).
--
-- Columns that are really just short codes (isDeleted, Item_No, Bid_Number
-- and the like) are included too rather than picked around — a whole-table
-- CONVERT is one predictable operation per table instead of a per-column
-- judgement call repeated 90+ times, and utf8mb4 costs those columns nothing:
-- MySQL only spends the extra bytes a value actually uses, not the column's
-- worst case.
ALTER TABLE tbl_inspection                                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inspection_items_details                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inspection_progress_billing                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inspection_items_details_progress_billing  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inventory                                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inventory_items_details                    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE tbl_inventory_sub_details                      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
