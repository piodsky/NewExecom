-- Removes the sample bid document created to demonstrate the module.
--
-- Hard DELETE, not the isDeleted = 1 soft delete the app uses: this row was
-- never real paperwork, so there is nothing worth keeping a tombstone for.
-- Scoped to the -SAMPLE suffix, so a genuine 2026-055 entered later is not
-- touched by it.
--
--     mysql -u prroot -p purchase_request < database/sample_competitive_bidding.sql

DELETE FROM tbl_competitive_bidding_items_details WHERE Bid_Number = '2026-055-SAMPLE';
DELETE FROM tbl_competitive_bidding               WHERE Bid_Number = '2026-055-SAMPLE';
