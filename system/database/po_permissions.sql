-- Per-role Purchase Order action permissions: Lock/Unlock, Delete, Edit.
--
--     mysql -u prroot -p purchase_request < database/po_permissions.sql
--
-- Own table, not a new column on HRMIS's shared system_access_level — these
-- three actions (lock a PO, delete a PO, edit a PO) are concepts specific to
-- this app; the legacy VB.NET apps have no notion of them, so they do not
-- belong on the table those apps read. Pure CREATE TABLE, no ALTER on
-- anything shared — nothing existing is touched or locked.
--
-- User-requested 2026-09-09: "the administrator can lock unlock delete edit
-- the inside of the PO" — configurable per role, same as everything else on
-- Access Levels, replacing the role_id IN (1,2,3) hardcoded in
-- api/purchase_orders.php (and closing a real gap: Edit had NO permission
-- check at all before this — anyone with Purchase Orders module access could
-- edit any unlocked PO via a direct API call).
--
-- One row PER ROLE (role_id is the PRIMARY KEY, not a per-person piid the way
-- system_access_level works) — role-level control only, no per-account
-- overrides, by explicit choice. A role with no row here is not "denied": see
-- poActionPermission() in config.php, which falls back to today's exact
-- behaviour (role_id 1/2/3 get Lock+Delete+Edit, everyone else gets Edit
-- only) for any role that has never been touched from Access Levels — so
-- this table starts EMPTY and changes nothing until an admin actually ticks
-- a box.
CREATE TABLE `tbl_po_permissions` (
    `role_id`      INT          NOT NULL,
    `can_lock`     TINYINT(1)   NOT NULL DEFAULT 0,
    `can_delete`   TINYINT(1)   NOT NULL DEFAULT 0,
    `can_edit`     TINYINT(1)   NOT NULL DEFAULT 1,
    `Date_Updated` DATETIME     NULL DEFAULT NULL,
    `Updated_By`   VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
--   SELECT * FROM tbl_po_permissions;   -- expect 0 rows
--
-- To undo (nothing depends on this until the Access Levels UI ships):
--   DROP TABLE tbl_po_permissions;
