-- Competitive Bidding module — the BAC contract document set.
--
-- One record here drives all three printed documents for a bid, because they
-- restate the same facts and must never disagree with each other:
--
--     Notice of Award      (SF-GOOD-49)  — bid date, contract price, bonds
--     Contract Agreement   (4 pages)     — the item grid, clauses, notary page
--     Notice to Proceed                  — commencement letter
--
-- Lives in `purchase_request` on the HRMIS server alongside tbl_po_notifications
-- and tbl_po_audit_logs, so a deploy box still needs no MySQL of its own.
-- Run once against the HRMIS server:
--
--     mysql -u prroot -p purchase_request < database/tbl_competitive_bidding.sql
--
-- utf8mb4, unlike the two tables above. Their file claims this server is MySQL
-- 5.1.30 (which predates utf8mb4); it is actually 5.5.62 and offers utf8mb4 —
-- verified with SELECT VERSION() and SHOW CHARACTER SET. The 767-byte index
-- prefix limit that bites utf8mb4 on 5.5 is avoided by indexing only
-- Bid_Number VARCHAR(50) (200 bytes).
--
-- Additive only: CREATE TABLE IF NOT EXISTS touches no existing table, and
-- neither table shares a name with anything in the 101 already here.
--
-- Column naming follows tbl_purchase_order's PascalCase_With_Underscores and
-- its isDeleted soft-delete convention, so the two modules read the same way.

CREATE TABLE IF NOT EXISTS tbl_competitive_bidding (
    ID                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identity. Bid_Number is BAC's own numbering off their paperwork
    -- ("2026-055"), typed in rather than generated here — unlike PO_Number,
    -- which this app mints. It is what all three documents are filed under.
    Bid_Number             VARCHAR(50)   NULL,
    Contract_Number        VARCHAR(50)   NULL,   -- "2026-130", Contract only
    Contract_Name          VARCHAR(255)  NULL,   -- "PURCHASE OF 1 UNIT WHEEL LOADER"
    Contract_Location      VARCHAR(255)  NULL,   -- "CDRRMO, MALAYBALAY CITY"

    -- Supplier / winning bidder. Kept as free text rather than a ref_supplier
    -- id for the same reason the purchase orders do: the name printed on the
    -- contract is the name as it appeared on the bid, and must not change
    -- retroactively when the master list is tidied up.
    Supplier_Name          VARCHAR(255)  NULL,
    Supplier_Address       VARCHAR(255)  NULL,
    Supplier_Address2      VARCHAR(255)  NULL,   -- "w/ Branch office at ..."
    Supplier_Org_Place     VARCHAR(255)  NULL,   -- "...existing under the laws of Quezon City"
    Representative_Name    VARCHAR(255)  NULL,   -- signs for the supplier
    Representative_Title   VARCHAR(255)  NULL,   -- "Account Manager"

    -- Money. Contract_Amount is the awarded total; it is stored rather than
    -- summed from the items because the two bond figures are struck from it
    -- and an item edit must not silently restate an executed contract.
    Contract_Amount        DECIMAL(15,2) NULL,
    Cash_Bond_Amount       DECIMAL(15,2) NULL,   -- 5% of contract price
    Surety_Bond_Amount     DECIMAL(15,2) NULL,   -- 30% of contract price

    -- Delivery, printed in the band under the item grid.
    Place_of_Delivery      VARCHAR(255)  NULL,
    Delivery_Term          VARCHAR(100)  NULL,   -- "30 C.D."

    -- Dates. DATE, not the VARCHAR(255) that tbl_purchase_order uses for its
    -- date columns — that is legacy shape, not a pattern worth copying.
    Bid_Date               DATE          NULL,   -- date of the bid being accepted
    Contract_Date          DATE          NULL,   -- "executed on the __ day of __"
    NOA_Date               DATE          NULL,   -- Notice of Award
    NTP_Date               DATE          NULL,   -- Notice to Proceed

    -- Signatories, printed on all three documents.
    Signatory_Name         VARCHAR(255)  NULL,   -- Head of the Procuring Entity
    Signatory_Title        VARCHAR(255)  NULL,
    Witness_Entity_Name    VARCHAR(255)  NULL,   -- Witness — Procuring Entity
    Witness_Entity_Title   VARCHAR(255)  NULL,
    Witness_Supplier_Name  VARCHAR(255)  NULL,   -- Witness — Supplier

    -- Notary block on the Acknowledgment page. Every one of these is written
    -- by hand on the paper copy, so all are optional: a blank prints as the
    -- ruled line the notary fills in.
    Notary_Name            VARCHAR(255)  NULL,
    Notary_Place           VARCHAR(255)  NULL,   -- "MANOLO FORTICH, BUKIDNON"
    Notary_Doc_No          VARCHAR(20)   NULL,
    Notary_Page_No         VARCHAR(20)   NULL,
    Notary_Book_No         VARCHAR(20)   NULL,
    Notary_Series          VARCHAR(20)   NULL,

    Remarks                TEXT          NULL,

    Date_Created           DATETIME      NULL,
    Created_By             VARCHAR(50)   NULL,   -- HRMIS username, as tbl_purchase_order does
    Edited_By              VARCHAR(50)   NULL,
    Deleted_by             VARCHAR(50)   NULL,
    isDeleted              INT(5)        NULL DEFAULT 0,

    -- The list screen filters on Date_Created and looks records up by
    -- Bid_Number, the same two access patterns the PO list has.
    INDEX idx_cb_bid_number (Bid_Number),
    INDEX idx_cb_created    (Date_Created),
    INDEX idx_cb_deleted    (isDeleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Item grid on page 2-3 of the Contract Agreement.
--
-- Same two-shape rule as tbl_purchase_order_items_details: a row is either a
-- DESCRIPTION-ONLY row (a heading or a specification line — every other column
-- blank) or a PRICED row (it has a Total_Cost). Nothing in between. On the
-- sample contract "WHEEL LOADER" is the priced row and the thirty-odd
-- specification lines beneath it are description-only.
--
-- Joined back by Bid_Number rather than by the parent's ID, matching how
-- tbl_purchase_order_items_details joins on PO_Number.
CREATE TABLE IF NOT EXISTS tbl_competitive_bidding_items_details (
    ID                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Bid_Number        VARCHAR(50)   NULL,
    Item_No           VARCHAR(20)   NULL,   -- printed "Item No." column; blank on spec rows
    Items_Description TEXT          NULL,
    Unit              VARCHAR(255)  NULL,
    Quantity          DECIMAL(11,3) NULL,
    Unit_Cost         DECIMAL(15,3) NULL,
    Total_Cost        DECIMAL(15,3) NULL DEFAULT 0.000,
    -- Print order. tbl_purchase_order_items_details relies on its ID order
    -- instead, which quietly makes reordering an existing row impossible;
    -- an explicit column costs nothing and avoids inheriting that.
    Sort_Order        INT           NULL DEFAULT 0,
    Date_Created      DATETIME      NULL,
    isDeleted         INT(10)       NULL DEFAULT 0,
    INDEX idx_cbi_bid    (Bid_Number),
    INDEX idx_cbi_sort   (Bid_Number, Sort_Order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
