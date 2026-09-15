-- Manual status override for a Purchase Order — sits on top of the existing
-- automatic Pending/Approved badge (derived from Date_Approved_PO). Blank/NULL
-- means "no override, use the automatic badge"; a value here (Terminated,
-- Cancelled, On Hold) replaces that badge until cleared. See the "Status"
-- quick field in purchase_orders.php's Additional Details card and the
-- PATCH handler in api/purchase_orders.php.

ALTER TABLE tbl_purchase_order
    ADD COLUMN `Status` VARCHAR(30) DEFAULT NULL AFTER `Remarks`;
