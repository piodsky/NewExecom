-- Competitive Bidding: the Purchase Request amount, so savings can be measured.
--
--     mysql -u prroot -p purchase_request < database/cb_pr_amount.sql
--
-- Additive, one nullable column on a table this module owns outright. No
-- existing column is touched and every existing row gets NULL. Safe to run
-- while people are using the app.
--
-- MySQL 5.5 has no ADD COLUMN IF NOT EXISTS, so re-running errors with
-- "Duplicate column name". That is a loud, harmless failure meaning it has
-- already been applied.


-- ── The figure the request was raised for ────────────────────────────────
--
-- The point of the pair is the difference. Contract_Amount is what the winning
-- bidder was awarded; this is what the Purchase Request that started the
-- procurement was budgeted at — the approved budget the bidding was run
-- against. Subtract one from the other and you have the thing nobody could
-- report on before: how much the competitive bidding actually saved.
--
-- Date_PR, the other half of the request's identity, is already on this table
-- (see database/tracking_dates_and_remarks.sql) and already on the form.
--
-- DECIMAL(15,2), matching Contract_Amount exactly — two figures that exist to
-- be subtracted from each other must not round differently.
--
-- NULL, not 0, when it has not been recorded. The distinction carries real
-- weight here: a bid with no PR amount on file is a bid whose savings are
-- UNKNOWN, and defaulting it to zero would report every one of those as a
-- 100% saving. Competitive Insights counts them separately and says so.
ALTER TABLE tbl_competitive_bidding
    ADD COLUMN PR_Amount DECIMAL(15,2) NULL AFTER Date_PR;
