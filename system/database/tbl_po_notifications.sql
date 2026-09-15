-- New, self-contained notification tables for the Purchase Order module's
-- HRMIS database (purchase_request @ 192.168.10.254). NOT executed
-- automatically — this file is a deliverable to review and run yourself
-- (or ask Claude to run it) since it changes the live server's schema.
--
-- Purely additive: two brand-new tables, no ALTER on anything that already
-- exists, no FOREIGN KEY constraints pointing at `users`/`tbl_purchase_order`
-- (this DB doesn't use FKs anywhere else either — ref_supplier, ref_division,
-- etc. are all plain int references) — so this cannot touch, lock, or break
-- any existing table, and can be dropped again with zero side effects.
--
-- Confirmed live schema this design lines up with: `users` has `user_id`
-- (int) and `role_id` (int, joins to `table_role.role_id`) — the same
-- user_id the app already stores as $_SESSION['pbs_user_id'] on login
-- (see api/auth.php), so no separate user-mapping step is needed.
--
-- Mirrors the shape of this app's existing LOCAL notifications/
-- notification_reads tables (pyobasesystem_db, see database/schema.sql):
-- broadcast a notification either to a whole role or to one specific user,
-- track who has read it independently. Nothing reads/writes this table yet
-- — wiring the PO module's notify calls and the topbar bell up to it is a
-- separate follow-up once this schema is actually in place.

CREATE TABLE IF NOT EXISTS `tbl_po_notifications` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  -- Exactly one of Role_ID / User_ID should be set: Role_ID broadcasts to
  -- everyone with that table_role.role_id (e.g. all admins); User_ID targets
  -- one specific users.user_id instead.
  `Role_ID` INT(11) DEFAULT NULL,
  `User_ID` INT(11) DEFAULT NULL,
  `Title` VARCHAR(150) NOT NULL,
  `Message` VARCHAR(255) DEFAULT NULL,
  `Link` VARCHAR(255) DEFAULT NULL,
  `PO_Number` VARCHAR(50) DEFAULT NULL,
  `Created_By` INT(11) DEFAULT NULL,
  `Date_Created` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `idx_role` (`Role_ID`),
  KEY `idx_user` (`User_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_po_notification_reads` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Notification_ID` INT(11) NOT NULL,
  `User_ID` INT(11) NOT NULL,
  `Date_Read` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uniq_notif_user` (`Notification_ID`, `User_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
