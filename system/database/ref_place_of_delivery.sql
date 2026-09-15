-- New reference table for the Purchase Order module's HRMIS database
-- (purchase_request @ 192.168.10.254). NOT executed automatically — this
-- file is a deliverable to review and run yourself (or ask Claude to run it)
-- since it changes the live server's schema.
--
-- Values taken from the legacy VB.NET app's Place of Delivery combo box
-- (PO_Choices.vb / Add_Purchase_Order.vb). Shaped like ref_working_days/
-- ref_payment_term: ID, a single value column, isDeleted for soft-delete.

CREATE TABLE IF NOT EXISTS `ref_place_of_delivery` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Value` VARCHAR(100) NOT NULL,
  `isDeleted` INT(10) DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ref_place_of_delivery` (`Value`) VALUES
  ('CGSO WAREHOUSE'), ('JOB SITE'), ('PICK UP');
