-- A single sample purchase order, for exercising the Inspection & Acceptance
-- module against a record that is safe to inspect, revise and undo.
--
--     mysql -u prroot -p purchase_request < database/sample_purchase_order.sql
--
-- Undo with database/sample_purchase_order_undo.sql, which removes this PO,
-- its item lines, and any inspection recorded against it.
--
-- This writes to the LIVE tbl_purchase_order alongside 26,000 real purchase
-- orders, so it is built to be unmistakable and to be found again:
--   * PO_Number 'PO-sample2026' matches nothing in the real numbering, which
--     is 'PO-sysYYYYnnnnn' / 'PO-YYYYnnnnsys'.
--   * the Purpose says in plain words that it is a test record.
-- Every other value is copied from the shapes actually in use on real POs
-- (supplier, department, mode of procurement, payment term, units), because a
-- sample made of placeholder text does not exercise the screen the same way.
--
-- Re-running is safe: each statement is guarded so it inserts only when the
-- row is not already there.


-- ── Header ───────────────────────────────────────────────────────────────
-- Inspect = 'NO' so the PO starts on the Awaiting list, which is where the
-- Inspection module expects to pick it up. Date_PR is 2 weeks before Date_PO,
-- so the lead-time figure that column was added for has something to measure.
INSERT INTO tbl_purchase_order
    (PO_Number, PR_Number, Date_PR, PR_Received_Name, Category, Department,
     Division, Section, Reference_No, BAC_Resolution_No, Supplier_Name,
     Supplier_Address, Date_PO, Purpose, End_User, Date_Created, isDeleted,
     Mode_of_Procurement, Place_of_Delivery, PO_Working_Days, PO_Payment_Term,
     Note, Requested_by, Barcode_Number, Inspect, Print_PO, Print_Obligation)
SELECT 'PO-sample2026', 'PR-sample2026', '2026-08-03', 'MARIA L. SANTOS',
       'Other Supplies', 'CITY HEALTH OFFICE', '', '', 'REF-sample2026', '',
       'AK TRADING', 'MALAYBALAY CITY', '2026-08-17 09:00:00',
       'SAMPLE RECORD - FOR TESTING THE INSPECTION AND ACCEPTANCE MODULE. NOT A REAL PURCHASE ORDER AND SAFE TO DELETE. Supplies for the use of the City Health Office.',
       'CITY HEALTH OFFICE', '2026-08-17 09:00:00', 0,
       'SVP', 'CGSO WAREHOUSE', '10 Working Days', '30 Working Days Payment Term',
       'Created as a test fixture, not from an actual purchase request.',
       'DR. JOSE R. CRUZ', '', 'NO', 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tbl_purchase_order WHERE PO_Number = 'PO-sample2026');


-- ── Item lines ───────────────────────────────────────────────────────────
-- Five priced lines, all with a Total Cost, and no description-only heading
-- rows: the inspection form requires a numeric quantity on every line it is
-- given, so a LOT or SCOPE OF WORK divider — legitimate on a purchase order —
-- would arrive here as a zero-quantity line and only confuse the test.
--
-- Line 3 is deliberately a long multi-line specification. That is what the real
-- item descriptions look like, and it is what exercises the growing description
-- box on the inspection form and the page breaking on the printed report.
--
-- Quantities are whole numbers because tbl_inspection_items_details stores
-- Quantity as int(40) — a fractional quantity would be silently rounded the
-- moment it was inspected, which is not something a sample should teach.
INSERT INTO tbl_purchase_order_items_details
    (PO_Number, Item_ID, Items_Description, Unit, Quantity, Unit_Cost, Total_Cost,
     Date_PO, Date_Created, End_User, isDeleted, NumberSeries)
SELECT * FROM (
    SELECT 'PO-sample2026' AS PO_Number, 1 AS Item_ID,
           'Bond Paper, Substance 20, Short (8.5 x 11 inches)' AS Items_Description,
           'ream' AS Unit, 50 AS Quantity, 285.000 AS Unit_Cost, 14250.000 AS Total_Cost,
           '2026-08-17 09:00:00' AS Date_PO, '2026-08-17 09:00:00' AS Date_Created,
           'CITY HEALTH OFFICE' AS End_User, 0 AS isDeleted, '' AS NumberSeries
    UNION ALL SELECT 'PO-sample2026', 2,
        'Alcohol, Ethyl, 70% Solution, 500ml', 'bot', 120, 92.500, 11100.000,
        '2026-08-17 09:00:00', '2026-08-17 09:00:00', 'CITY HEALTH OFFICE', 0, ''
    UNION ALL SELECT 'PO-sample2026', 3,
        CONCAT('Examination Table, Stainless Steel\n',
               '\n',
               'SPECIFICATIONS:\n',
               '   - Overall size: 1830mm (L) x 610mm (W) x 800mm (H)\n',
               '   - Frame: 38mm stainless steel tubing, grade 304\n',
               '   - Top: 1.2mm stainless steel sheet with padded vinyl cover\n',
               '   - Adjustable backrest, 0 to 75 degrees\n',
               '   - Pull-out step at the foot end\n',
               '   - With paper roll holder and side drawer\n',
               '\n',
               'WARRANTY: One (1) year on parts and workmanship, on site.'),
        'unit', 4, 18500.000, 74000.000,
        '2026-08-17 09:00:00', '2026-08-17 09:00:00', 'CITY HEALTH OFFICE', 0, ''
    UNION ALL SELECT 'PO-sample2026', 4,
        'Digital Thermometer, Non-contact Infrared, Forehead', 'pcs', 25, 1450.000, 36250.000,
        '2026-08-17 09:00:00', '2026-08-17 09:00:00', 'CITY HEALTH OFFICE', 0, ''
    UNION ALL SELECT 'PO-sample2026', 5,
        'Storage Cabinet, Steel, 4-Layer, Swing Door', 'unit', 6, 8750.000, 52500.000,
        '2026-08-17 09:00:00', '2026-08-17 09:00:00', 'CITY HEALTH OFFICE', 0, ''
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM tbl_purchase_order_items_details
    WHERE PO_Number = 'PO-sample2026' AND isDeleted = 0
);

-- Grand total: 188,100.00
