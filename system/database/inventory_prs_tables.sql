-- Property Return Slip (PRS) — dedicated tables, not the legacy auto_itr/
-- auto_ptr numbering-log pattern (those exist only to keep continuity with
-- numbers the old VB.NET app already issued; PRS never had a legacy
-- equivalent, so there is no old sequence to stay compatible with).
--
-- PRS_Number is derived directly from this table's own PRS_ID (same
-- approach as poNumberBarcode() in config.php: the row's own reliable
-- auto-increment id + the date, computed in PHP right after INSERT) —
-- no separate sequence table needed just to hand back a number.
--
-- Shaped like tbl_inventory_transfers/tbl_inventory_transfer_items (this
-- session's own modern design for ITR/PTR), not reused directly — a return
-- has its own fields (Condition_On_Return, Returned_To, Received_By) that
-- don't map cleanly onto a transfer's "To another employee" shape.

CREATE TABLE `tbl_inventory_prs` (
  `PRS_ID` int NOT NULL AUTO_INCREMENT,
  `PRS_Number` varchar(50) DEFAULT NULL,
  `PO_Number` varchar(30) DEFAULT NULL,
  `From_Accountable_Officer` varchar(255) DEFAULT NULL,
  `From_Position` varchar(255) DEFAULT NULL,
  `From_Location` varchar(255) DEFAULT NULL,
  `Returned_To` varchar(255) DEFAULT NULL,
  `Condition_On_Return` varchar(100) DEFAULT NULL,
  `Reason_for_Return` varchar(500) DEFAULT NULL,
  `Received_By` varchar(255) DEFAULT NULL,
  `Received_Position` varchar(255) DEFAULT NULL,
  `Approved_By` varchar(255) DEFAULT NULL,
  `Approved_Position` varchar(255) DEFAULT NULL,
  `Date_Returned` datetime DEFAULT NULL,
  `Printed_By` varchar(255) DEFAULT NULL,
  `Date_Created` datetime DEFAULT NULL,
  `isDeleted` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`PRS_ID`),
  KEY `idx_prs_po` (`PO_Number`),
  KEY `idx_prs_number` (`PRS_Number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `tbl_inventory_prs_items` (
  `PRS_Item_ID` int NOT NULL AUTO_INCREMENT,
  `PRS_ID` int NOT NULL,
  `Inv_ID` int NOT NULL,
  `Items_Description` text,
  `Tag_Number` varchar(100) DEFAULT NULL,
  `Serial_Number` varchar(100) DEFAULT NULL,
  `Quantity` int DEFAULT NULL,
  `Unit_Cost` decimal(11,2) DEFAULT NULL,
  PRIMARY KEY (`PRS_Item_ID`),
  KEY `idx_prs_item_prs` (`PRS_ID`),
  KEY `idx_prs_item_inv` (`Inv_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
