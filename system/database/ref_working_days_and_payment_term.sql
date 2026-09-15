-- New reference tables for the Purchase Order module's HRMIS database
-- (purchase_request @ 192.168.10.254). NOT executed automatically — this
-- file is a deliverable to review and run yourself (or ask Claude to run it)
-- since it changes the live server's schema.
--
-- Shaped like the existing simple picklist tables (ref_mode_of_procurement,
-- ref_po_users): ID, a single value column, isDeleted for soft-delete so
-- Master Data's "remove" action never hard-deletes a row that something
-- else might already reference.

CREATE TABLE IF NOT EXISTS `ref_working_days` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Value` VARCHAR(50) NOT NULL,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ref_working_days` (`Value`) VALUES
  ('0'), ('5'), ('10'), ('15'), ('20'), ('25'), ('30'), ('45'),
  ('As Needed'),
  ('Saturday'), ('Sunday'), ('Monday'), ('Tuesday'), ('Wednesday'), ('Thursday'), ('Friday'),
  ('Daily'), ('Weekly'), ('Monthly'), ('Yearly');

CREATE TABLE IF NOT EXISTS `ref_payment_term` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Value` VARCHAR(50) NOT NULL,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ref_payment_term` (`Value`) VALUES
  ('0'), ('5'), ('10'), ('15'), ('20'), ('25'), ('30'),
  ('10%'), ('20%'), ('30%'), ('40%'), ('50%'),
  ('Cash Basis'), ('As Needed'), ('Immediately'), ('Progress Billing'),
  ('Saturday'), ('Sunday'), ('Monday'), ('Tuesday'), ('Wednesday'), ('Thursday'), ('Friday'),
  ('Daily'), ('Weekly'), ('Monthly'), ('Yearly');
