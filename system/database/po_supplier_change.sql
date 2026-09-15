-- Change-of-Supplier justification letter fields — a single overwritable
-- record per PO (same convention as the Additional Details quick fields),
-- filled in when the awarded supplier is not the lowest bidder, so there is
-- a printable attachment on file naming both suppliers and the reason. See
-- the "Change of Supplier" card in purchase_orders.php and its combined
-- PATCH handler in api/purchase_orders.php.

ALTER TABLE tbl_purchase_order
    ADD COLUMN `Supplier_Change_Flag` INT NOT NULL DEFAULT 0 AFTER `Status`,
    ADD COLUMN `Supplier_Change_Winning` VARCHAR(255) DEFAULT NULL AFTER `Supplier_Change_Flag`,
    ADD COLUMN `Supplier_Change_Previous` VARCHAR(255) DEFAULT NULL AFTER `Supplier_Change_Winning`,
    ADD COLUMN `Supplier_Change_Justification` VARCHAR(1000) DEFAULT NULL AFTER `Supplier_Change_Previous`,
    ADD COLUMN `Supplier_Change_Signatory_Name` VARCHAR(150) DEFAULT NULL AFTER `Supplier_Change_Justification`,
    ADD COLUMN `Supplier_Change_Signatory_Title` VARCHAR(100) DEFAULT NULL AFTER `Supplier_Change_Signatory_Name`;
