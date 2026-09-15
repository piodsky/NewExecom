-- Inventory / Property transfer HISTORY.
--
-- The legacy VB (`Transfer Form.vb`) prints an ITR (Inventory Transfer Report)
-- or PTR (Property Transfer Report) and, in the SAME action, runs:
--
--   UPDATE tbl_inventory_items_details
--   SET Accountable_Officer = ?, Position = ?, Location = ?,
--       Fund_Cluster = ?, Date_Transfered = ?
--   WHERE Inv_ID IN (...)
--
-- That overwrites the item's custodian in place. There is no PTR/ITR-by-PTR/ITR
-- table capturing who held it before, so an item transferred five times has
-- had five different accountable officers and the database remembers only the
-- last one — a real property-accountability gap, not a hypothetical one: COA
-- audits ask "who has signed for this since it was acquired," and today that
-- question can only be answered by finding the paper.
--
-- This adds an append-only log, written in the SAME transaction as the update
-- above (see api/inventory_issuance.php). tbl_inventory_items_details keeps
-- its current-custodian columns exactly as they are — nothing existing reads
-- differently — this is purely additive.
--
-- Two tables, matching the shape auto_parnew/auto_icsnew/auto_itr/auto_ptr
-- already use elsewhere in this schema: one row per transfer EVENT (which can
-- move several items at once, exactly like one ICS number can cover several
-- Inv_IDs), and a child row per item moved in that event.
--
-- Server is MySQL 8.4.11 on 192.168.11.70 (purchase_request). Run once.

CREATE TABLE `tbl_inventory_transfers` (
    `Transfer_ID`        int NOT NULL AUTO_INCREMENT,
    -- 'ITR' (Inventory Transfer Report, for ICS-classified/semi-expendable
    -- items) or 'PTR' (Property Transfer Report, for PAR-classified/
    -- capitalized property) — the same split the legacy form's two entry
    -- points (btnInventoryTransferForm / btnPropertyTransferForm) already draw.
    `Transfer_Type`       varchar(3)      NOT NULL,
    -- The printed number: "ITR2026800123" / "PTR2026800045", same format the
    -- legacy auto_itr/auto_ptr sequences already produce.
    `Transfer_Number`     varchar(255)    NOT NULL,
    `PO_Number`           varchar(30)     NULL DEFAULT NULL,
    -- Snapshot of the FROM side, taken at transfer time — this is the value
    -- that used to be silently overwritten with no record. The TO side is not
    -- duplicated here: it is whatever tbl_inventory_items_details carries as
    -- of this transfer, which the item rows already hold.
    `From_Accountable_Officer` varchar(255) NULL DEFAULT NULL,
    `From_Position`       varchar(255)    NULL DEFAULT NULL,
    `From_Location`       varchar(255)    NULL DEFAULT NULL,
    `To_Accountable_Officer`   varchar(255) NULL DEFAULT NULL,
    `To_Position`         varchar(255)    NULL DEFAULT NULL,
    `To_Location`         varchar(255)    NULL DEFAULT NULL,
    `Fund_Cluster`        varchar(255)    NULL DEFAULT NULL,
    `Reason_for_Transfer` varchar(500)    NULL DEFAULT NULL,
    -- Who signed the printed form — same two signatory pairs the legacy
    -- report parameters carry (CGSO_Officer/Position = approving,
    -- Warehouse_Officer/Position = issuing).
    `Approved_By`         varchar(255)    NULL DEFAULT NULL,
    `Approved_Position`   varchar(255)    NULL DEFAULT NULL,
    `Issued_By`           varchar(255)    NULL DEFAULT NULL,
    `Issued_Position`     varchar(255)    NULL DEFAULT NULL,
    `Date_Transferred`    datetime        NULL DEFAULT NULL,
    `Printed_By`          varchar(255)    NULL DEFAULT NULL,
    `Date_Created`        datetime        NULL DEFAULT NULL,
    `isDeleted`           int             NOT NULL DEFAULT '0',
    PRIMARY KEY (`Transfer_ID`),
    KEY `idx_transfer_po` (`PO_Number`),
    KEY `idx_transfer_number` (`Transfer_Number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `tbl_inventory_transfer_items` (
    `Transfer_Item_ID` int NOT NULL AUTO_INCREMENT,
    `Transfer_ID`       int NOT NULL,
    `Inv_ID`            int NOT NULL,
    -- A line's own description/tag/serial at the moment of transfer, so the
    -- printed ITR/PTR can be reconstructed later even if the item's own row
    -- is edited afterward (renamed, re-described, re-tagged).
    `Items_Description` text            NULL DEFAULT NULL,
    `Tag_Number`        varchar(100)    NULL DEFAULT NULL,
    `Serial_Number`     varchar(100)    NULL DEFAULT NULL,
    `Quantity`          int             NULL DEFAULT NULL,
    `Unit_Cost`         decimal(11,2)   NULL DEFAULT NULL,
    PRIMARY KEY (`Transfer_Item_ID`),
    KEY `idx_transfer_item_transfer` (`Transfer_ID`),
    KEY `idx_transfer_item_inv` (`Inv_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
--   SHOW TABLES LIKE 'tbl_inventory_transfer%';
--   SELECT COUNT(*) FROM tbl_inventory_transfers;         -- expect 0
--   SELECT COUNT(*) FROM tbl_inventory_transfer_items;    -- expect 0
--
-- To undo (nothing depends on these tables until the UI ships):
--   DROP TABLE tbl_inventory_transfer_items;
--   DROP TABLE tbl_inventory_transfers;
