-- New reference table for the Purchase Order module's HRMIS database
-- (purchase_request @ 192.168.10.254). NOT executed automatically — this
-- file is a deliverable to review and run yourself (or ask Claude to run it)
-- since it changes the live server's schema.
--
-- Approving signatories for the print flow (Name + Title, e.g. the City
-- Mayor) — mirrors ref_supplier's shape (a value column plus one extra
-- column) since printing needs both the name and the title together.

-- This HRMIS server is MySQL 5.1.30 (confirmed via SHOW VARIABLES) — predates
-- utf8mb4 support (added in 5.5.3), and ref_place_of_delivery (the sibling
-- table this one is modeled on) is itself utf8, not utf8mb4, on this server.
-- Matching that rather than the utf8mb4 this app's own local DB uses.
CREATE TABLE IF NOT EXISTS `ref_signatory` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(150) NOT NULL,
  `Title` VARCHAR(100) DEFAULT NULL,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `ref_signatory` (`Name`, `Title`) VALUES
  ('JAY WARREN R. PABILLARAN', 'City Mayor');
