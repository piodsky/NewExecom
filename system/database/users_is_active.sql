-- Adds an Active/Inactive flag to purchase_request.users so an account can be
-- disabled without deleting it — requested 2026-09-10, after finding 45 of
-- 111 accounts point to a piid that no longer exists in HRMIS's
-- tblpersonalinformation (see the User Management review this same day).
-- Deleting those outright would be permanent and this table is genuinely
-- shared production auth (this app, the legacy VB desktop app, and every
-- other HRMIS module all read it — see api/hrmis_users.php's own comment on
-- that) — a soft toggle is the safer, reversible alternative to a DELETE.
--
-- DEFAULT 1 (Active) for every existing row, so nothing that can already log
-- in today loses access the moment this runs — deactivating specific
-- accounts (the 45 orphaned ones or anyone else) is a deliberate follow-up
-- action taken through User Management afterward, not a side effect of this
-- migration.
--
-- This column is only ENFORCED at login by this app's own api/auth.php — the
-- other HRMIS modules sharing this table are untouched code and will simply
-- ignore a column they don't query. Nothing here changes what they do.
ALTER TABLE users
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER Department;
