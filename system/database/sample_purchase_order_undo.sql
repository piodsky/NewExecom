-- Removes the sample purchase order created by database/sample_purchase_order.sql,
-- along with anything the Inspection & Acceptance module recorded against it.
--
--     mysql -u prroot -p purchase_request < database/sample_purchase_order_undo.sql
--
-- DELETE, not isDeleted = 1: this record never belonged in the table, so it
-- should leave no trace. That is the opposite of the rule for real purchase
-- orders, which are only ever soft-deleted — hence the very specific
-- PO_Number in every WHERE clause below, and no wildcards anywhere.
--
-- Safe to run whether or not the PO was ever inspected; each statement simply
-- matches nothing if there is nothing to remove.

DELETE FROM tbl_inspection_items_details WHERE PO_Number = 'PO-sample2026';
DELETE FROM tbl_inspection              WHERE PO_Number = 'PO-sample2026';
DELETE FROM tbl_purchase_order_items_details WHERE PO_Number = 'PO-sample2026';
DELETE FROM tbl_purchase_order          WHERE PO_Number = 'PO-sample2026';

-- The audit log entries and notifications the test generated are deliberately
-- left alone: they are a record of what was done in the system, which stays
-- true regardless of whether the purchase order they refer to still exists.
