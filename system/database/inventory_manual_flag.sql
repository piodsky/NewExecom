-- Flags an inventory header row entered directly (no linked Purchase Order)
-- rather than received from a real PO on file — legacy assets acquired
-- before this system existed. See api/inventory.php's POST handler's
-- `manual` branch and inventory.php's "Add Manually" flow.

ALTER TABLE tbl_inventory
    ADD COLUMN `Is_Manual` INT NOT NULL DEFAULT 0 AFTER `Deleted_by`;
