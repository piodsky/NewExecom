-- Competitive Bidding, second migration: lock/unlock and the DTS link.
--
-- Additive only. Two nullable columns on a table this module owns outright —
-- no existing column is touched, no data is rewritten, and the one sample row
-- simply gets NULLs. Safe to run while people are using the app.
--
--     mysql -u prroot -p purchase_request < database/tbl_competitive_bidding_alter.sql
--
-- MySQL 5.5 has no ADD COLUMN IF NOT EXISTS, so re-running this errors with
-- "Duplicate column name" rather than doing nothing. That is a loud, harmless
-- failure — it means the migration already ran.

-- Locked once the document set has been formally printed and issued, mirroring
-- tbl_purchase_order.Print_PO. The point is the same: a contract that has gone
-- out to a supplier must not be quietly edited afterwards, and unlocking is a
-- deliberate act restricted to the three procurement administrator roles.
--
-- INT rather than a boolean for consistency with Print_PO / Print_Obligation
-- on the purchase order table, which the same people read.
ALTER TABLE tbl_competitive_bidding
    ADD COLUMN Print_Bid INT(10) NULL DEFAULT 0 AFTER Remarks;

-- The DTS document number printed on the routing slip, so a bid document set
-- can be followed through the city's document tracking system the same way a
-- purchase order can. Free text and NOT unique: it is transcribed off a
-- printed slip, and refusing to save a typo twice would be worse than letting
-- someone fix it. Matches tbl_purchase_order.Barcode_Number in both name and
-- shape so dtsTrackBarcode() needs no special case.
ALTER TABLE tbl_competitive_bidding
    ADD COLUMN Barcode_Number VARCHAR(50) NULL AFTER Print_Bid;

-- Looked up whenever someone tracks a document by the number on the slip.
CREATE INDEX idx_cb_barcode ON tbl_competitive_bidding (Barcode_Number);


-- The City Treasurer signs the Contract Agreement as Witness — Procuring
-- Entity, but is not on file in ref_signatory (which holds only the City Mayor
-- and the City Budget Officer). Added here so the signatory fields can be a
-- picklist off master data instead of retyped free text on every contract.
--
-- Guarded so re-running does not create a duplicate: ref_signatory has no
-- unique key on Name, and two Treasurers on the picklist is exactly the kind
-- of mess this module is trying to avoid.
INSERT INTO ref_signatory (Name, Title, isDeleted)
SELECT 'BELLA STEPHANIE B. SAAVEDRA', 'City Treasurer', 0
  FROM DUAL
 WHERE NOT EXISTS (
     SELECT 1 FROM ref_signatory
      WHERE Name = 'BELLA STEPHANIE B. SAAVEDRA' AND isDeleted = 0
 );
