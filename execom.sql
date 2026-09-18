/*
SQLyog Ultimate v12.09 (64 bit)
MySQL - 10.4.32-MariaDB : Database - execom_db
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`execom_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `execom_db`;

/*Table structure for table `audit_logs` */

DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `audit_logs` */

insert  into `audit_logs`(`id`,`user_id`,`username`,`action`,`module`,`details`,`ip_address`,`created_at`) values (1,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:23:28'),(2,1,'admin','LOGOUT','auth','User logged out','::1','2026-09-16 08:23:49'),(3,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:32:53'),(9,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:36:31'),(24,1,'admin','CREATE_USER','settings','Created account \"badmin1\" (branch_admin)','::1','2026-09-16 08:39:03'),(26,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:50:50'),(43,1,'admin','CREATE_USER','settings','Created account \"bu2\" (branch_user)','::1','2026-09-16 08:52:59'),(46,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:53:59'),(47,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:17:54'),(51,6,'bu_mlb','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:20:58'),(52,7,'ba_mrm','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:20:58'),(59,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:38:00'),(72,8,'bu_mlb2','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:50:06'),(73,9,'ba_mrm2','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:50:07'),(74,1,'admin','CREATE_SUPPLIER','suppliers','Created supplier \"Supplier 1\"','::1','2026-09-16 09:54:34'),(75,1,'admin','UPDATE_SUPPLIER','suppliers','Updated supplier \"Supplier Sample 1\"','::1','2026-09-16 09:54:56'),(76,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:59:13'),(81,10,'ba_mlb_cpr','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:59:50'),(82,11,'ba_mrm_cpr','LOGIN','auth','User logged in from ::1','::1','2026-09-16 09:59:50'),(83,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:13:15'),(86,12,'ba_mrm_p6','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:15:08'),(89,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:47:25'),(90,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:52:53'),(91,13,'mlb.admin','CREATE_PR','purchase_requisition','Created MLB-PR-2026-0001 and submitted','::1','2026-09-16 10:52:53'),(92,13,'mlb.admin','APPROVE_PR','purchase_requisition','approved MLB-PR-2026-0001: Approved, proceed to canvass','::1','2026-09-16 10:53:02'),(93,13,'mlb.admin','CREATE_INTERNAL_RFQ','internal_rfq','Created MLB-IRFQ-2026-0001 from MLB-PR-2026-0001','::1','2026-09-16 10:53:02'),(94,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 10:53:20'),(95,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 10:53:20'),(96,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 10:53:20'),(97,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 10:53:20'),(98,13,'mlb.admin','CONVERT_RFQ_TO_PO','internal_rfq','MLB-IRFQ-2026-0001 -> MLB-POI-2026-0001, MLB-POI-2026-0002','::1','2026-09-16 10:53:35'),(99,13,'mlb.admin','SEND_PO_INTERNAL','po_internal','Sent MLB-POI-2026-0001 to V INC.','::1','2026-09-16 10:53:47'),(100,13,'mlb.admin','SEND_PO_INTERNAL','po_internal','Sent MLB-POI-2026-0002 to Alpha Distributors','::1','2026-09-16 10:53:47'),(101,13,'mlb.admin','CREATE_RR','receiving_reports','Posted MLB-RR-2026-0001 against MLB-POI-2026-0001','::1','2026-09-16 10:53:47'),(102,13,'mlb.admin','CREATE_RR','receiving_reports','Posted MLB-RR-2026-0002 against MLB-POI-2026-0002','::1','2026-09-16 10:53:47'),(103,15,'mrm.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:53:59'),(104,15,'mrm.admin','CREATE_PR','purchase_requisition','Created MRM-PR-2026-0001 and submitted','::1','2026-09-16 10:53:59'),(105,15,'mrm.admin','APPROVE_PR','purchase_requisition','approved MRM-PR-2026-0001','::1','2026-09-16 10:53:59'),(106,15,'mrm.admin','CREATE_INTERNAL_RFQ','internal_rfq','Created MRM-IRFQ-2026-0001 from MRM-PR-2026-0001','::1','2026-09-16 10:53:59'),(107,15,'mrm.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MRM-IRFQ-2026-0001','::1','2026-09-16 10:54:38'),(108,15,'mrm.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MRM-IRFQ-2026-0001','::1','2026-09-16 10:54:38'),(109,15,'mrm.admin','CONVERT_RFQ_TO_PO','internal_rfq','MRM-IRFQ-2026-0001 -> MRM-POI-2026-0001','::1','2026-09-16 10:54:49'),(110,15,'mrm.admin','SEND_PO_INTERNAL','po_internal','Sent MRM-POI-2026-0001 to TechSource Philippines','::1','2026-09-16 10:54:49'),(111,15,'mrm.admin','CREATE_RR','receiving_reports','Posted MRM-RR-2026-0001 against MRM-POI-2026-0001','::1','2026-09-16 10:54:59'),(112,13,'mlb.admin','CREATE_STOCK_TRANSFER','stock_transfer','Requested MLB-ST-2026-0001','::1','2026-09-16 10:55:16'),(113,13,'mlb.admin','SHIP_STOCK_TRANSFER','stock_transfer','Shipped MLB-ST-2026-0001','::1','2026-09-16 10:55:16'),(114,16,'val.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:55:16'),(115,16,'val.admin','RECEIVE_STOCK_TRANSFER','stock_transfer','Received MLB-ST-2026-0001','::1','2026-09-16 10:55:16'),(116,14,'mlb.staff','LOGIN','auth','User logged in from ::1','::1','2026-09-16 10:55:26'),(117,14,'mlb.staff','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0001 for 9,000.00','::1','2026-09-16 10:55:26'),(118,16,'val.admin','CREATE_ISSUANCE','issuance','Posted VAL-ISS-2026-0001 for 102,000.00','::1','2026-09-16 10:55:26'),(119,13,'mlb.admin','CREATE_OUTGOING_RFQ','outgoing_rfq','Logged MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office','::1','2026-09-16 10:55:34'),(120,13,'mlb.admin','CREATE_OUTGOING_QUOTATION','outgoing_quotations','Created MLB-OQ-2026-0001 for LGU Malaybalay - General Services Office','::1','2026-09-16 10:55:42'),(121,13,'mlb.admin','CONVERT_QUOTATION_TO_PO','outgoing_quotations','MLB-OQ-2026-0001 -> MLB-POO-2026-0001','::1','2026-09-16 10:55:51'),(122,13,'mlb.admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0002 for 118,500.00','::1','2026-09-16 10:56:05'),(123,13,'mlb.admin','CREATE_DR','delivery_reports','Posted MLB-DR-2026-0001 against MLB-POO-2026-0001','::1','2026-09-16 10:56:05'),(124,15,'mrm.admin','CREATE_OUTGOING_RFQ','outgoing_rfq','Logged MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office','::1','2026-09-16 10:56:16'),(125,15,'mrm.admin','CREATE_OUTGOING_QUOTATION','outgoing_quotations','Created MRM-OQ-2026-0001 for Bukidnon State University - Supply Office','::1','2026-09-16 10:56:16'),(126,15,'mrm.admin','CONVERT_QUOTATION_TO_PO','outgoing_quotations','MRM-OQ-2026-0001 -> MRM-POO-2026-0001','::1','2026-09-16 10:56:17'),(127,16,'val.admin','CREATE_PR','purchase_requisition','Created VAL-PR-2026-0001 and submitted','::1','2026-09-16 10:57:52'),(128,14,'mlb.staff','CREATE_STOCK_TRANSFER','stock_transfer','Requested MLB-ST-2026-0002','::1','2026-09-16 10:57:52'),(129,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 12:54:24'),(130,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 12:55:20'),(131,1,'admin','LOGOUT','auth','User logged out','::1','2026-09-16 13:00:37'),(132,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 13:00:56'),(133,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 13:08:39'),(134,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 13:11:20'),(135,13,'mlb.admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0003 for 108,600.00','::1','2026-09-16 13:11:31'),(136,13,'mlb.admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0003','::1','2026-09-16 13:11:47'),(137,1,'admin','CREATE_ISSUANCE','issuance','Posted MRM-ISS-2026-0001 for 39,900.00','::1','2026-09-16 13:14:43'),(138,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 13:18:27'),(139,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0004 for 3,600.00','::1','2026-09-16 16:36:33'),(140,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 16:41:34'),(141,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 16:49:25'),(142,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 07:50:31'),(143,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 08:14:45'),(144,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 10:44:33'),(145,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0005 for 1,800.00','::1','2026-09-17 10:44:33'),(146,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0005','::1','2026-09-17 10:44:47'),(147,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 10:50:06'),(148,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0006 for 1,800.00','::1','2026-09-17 10:50:06'),(149,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0007 for 1,800.00','::1','2026-09-17 10:50:07'),(150,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0006','::1','2026-09-17 10:50:25'),(151,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0007','::1','2026-09-17 10:50:25'),(152,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0008 for 3,600.00','::1','2026-09-17 10:51:19'),(153,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 10:55:25'),(154,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 15:45:12'),(155,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-18 07:41:00'),(156,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-18 15:09:12'),(157,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-18 15:09:44'),(163,1,'admin','CREATE_ITEM','items','Created item \"Monitor 27inch\"','::1','2026-09-18 15:29:48');

/*Table structure for table `branches` */

DROP TABLE IF EXISTS `branches`;

CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `branches` */

insert  into `branches`(`id`,`code`,`name`,`address`,`contact_no`,`is_active`,`created_at`) values (1,'MLB','Malaybalay City',NULL,NULL,1,'2026-09-16 08:21:51'),(2,'MRM','Maramag City',NULL,NULL,1,'2026-09-16 08:21:51'),(3,'DVO','Davao City',NULL,NULL,1,'2026-09-16 08:21:51'),(4,'CDO','Cagayan De Oro City',NULL,NULL,1,'2026-09-16 08:21:51'),(5,'VAL','Valencia City',NULL,NULL,1,'2026-09-16 08:21:51');

/*Table structure for table `clients` */

DROP TABLE IF EXISTS `clients`;

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `client_type` enum('business','walkin') NOT NULL DEFAULT 'business',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `clients` */

insert  into `clients`(`id`,`name`,`address`,`contact_person`,`contact_number`,`client_type`,`is_active`,`created_at`) values (6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','Engr. Paolo Ranin','09051234567','business',1,'2026-09-16 10:51:49'),(7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','Ms. Elena Cordova','09179988776','business',1,'2026-09-16 10:51:49'),(8,'LGU Valencia - Engineering Office','Poblacion, Valencia City, Bukidnon','Engr. Danilo Ang','09201122334','business',1,'2026-09-16 10:51:49'),(9,'Maria Santos','456 Mabini St.',NULL,'0917-000-0001','walkin',0,'2026-09-17 10:50:06'),(10,'PIODOS',NULL,NULL,NULL,'walkin',1,'2026-09-17 10:51:19');

/*Table structure for table `delivery_report_items` */

DROP TABLE IF EXISTS `delivery_report_items`;

CREATE TABLE `delivery_report_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dr_number` varchar(30) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_dri_dr` (`dr_number`),
  CONSTRAINT `delivery_report_items_ibfk_1` FOREIGN KEY (`dr_number`) REFERENCES `delivery_reports` (`dr_number`),
  CONSTRAINT `delivery_report_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `delivery_report_items` */

insert  into `delivery_report_items`(`id`,`dr_number`,`item_id`,`quantity`,`is_deleted`) values (3,'MLB-DR-2026-0001',13,'1.00',0),(4,'MLB-DR-2026-0001',17,'5.00',0);

/*Table structure for table `delivery_reports` */

DROP TABLE IF EXISTS `delivery_reports`;

CREATE TABLE `delivery_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dr_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `po_number` varchar(30) DEFAULT NULL,
  `delivered_by` int(11) NOT NULL,
  `forwarder` varchar(150) DEFAULT NULL,
  `date_delivered` date DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `date_accepted` date DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dr_number` (`dr_number`),
  KEY `delivered_by` (`delivered_by`),
  KEY `idx_dr_branch` (`branch_id`),
  KEY `idx_dr_po` (`po_number`),
  CONSTRAINT `delivery_reports_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `delivery_reports_ibfk_2` FOREIGN KEY (`po_number`) REFERENCES `po_outgoing` (`po_number`),
  CONSTRAINT `delivery_reports_ibfk_3` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `delivery_reports` */

insert  into `delivery_reports`(`id`,`dr_number`,`branch_id`,`po_number`,`delivered_by`,`forwarder`,`date_delivered`,`received_by`,`date_accepted`,`is_deleted`,`created_at`) values (3,'MLB-DR-2026-0001',1,'MLB-POO-2026-0001',13,'EXECOM Delivery Van','2026-09-28','Engr. Paolo Ranin','2026-09-28',0,'2026-09-16 10:56:05');

/*Table structure for table `doc_sequences` */

DROP TABLE IF EXISTS `doc_sequences`;

CREATE TABLE `doc_sequences` (
  `branch_id` int(11) NOT NULL,
  `doc_type` varchar(20) NOT NULL,
  `year` smallint(6) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`branch_id`,`doc_type`,`year`),
  CONSTRAINT `doc_sequences_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `doc_sequences` */

insert  into `doc_sequences`(`branch_id`,`doc_type`,`year`,`last_number`) values (1,'DR',2026,1),(1,'IRFQ',2026,1),(1,'ISS',2026,8),(1,'OQ',2026,1),(1,'ORFQ',2026,1),(1,'POI',2026,2),(1,'POO',2026,1),(1,'PR',2026,1),(1,'RR',2026,2),(1,'ST',2026,2),(2,'IRFQ',2026,1),(2,'ISS',2026,1),(2,'OQ',2026,1),(2,'ORFQ',2026,1),(2,'POI',2026,1),(2,'POO',2026,1),(2,'PR',2026,1),(2,'RR',2026,1),(5,'ISS',2026,1),(5,'PR',2026,1);

/*Table structure for table `internal_rfq` */

DROP TABLE IF EXISTS `internal_rfq`;

CREATE TABLE `internal_rfq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `pr_number` varchar(30) DEFAULT NULL,
  `sent_date` date DEFAULT NULL,
  `status` enum('open','converted','cancelled') NOT NULL DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfq_number` (`rfq_number`),
  KEY `pr_number` (`pr_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_irfq_branch` (`branch_id`),
  CONSTRAINT `internal_rfq_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `internal_rfq_ibfk_2` FOREIGN KEY (`pr_number`) REFERENCES `pr_requests` (`pr_number`),
  CONSTRAINT `internal_rfq_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `internal_rfq` */

insert  into `internal_rfq`(`id`,`rfq_number`,`branch_id`,`pr_number`,`sent_date`,`status`,`created_by`,`is_deleted`,`created_at`) values (2,'MLB-IRFQ-2026-0001',1,'MLB-PR-2026-0001','2026-09-16','converted',13,0,'2026-09-16 10:53:02'),(3,'MRM-IRFQ-2026-0001',2,'MRM-PR-2026-0001','2026-09-16','converted',15,0,'2026-09-16 10:53:59');

/*Table structure for table `internal_rfq_items` */

DROP TABLE IF EXISTS `internal_rfq_items`;

CREATE TABLE `internal_rfq_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_irfqi_rfq` (`rfq_number`),
  CONSTRAINT `internal_rfq_items_ibfk_1` FOREIGN KEY (`rfq_number`) REFERENCES `internal_rfq` (`rfq_number`),
  CONSTRAINT `internal_rfq_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `internal_rfq_items` */

insert  into `internal_rfq_items`(`id`,`rfq_number`,`item_id`,`description`,`quantity`,`unit`,`is_deleted`) values (3,'MLB-IRFQ-2026-0001',13,'VICTUS Clamshell 15\"','3.00','unit',0),(4,'MLB-IRFQ-2026-0001',17,'8GB DDR4 RAM','20.00','pc',0),(5,'MRM-IRFQ-2026-0001',19,'EcoTank L3210 Printer','4.00','unit',0),(6,'MRM-IRFQ-2026-0001',20,'4-Channel CCTV Kit','2.00','set',0);

/*Table structure for table `internal_rfq_quotes` */

DROP TABLE IF EXISTS `internal_rfq_quotes`;

CREATE TABLE `internal_rfq_quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `rfq_item_id` int(11) NOT NULL,
  `quoted_price` decimal(15,2) DEFAULT NULL,
  `lead_time_days` int(11) DEFAULT NULL,
  `is_selected` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `rfq_item_id` (`rfq_item_id`),
  KEY `idx_irfqq_rfq` (`rfq_number`),
  CONSTRAINT `internal_rfq_quotes_ibfk_1` FOREIGN KEY (`rfq_number`) REFERENCES `internal_rfq` (`rfq_number`),
  CONSTRAINT `internal_rfq_quotes_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `internal_rfq_quotes_ibfk_3` FOREIGN KEY (`rfq_item_id`) REFERENCES `internal_rfq_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `internal_rfq_quotes` */

insert  into `internal_rfq_quotes`(`id`,`rfq_number`,`supplier_id`,`rfq_item_id`,`quoted_price`,`lead_time_days`,`is_selected`,`created_at`) values (4,'MLB-IRFQ-2026-0001',7,3,'92000.00',10,1,'2026-09-16 10:53:20'),(5,'MLB-IRFQ-2026-0001',8,3,'95000.00',7,0,'2026-09-16 10:53:20'),(6,'MLB-IRFQ-2026-0001',8,4,'1150.00',5,1,'2026-09-16 10:53:20'),(7,'MLB-IRFQ-2026-0001',9,4,'1250.00',3,0,'2026-09-16 10:53:20'),(8,'MRM-IRFQ-2026-0001',9,5,'8300.00',7,1,'2026-09-16 10:54:38'),(9,'MRM-IRFQ-2026-0001',9,6,'14200.00',10,1,'2026-09-16 10:54:38');

/*Table structure for table `inventory_stock` */

DROP TABLE IF EXISTS `inventory_stock`;

CREATE TABLE `inventory_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `quantity_on_hand` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_branch` (`item_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `inventory_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `inventory_stock_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `inventory_stock` */

insert  into `inventory_stock`(`id`,`item_id`,`branch_id`,`quantity_on_hand`,`reorder_level`) values (9,17,1,'6.00','0.00');

/*Table structure for table `inventory_unassigned_units` */

DROP TABLE IF EXISTS `inventory_unassigned_units`;

CREATE TABLE `inventory_unassigned_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `date_received` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `scanned_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `scanned_by` (`scanned_by`),
  KEY `idx_invunassigned_branch` (`branch_id`),
  KEY `idx_invunassigned_serial` (`serial_number`),
  CONSTRAINT `inventory_unassigned_units_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `inventory_unassigned_units_ibfk_2` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `inventory_unassigned_units` */

/*Table structure for table `inventory_units` */

DROP TABLE IF EXISTS `inventory_units`;

CREATE TABLE `inventory_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `status` enum('in_stock','reserved','issued','in_transit','returned','defective') NOT NULL DEFAULT 'in_stock',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `source_rr_number` varchar(30) DEFAULT NULL,
  `reserved_for_client_id` int(11) DEFAULT NULL,
  `reserved_for_po_number` varchar(30) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `date_out` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `source_rr_number` (`source_rr_number`),
  KEY `reserved_for_client_id` (`reserved_for_client_id`),
  KEY `idx_invu_branch` (`branch_id`),
  KEY `idx_invu_item` (`item_id`),
  KEY `idx_invu_serial` (`serial_number`),
  KEY `idx_invu_status` (`status`),
  CONSTRAINT `inventory_units_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `inventory_units_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `inventory_units_ibfk_3` FOREIGN KEY (`source_rr_number`) REFERENCES `receiving_reports` (`rr_number`),
  CONSTRAINT `inventory_units_ibfk_4` FOREIGN KEY (`reserved_for_client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `inventory_units` */

insert  into `inventory_units`(`id`,`item_id`,`branch_id`,`serial_number`,`status`,`unit_cost`,`source_rr_number`,`reserved_for_client_id`,`reserved_for_po_number`,`date_received`,`date_out`,`location`,`remarks`,`is_deleted`,`created_at`) values (7,13,1,'HP-VC15-0001','issued','92000.00','MLB-RR-2026-0001',NULL,NULL,'2026-09-20','2026-09-16',NULL,NULL,0,'2026-09-16 10:53:47'),(8,13,1,'HP-VC15-0002','in_stock','92000.00','MLB-RR-2026-0001',NULL,NULL,'2026-09-20',NULL,NULL,NULL,0,'2026-09-16 10:53:47'),(9,13,5,'HP-VC15-0003','issued','92000.00','MLB-RR-2026-0001',NULL,NULL,'2026-09-20','2026-09-16',NULL,NULL,0,'2026-09-16 10:53:47'),(10,19,2,'EP-L3210-0001','issued','8300.00','MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,0,'2026-09-16 10:54:59'),(11,19,2,'EP-L3210-0002','issued','8300.00','MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,0,'2026-09-16 10:54:59'),(12,20,2,'HIK-4CH-0001','issued','14200.00','MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,0,'2026-09-16 10:54:59');

/*Table structure for table `issuance_items` */

DROP TABLE IF EXISTS `issuance_items`;

CREATE TABLE `issuance_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issuance_number` varchar(30) NOT NULL,
  `inventory_unit_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` decimal(11,2) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `inventory_unit_id` (`inventory_unit_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_issi_issuance` (`issuance_number`),
  CONSTRAINT `issuance_items_ibfk_1` FOREIGN KEY (`issuance_number`) REFERENCES `issuances` (`issuance_number`),
  CONSTRAINT `issuance_items_ibfk_2` FOREIGN KEY (`inventory_unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `issuance_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `issuance_items` */

insert  into `issuance_items`(`id`,`issuance_number`,`inventory_unit_id`,`item_id`,`quantity`,`unit_price`,`total_price`,`is_deleted`) values (12,'MLB-ISS-2026-0001',NULL,17,'5.00','1800.00','9000.00',0),(13,'VAL-ISS-2026-0001',9,NULL,'1.00','102000.00','102000.00',0),(14,'MLB-ISS-2026-0002',7,NULL,'1.00','108000.00','108000.00',0),(15,'MLB-ISS-2026-0002',NULL,17,'5.00','2100.00','10500.00',0),(16,'MLB-ISS-2026-0003',8,NULL,'1.00','105000.00','105000.00',0),(17,'MLB-ISS-2026-0003',NULL,17,'2.00','1800.00','3600.00',0),(18,'MRM-ISS-2026-0001',12,NULL,'1.00','18900.00','18900.00',0),(19,'MRM-ISS-2026-0001',10,NULL,'1.00','10500.00','10500.00',0),(20,'MRM-ISS-2026-0001',11,NULL,'1.00','10500.00','10500.00',0),(21,'MLB-ISS-2026-0004',NULL,17,'2.00','1800.00','3600.00',0),(22,'MLB-ISS-2026-0005',NULL,17,'1.00','1800.00','1800.00',0),(23,'MLB-ISS-2026-0006',NULL,17,'1.00','1800.00','1800.00',0),(24,'MLB-ISS-2026-0007',NULL,17,'1.00','1800.00','1800.00',0),(25,'MLB-ISS-2026-0008',NULL,17,'2.00','1800.00','3600.00',0);

/*Table structure for table `issuances` */

DROP TABLE IF EXISTS `issuances`;

CREATE TABLE `issuances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issuance_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `walkin_name` varchar(150) DEFAULT NULL,
  `walkin_address` varchar(255) DEFAULT NULL,
  `walkin_contact` varchar(50) DEFAULT NULL,
  `po_number` varchar(30) DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `date_created` datetime DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `issuance_number` (`issuance_number`),
  KEY `client_id` (`client_id`),
  KEY `po_number` (`po_number`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_iss_branch` (`branch_id`),
  CONSTRAINT `issuances_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `issuances_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `issuances_ibfk_3` FOREIGN KEY (`po_number`) REFERENCES `po_outgoing` (`po_number`),
  CONSTRAINT `issuances_ibfk_4` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `issuances` */

insert  into `issuances`(`id`,`issuance_number`,`branch_id`,`client_id`,`walkin_name`,`walkin_address`,`walkin_contact`,`po_number`,`issued_by`,`date_created`,`total_amount`,`is_deleted`,`created_at`) values (11,'MLB-ISS-2026-0001',1,NULL,NULL,NULL,NULL,NULL,14,'2026-09-16 10:55:26','9000.00',0,'2026-09-16 10:55:26'),(12,'VAL-ISS-2026-0001',5,NULL,NULL,NULL,NULL,NULL,16,'2026-09-16 10:55:26','102000.00',0,'2026-09-16 10:55:26'),(13,'MLB-ISS-2026-0002',1,6,NULL,NULL,NULL,'MLB-POO-2026-0001',13,'2026-09-16 10:56:05','118500.00',0,'2026-09-16 10:56:05'),(14,'MLB-ISS-2026-0003',1,NULL,NULL,NULL,NULL,NULL,13,'2026-09-16 13:11:31','108600.00',1,'2026-09-16 13:11:31'),(15,'MRM-ISS-2026-0001',2,7,NULL,NULL,NULL,NULL,1,'2026-09-16 13:14:43','39900.00',0,'2026-09-16 13:14:43'),(16,'MLB-ISS-2026-0004',1,NULL,NULL,NULL,NULL,NULL,1,'2026-09-16 16:36:33','3600.00',0,'2026-09-16 16:36:33'),(17,'MLB-ISS-2026-0005',1,NULL,'Juan Dela Cruz','123 Sampaguita St.','0917-555-1234',NULL,1,'2026-09-17 10:44:33','1800.00',1,'2026-09-17 10:44:33'),(18,'MLB-ISS-2026-0006',1,9,'Maria Santos','456 Mabini St.','0917-000-0001',NULL,1,'2026-09-17 10:50:06','1800.00',1,'2026-09-17 10:50:06'),(19,'MLB-ISS-2026-0007',1,9,'Maria Santos',NULL,NULL,NULL,1,'2026-09-17 10:50:07','1800.00',1,'2026-09-17 10:50:07'),(20,'MLB-ISS-2026-0008',1,10,'PIODOS',NULL,NULL,NULL,1,'2026-09-17 10:51:19','3600.00',0,'2026-09-17 10:51:19');

/*Table structure for table `item_categories` */

DROP TABLE IF EXISTS `item_categories`;

CREATE TABLE `item_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_serialized` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `item_categories` */

insert  into `item_categories`(`id`,`name`,`is_serialized`,`is_active`) values (1,'Monitor',1,1),(2,'Laptop / All-in-One',1,1),(3,'Processor / Motherboard',1,1),(4,'SSD / HDD / External Storage',1,1),(5,'RAM',0,1),(6,'Printer',1,1),(7,'CCTV',1,1),(8,'Projector / Scanner / Webcam',1,1),(9,'Others',0,1);

/*Table structure for table `items` */

DROP TABLE IF EXISTS `items`;

CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'unit',
  `is_serialized` tinyint(1) NOT NULL DEFAULT 1,
  `default_cost` decimal(15,2) DEFAULT NULL,
  `default_price` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_items_category` (`category_id`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `items` */

insert  into `items`(`id`,`sku`,`name`,`category_id`,`brand`,`unit`,`is_serialized`,`default_cost`,`default_price`,`is_active`,`created_at`) values (13,'LAP-VICTUS-15','VICTUS Clamshell 15\"',2,'HP','unit',1,'90000.00','100000.00',1,'2026-09-16 10:52:28'),(14,'LAP-ASPIRE-5','Aspire 5 Laptop',2,'Acer','unit',1,'32000.00','39900.00',1,'2026-09-16 10:52:28'),(15,'MON-24-LED','Samsung 24\" LED Monitor',1,'Samsung','unit',1,'6200.00','7800.00',1,'2026-09-16 10:52:28'),(16,'SSD-480GB','480GB SATA SSD',4,'Kingston','unit',1,'2200.00','2900.00',1,'2026-09-16 10:52:28'),(17,'RAM-8GB-DDR4','8GB DDR4 RAM',5,'Kingston','pc',0,'1200.00','1800.00',1,'2026-09-16 10:52:28'),(18,'RAM-16GB-DDR4','16GB DDR4 RAM',5,'Kingston','pc',0,'2300.00','3100.00',1,'2026-09-16 10:52:28'),(19,'PRN-L3210','EcoTank L3210 Printer',6,'Epson','unit',1,'8500.00','10500.00',1,'2026-09-16 10:52:28'),(20,'CCTV-4CH-KIT','4-Channel CCTV Kit',7,'Hikvision','set',1,'14500.00','18900.00',1,'2026-09-16 10:52:28'),(21,'CBL-HDMI-2M','HDMI Cable 2m',9,'Generic','pc',0,'120.00','250.00',1,'2026-09-16 10:52:28'),(22,'USB-32GB','USB Flash Drive 32GB',9,'SanDisk','pc',0,'280.00','450.00',1,'2026-09-16 10:52:28'),(23,'MonitorGambias','Monitor 27inch',1,'Gambias','unit',1,'10000.00','9000.00',1,'2026-09-18 15:29:48');

/*Table structure for table `notification_reads` */

DROP TABLE IF EXISTS `notification_reads`;

CREATE TABLE `notification_reads` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `notification_reads` */

insert  into `notification_reads`(`notification_id`,`user_id`,`read_at`) values (14,1,'2026-09-17 07:50:39'),(16,1,'2026-09-17 07:50:39'),(18,1,'2026-09-17 07:50:39'),(20,1,'2026-09-17 07:50:39'),(22,1,'2026-09-17 07:50:39'),(24,1,'2026-09-17 07:50:39'),(26,1,'2026-09-17 07:50:39'),(28,1,'2026-09-17 07:50:39'),(30,1,'2026-09-17 07:50:39'),(32,1,'2026-09-17 07:50:39'),(34,1,'2026-09-17 07:50:39'),(36,1,'2026-09-17 07:50:39'),(38,1,'2026-09-17 07:50:39'),(40,1,'2026-09-17 07:50:39'),(42,1,'2026-09-17 07:50:39');

/*Table structure for table `notifications` */

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `notifications` */

insert  into `notifications`(`id`,`role`,`title`,`message`,`link`,`created_at`) values (13,'branch_admin','Requisition approved','MLB-PR-2026-0001 was approved by Rosemarie Tan','purchase_requisitions.php','2026-09-16 10:53:02'),(14,'super_admin','Requisition approved','MLB-PR-2026-0001 was approved by Rosemarie Tan','purchase_requisitions.php','2026-09-16 10:53:02'),(15,'branch_admin','Internal PO created','MLB-IRFQ-2026-0001 converted to MLB-POI-2026-0001, MLB-POI-2026-0002','po_internal.php','2026-09-16 10:53:35'),(16,'super_admin','Internal PO created','MLB-IRFQ-2026-0001 converted to MLB-POI-2026-0001, MLB-POI-2026-0002','po_internal.php','2026-09-16 10:53:35'),(17,'branch_admin','Purchase Order fully received','MLB-POI-2026-0001 from V INC. is complete','po_internal.php','2026-09-16 10:53:47'),(18,'super_admin','Purchase Order fully received','MLB-POI-2026-0001 from V INC. is complete','po_internal.php','2026-09-16 10:53:47'),(19,'branch_admin','Purchase Order fully received','MLB-POI-2026-0002 from Alpha Distributors is complete','po_internal.php','2026-09-16 10:53:47'),(20,'super_admin','Purchase Order fully received','MLB-POI-2026-0002 from Alpha Distributors is complete','po_internal.php','2026-09-16 10:53:47'),(21,'branch_admin','Requisition approved','MRM-PR-2026-0001 was approved by Kevin Salvador','purchase_requisitions.php','2026-09-16 10:53:59'),(22,'super_admin','Requisition approved','MRM-PR-2026-0001 was approved by Kevin Salvador','purchase_requisitions.php','2026-09-16 10:53:59'),(23,'branch_admin','Internal PO created','MRM-IRFQ-2026-0001 converted to MRM-POI-2026-0001','po_internal.php','2026-09-16 10:54:49'),(24,'super_admin','Internal PO created','MRM-IRFQ-2026-0001 converted to MRM-POI-2026-0001','po_internal.php','2026-09-16 10:54:49'),(25,'branch_admin','Stock transfer requested','MLB-ST-2026-0001 is awaiting shipment','stock_transfers.php','2026-09-16 10:55:16'),(26,'super_admin','Stock transfer requested','MLB-ST-2026-0001 is awaiting shipment','stock_transfers.php','2026-09-16 10:55:16'),(27,'branch_admin','Stock transfer in transit','MLB-ST-2026-0001 is on its way to Valencia City','stock_transfers.php','2026-09-16 10:55:16'),(28,'super_admin','Stock transfer in transit','MLB-ST-2026-0001 is on its way to Valencia City','stock_transfers.php','2026-09-16 10:55:16'),(29,'branch_admin','Stock transfer received','MLB-ST-2026-0001 has arrived at Valencia City','stock_transfers.php','2026-09-16 10:55:16'),(30,'super_admin','Stock transfer received','MLB-ST-2026-0001 has arrived at Valencia City','stock_transfers.php','2026-09-16 10:55:16'),(31,'branch_admin','Canvass received','MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office needs a quotation','outgoing_rfq.php','2026-09-16 10:55:34'),(32,'super_admin','Canvass received','MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office needs a quotation','outgoing_rfq.php','2026-09-16 10:55:34'),(33,'branch_admin','Client order awarded','MLB-OQ-2026-0001 converted to MLB-POO-2026-0001 for LGU Malaybalay - General Services Office','po_outgoing.php','2026-09-16 10:55:51'),(34,'super_admin','Client order awarded','MLB-OQ-2026-0001 converted to MLB-POO-2026-0001 for LGU Malaybalay - General Services Office','po_outgoing.php','2026-09-16 10:55:51'),(35,'branch_admin','Order completed','MLB-POO-2026-0001 signed for by Engr. Paolo Ranin and closed','po_outgoing.php','2026-09-16 10:56:05'),(36,'super_admin','Order completed','MLB-POO-2026-0001 signed for by Engr. Paolo Ranin and closed','po_outgoing.php','2026-09-16 10:56:05'),(37,'branch_admin','Canvass received','MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office needs a quotation','outgoing_rfq.php','2026-09-16 10:56:16'),(38,'super_admin','Canvass received','MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office needs a quotation','outgoing_rfq.php','2026-09-16 10:56:16'),(39,'branch_admin','Client order awarded','MRM-OQ-2026-0001 converted to MRM-POO-2026-0001 for Bukidnon State University - Supply Office','po_outgoing.php','2026-09-16 10:56:17'),(40,'super_admin','Client order awarded','MRM-OQ-2026-0001 converted to MRM-POO-2026-0001 for Bukidnon State University - Supply Office','po_outgoing.php','2026-09-16 10:56:17'),(41,'branch_admin','Stock transfer requested','MLB-ST-2026-0002 is awaiting shipment','stock_transfers.php','2026-09-16 10:57:52'),(42,'super_admin','Stock transfer requested','MLB-ST-2026-0002 is awaiting shipment','stock_transfers.php','2026-09-16 10:57:52');

/*Table structure for table `outgoing_quotation_items` */

DROP TABLE IF EXISTS `outgoing_quotation_items`;

CREATE TABLE `outgoing_quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `total_price` decimal(15,2) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_oqi_quotation` (`quotation_number`),
  CONSTRAINT `outgoing_quotation_items_ibfk_1` FOREIGN KEY (`quotation_number`) REFERENCES `outgoing_quotations` (`quotation_number`),
  CONSTRAINT `outgoing_quotation_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `outgoing_quotation_items` */

insert  into `outgoing_quotation_items`(`id`,`quotation_number`,`item_id`,`description`,`quantity`,`unit`,`unit_price`,`total_price`,`is_deleted`) values (5,'MLB-OQ-2026-0001',13,'VICTUS Clamshell 15\"','1.00','unit','108000.00','108000.00',0),(6,'MLB-OQ-2026-0001',17,'8GB DDR4 RAM','5.00','pc','2100.00','10500.00',0),(7,'MRM-OQ-2026-0001',20,'4-Channel CCTV Kit','1.00','set','18900.00','18900.00',0),(8,'MRM-OQ-2026-0001',19,'EcoTank L3210 Printer','2.00','unit','11200.00','22400.00',0);

/*Table structure for table `outgoing_quotations` */

DROP TABLE IF EXISTS `outgoing_quotations`;

CREATE TABLE `outgoing_quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(30) NOT NULL,
  `rfq_number` varchar(30) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_address` varchar(255) DEFAULT NULL,
  `price_validity_days` int(11) DEFAULT NULL,
  `lead_time` varchar(100) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','converted','cancelled') NOT NULL DEFAULT 'active',
  `prepared_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `rfq_number` (`rfq_number`),
  KEY `prepared_by` (`prepared_by`),
  KEY `idx_oq_branch` (`branch_id`),
  KEY `fk_oq_client` (`client_id`),
  CONSTRAINT `fk_oq_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `outgoing_quotations_ibfk_1` FOREIGN KEY (`rfq_number`) REFERENCES `outgoing_rfq` (`rfq_number`),
  CONSTRAINT `outgoing_quotations_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `outgoing_quotations_ibfk_3` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `outgoing_quotations` */

insert  into `outgoing_quotations`(`id`,`quotation_number`,`rfq_number`,`branch_id`,`client_id`,`client_name`,`client_address`,`price_validity_days`,`lead_time`,`total_amount`,`status`,`prepared_by`,`is_deleted`,`created_at`) values (3,'MLB-OQ-2026-0001','MLB-ORFQ-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon',15,'7 working days','118500.00','converted',13,0,'2026-09-16 10:55:42'),(4,'MRM-OQ-2026-0001','MRM-ORFQ-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon',10,'10 working days','41300.00','converted',15,0,'2026-09-16 10:56:16');

/*Table structure for table `outgoing_rfq` */

DROP TABLE IF EXISTS `outgoing_rfq`;

CREATE TABLE `outgoing_rfq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_address` varchar(255) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','quoted','converted','cancelled') NOT NULL DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfq_number` (`rfq_number`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_orfq_branch` (`branch_id`),
  CONSTRAINT `outgoing_rfq_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `outgoing_rfq_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `outgoing_rfq_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `outgoing_rfq` */

insert  into `outgoing_rfq`(`id`,`rfq_number`,`branch_id`,`client_id`,`client_name`,`client_address`,`received_date`,`due_date`,`status`,`created_by`,`is_deleted`,`created_at`) values (2,'MLB-ORFQ-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','2026-09-18','2026-10-02','converted',13,0,'2026-09-16 10:55:34'),(3,'MRM-ORFQ-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','2026-09-20','2026-10-10','converted',15,0,'2026-09-16 10:56:16');

/*Table structure for table `outgoing_rfq_items` */

DROP TABLE IF EXISTS `outgoing_rfq_items`;

CREATE TABLE `outgoing_rfq_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_orfqi_rfq` (`rfq_number`),
  CONSTRAINT `outgoing_rfq_items_ibfk_1` FOREIGN KEY (`rfq_number`) REFERENCES `outgoing_rfq` (`rfq_number`),
  CONSTRAINT `outgoing_rfq_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `outgoing_rfq_items` */

insert  into `outgoing_rfq_items`(`id`,`rfq_number`,`item_id`,`description`,`quantity`,`unit`,`is_deleted`) values (2,'MLB-ORFQ-2026-0001',13,'VICTUS Clamshell 15\"','1.00','unit',0),(3,'MLB-ORFQ-2026-0001',17,'8GB DDR4 RAM','5.00','pc',0),(4,'MRM-ORFQ-2026-0001',20,'4-Channel CCTV Kit','1.00','set',0),(5,'MRM-ORFQ-2026-0001',19,'EcoTank L3210 Printer','2.00','unit',0);

/*Table structure for table `po_internal` */

DROP TABLE IF EXISTS `po_internal`;

CREATE TABLE `po_internal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_address` varchar(255) DEFAULT NULL,
  `internal_rfq_number` varchar(30) DEFAULT NULL,
  `pr_number` varchar(30) DEFAULT NULL,
  `forwarder` varchar(150) DEFAULT NULL,
  `ship_to_address` varchar(255) DEFAULT NULL,
  `status` enum('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `internal_rfq_number` (`internal_rfq_number`),
  KEY `pr_number` (`pr_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_poi_branch` (`branch_id`),
  KEY `idx_poi_status` (`status`),
  CONSTRAINT `po_internal_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `po_internal_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `po_internal_ibfk_3` FOREIGN KEY (`internal_rfq_number`) REFERENCES `internal_rfq` (`rfq_number`),
  CONSTRAINT `po_internal_ibfk_4` FOREIGN KEY (`pr_number`) REFERENCES `pr_requests` (`pr_number`),
  CONSTRAINT `po_internal_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `po_internal` */

insert  into `po_internal`(`id`,`po_number`,`branch_id`,`supplier_id`,`supplier_name`,`supplier_address`,`internal_rfq_number`,`pr_number`,`forwarder`,`ship_to_address`,`status`,`total_amount`,`created_by`,`is_deleted`,`created_at`) values (4,'MLB-POI-2026-0001',1,7,'V INC.','Quezon City, Metro Manila','MLB-IRFQ-2026-0001','MLB-PR-2026-0001',NULL,NULL,'received','276000.00',13,0,'2026-09-16 10:53:35'),(5,'MLB-POI-2026-0002',1,8,'Alpha Distributors','Cebu City, Cebu','MLB-IRFQ-2026-0001','MLB-PR-2026-0001',NULL,NULL,'received','23000.00',13,0,'2026-09-16 10:53:35'),(6,'MRM-POI-2026-0001',2,9,'TechSource Philippines','Davao City, Davao del Sur','MRM-IRFQ-2026-0001','MRM-PR-2026-0001',NULL,NULL,'partially_received','61600.00',15,0,'2026-09-16 10:54:49');

/*Table structure for table `po_internal_items` */

DROP TABLE IF EXISTS `po_internal_items`;

CREATE TABLE `po_internal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `end_user` varchar(150) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_poii_po` (`po_number`),
  CONSTRAINT `po_internal_items_ibfk_1` FOREIGN KEY (`po_number`) REFERENCES `po_internal` (`po_number`),
  CONSTRAINT `po_internal_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `po_internal_items` */

insert  into `po_internal_items`(`id`,`po_number`,`item_id`,`end_user`,`description`,`quantity`,`unit`,`unit_cost`,`total_cost`,`is_deleted`) values (4,'MLB-POI-2026-0001',13,NULL,'VICTUS Clamshell 15\"','3.00','unit','92000.00','276000.00',0),(5,'MLB-POI-2026-0002',17,NULL,'8GB DDR4 RAM','20.00','pc','1150.00','23000.00',0),(6,'MRM-POI-2026-0001',19,NULL,'EcoTank L3210 Printer','4.00','unit','8300.00','33200.00',0),(7,'MRM-POI-2026-0001',20,NULL,'4-Channel CCTV Kit','2.00','set','14200.00','28400.00',0);

/*Table structure for table `po_outgoing` */

DROP TABLE IF EXISTS `po_outgoing`;

CREATE TABLE `po_outgoing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_address` varchar(255) DEFAULT NULL,
  `outgoing_quotation_number` varchar(30) DEFAULT NULL,
  `end_user` varchar(150) DEFAULT NULL,
  `place_of_delivery` varchar(255) DEFAULT NULL,
  `delivery_term` varchar(100) DEFAULT NULL,
  `payment_term` varchar(100) DEFAULT NULL,
  `status` enum('open','partially_delivered','delivered','completed','cancelled') NOT NULL DEFAULT 'open',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `client_id` (`client_id`),
  KEY `outgoing_quotation_number` (`outgoing_quotation_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_poo_branch` (`branch_id`),
  KEY `idx_poo_status` (`status`),
  CONSTRAINT `po_outgoing_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `po_outgoing_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `po_outgoing_ibfk_3` FOREIGN KEY (`outgoing_quotation_number`) REFERENCES `outgoing_quotations` (`quotation_number`),
  CONSTRAINT `po_outgoing_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `po_outgoing` */

insert  into `po_outgoing`(`id`,`po_number`,`branch_id`,`client_id`,`client_name`,`client_address`,`outgoing_quotation_number`,`end_user`,`place_of_delivery`,`delivery_term`,`payment_term`,`status`,`total_amount`,`created_by`,`is_deleted`,`created_at`) values (6,'MLB-POO-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','MLB-OQ-2026-0001','GSO Malaybalay','Capitol Compound, Malaybalay City','FOB Destination','30 days','completed','118500.00',13,0,'2026-09-16 10:55:51'),(7,'MRM-POO-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','MRM-OQ-2026-0001','BukSU Supply Office','Fortich St, Malaybalay City','FOB Destination','Cash on Delivery','open','41300.00',15,0,'2026-09-16 10:56:17');

/*Table structure for table `po_outgoing_items` */

DROP TABLE IF EXISTS `po_outgoing_items`;

CREATE TABLE `po_outgoing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_pooi_po` (`po_number`),
  CONSTRAINT `po_outgoing_items_ibfk_1` FOREIGN KEY (`po_number`) REFERENCES `po_outgoing` (`po_number`),
  CONSTRAINT `po_outgoing_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `po_outgoing_items` */

insert  into `po_outgoing_items`(`id`,`po_number`,`item_id`,`description`,`quantity`,`unit`,`unit_price`,`total_price`,`is_deleted`) values (8,'MLB-POO-2026-0001',13,'VICTUS Clamshell 15\"','1.00','unit','108000.00','108000.00',0),(9,'MLB-POO-2026-0001',17,'8GB DDR4 RAM','5.00','pc','2100.00','10500.00',0),(10,'MRM-POO-2026-0001',20,'4-Channel CCTV Kit','1.00','set','18900.00','18900.00',0),(11,'MRM-POO-2026-0001',19,'EcoTank L3210 Printer','2.00','unit','11200.00','22400.00',0);

/*Table structure for table `pr_items` */

DROP TABLE IF EXISTS `pr_items`;

CREATE TABLE `pr_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(30) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_pri_pr` (`pr_number`),
  CONSTRAINT `pr_items_ibfk_1` FOREIGN KEY (`pr_number`) REFERENCES `pr_requests` (`pr_number`),
  CONSTRAINT `pr_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `pr_items` */

insert  into `pr_items`(`id`,`pr_number`,`item_id`,`description`,`quantity`,`unit`,`is_deleted`) values (4,'MLB-PR-2026-0001',13,'VICTUS Clamshell 15\"','3.00','unit',0),(5,'MLB-PR-2026-0001',17,'8GB DDR4 RAM','20.00','pc',0),(6,'MRM-PR-2026-0001',19,'EcoTank L3210 Printer','4.00','unit',0),(7,'MRM-PR-2026-0001',20,'4-Channel CCTV Kit','2.00','set',0),(8,'VAL-PR-2026-0001',15,'Samsung 24\" LED Monitor','4.00','unit',0);

/*Table structure for table `pr_requests` */

DROP TABLE IF EXISTS `pr_requests`;

CREATE TABLE `pr_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `needed_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','converted') NOT NULL DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_number` (`pr_number`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_pr_branch` (`branch_id`),
  KEY `idx_pr_status` (`status`),
  CONSTRAINT `pr_requests_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `pr_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `pr_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `pr_requests` */

insert  into `pr_requests`(`id`,`pr_number`,`branch_id`,`requested_by`,`department`,`purpose`,`needed_date`,`status`,`approved_by`,`approved_at`,`remarks`,`is_deleted`,`created_at`) values (3,'MLB-PR-2026-0001',1,13,'Sales','Replenish laptop and RAM stock for Q4','2026-10-01','converted',13,'2026-09-16 10:53:02','Approved, proceed to canvass',0,'2026-09-16 10:52:53'),(4,'MRM-PR-2026-0001',2,15,'Branch Operations','Stock up printers and CCTV kits','2026-10-05','converted',15,'2026-09-16 10:53:59',NULL,0,'2026-09-16 10:53:59'),(5,'VAL-PR-2026-0001',5,16,'Sales','Restock monitors for branch display units','2026-10-15','pending',NULL,NULL,NULL,0,'2026-09-16 10:57:52');

/*Table structure for table `receiving_report_items` */

DROP TABLE IF EXISTS `receiving_report_items`;

CREATE TABLE `receiving_report_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rr_number` varchar(30) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(11,2) NOT NULL DEFAULT 1.00,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `serial_number` varchar(100) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_rri_rr` (`rr_number`),
  CONSTRAINT `receiving_report_items_ibfk_1` FOREIGN KEY (`rr_number`) REFERENCES `receiving_reports` (`rr_number`),
  CONSTRAINT `receiving_report_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `receiving_report_items` */

insert  into `receiving_report_items`(`id`,`rr_number`,`item_id`,`quantity`,`unit_cost`,`serial_number`,`is_deleted`) values (4,'MLB-RR-2026-0001',13,'1.00','92000.00','HP-VC15-0001',0),(5,'MLB-RR-2026-0001',13,'1.00','92000.00','HP-VC15-0002',0),(6,'MLB-RR-2026-0001',13,'1.00','92000.00','HP-VC15-0003',0),(7,'MLB-RR-2026-0002',17,'20.00','1150.00',NULL,0),(8,'MRM-RR-2026-0001',19,'1.00','8300.00','EP-L3210-0001',0),(9,'MRM-RR-2026-0001',19,'1.00','8300.00','EP-L3210-0002',0),(10,'MRM-RR-2026-0001',20,'1.00','14200.00','HIK-4CH-0001',0);

/*Table structure for table `receiving_reports` */

DROP TABLE IF EXISTS `receiving_reports`;

CREATE TABLE `receiving_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rr_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `po_number` varchar(30) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `date_received` date DEFAULT NULL,
  `condition_notes` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rr_number` (`rr_number`),
  KEY `received_by` (`received_by`),
  KEY `idx_rr_branch` (`branch_id`),
  KEY `idx_rr_po` (`po_number`),
  CONSTRAINT `receiving_reports_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `receiving_reports_ibfk_2` FOREIGN KEY (`po_number`) REFERENCES `po_internal` (`po_number`),
  CONSTRAINT `receiving_reports_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `receiving_reports` */

insert  into `receiving_reports`(`id`,`rr_number`,`branch_id`,`po_number`,`received_by`,`date_received`,`condition_notes`,`is_deleted`,`created_at`) values (4,'MLB-RR-2026-0001',1,'MLB-POI-2026-0001',13,'2026-09-20','All units in good condition',0,'2026-09-16 10:53:47'),(5,'MLB-RR-2026-0002',1,'MLB-POI-2026-0002',13,'2026-09-20','Complete delivery',0,'2026-09-16 10:53:47'),(6,'MRM-RR-2026-0001',2,'MRM-POI-2026-0001',15,'2026-09-25','Partial delivery, remainder on backorder',0,'2026-09-16 10:54:59');

/*Table structure for table `role_module_access` */

DROP TABLE IF EXISTS `role_module_access`;

CREATE TABLE `role_module_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(30) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_module` (`role`,`module_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `role_module_access` */

insert  into `role_module_access`(`id`,`role`,`module_key`,`can_access`,`updated_at`) values (1,'branch_user','settings',0,'2026-09-16 08:21:51');

/*Table structure for table `stock_transfer_items` */

DROP TABLE IF EXISTS `stock_transfer_items`;

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(30) NOT NULL,
  `inventory_unit_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` decimal(11,2) DEFAULT NULL,
  `description_snapshot` varchar(255) DEFAULT NULL,
  `serial_snapshot` varchar(100) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `inventory_unit_id` (`inventory_unit_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_sti_transfer` (`transfer_number`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_number`) REFERENCES `stock_transfers` (`transfer_number`),
  CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`inventory_unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `stock_transfer_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `stock_transfer_items` */

insert  into `stock_transfer_items`(`id`,`transfer_number`,`inventory_unit_id`,`item_id`,`quantity`,`description_snapshot`,`serial_snapshot`,`is_deleted`) values (4,'MLB-ST-2026-0001',9,NULL,NULL,'VICTUS Clamshell 15\"','HP-VC15-0003',0),(5,'MLB-ST-2026-0002',NULL,17,'5.00','8GB DDR4 RAM',NULL,0);

/*Table structure for table `stock_transfers` */

DROP TABLE IF EXISTS `stock_transfers`;

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(30) NOT NULL,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','in_transit','received','cancelled') NOT NULL DEFAULT 'pending',
  `date_created` datetime DEFAULT NULL,
  `date_received` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_st_from` (`from_branch_id`),
  KEY `idx_st_to` (`to_branch_id`),
  CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `stock_transfers` */

insert  into `stock_transfers`(`id`,`transfer_number`,`from_branch_id`,`to_branch_id`,`requested_by`,`approved_by`,`status`,`date_created`,`date_received`,`is_deleted`,`created_at`) values (3,'MLB-ST-2026-0001',1,5,13,13,'received','2026-09-16 10:55:16','2026-09-16 10:55:16',0,'2026-09-16 10:55:16'),(4,'MLB-ST-2026-0002',1,4,14,NULL,'pending','2026-09-16 10:57:52',NULL,0,'2026-09-16 10:57:52');

/*Table structure for table `suppliers` */

DROP TABLE IF EXISTS `suppliers`;

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `suppliers` */

insert  into `suppliers`(`id`,`name`,`address`,`contact_person`,`contact_number`,`email`,`payment_terms`,`is_active`,`created_at`) values (7,'V INC.','Quezon City, Metro Manila','Marie Cris S. Nini','09178458198',NULL,'Net 30',1,'2026-09-16 10:51:49'),(8,'Alpha Distributors','Cebu City, Cebu','Ramon Ty','09228841122',NULL,'COD',1,'2026-09-16 10:51:49'),(9,'TechSource Philippines','Davao City, Davao del Sur','Grace Villamor','09173321567',NULL,'Net 15',1,'2026-09-16 10:51:49');

/*Table structure for table `user_module_access` */

DROP TABLE IF EXISTS `user_module_access`;

CREATE TABLE `user_module_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_module` (`user_id`,`module_key`),
  CONSTRAINT `user_module_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `user_module_access` */

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'branch_user',
  `department` varchar(150) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_branch` (`branch_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`password`,`full_name`,`first_name`,`middle_name`,`last_name`,`suffix`,`branch_id`,`role`,`department`,`last_login`,`avatar_path`,`is_active`,`created_at`) values (1,'admin','$2y$10$0w832hH3EFlb4KMRhAW6zOnCB9MVpvgCg8nP54Wkc/.mVdev4LG/u','System Administrator','System',NULL,'Administrator',NULL,NULL,'super_admin',NULL,'2026-09-18 15:09:44',NULL,1,'2026-09-16 08:21:51'),(13,'mlb.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Rosemarie Tan','Rosemarie',NULL,'Tan',NULL,1,'branch_admin','Branch Management','2026-09-16 13:11:20',NULL,1,'2026-09-16 10:52:39'),(14,'mlb.staff','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Jerome Dagoc','Jerome',NULL,'Dagoc',NULL,1,'branch_user','Sales','2026-09-16 10:55:26',NULL,1,'2026-09-16 10:52:39'),(15,'mrm.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Kevin Salvador','Kevin',NULL,'Salvador',NULL,2,'branch_admin','Branch Management','2026-09-16 10:53:59',NULL,1,'2026-09-16 10:52:39'),(16,'val.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Angela Bacus','Angela',NULL,'Bacus',NULL,5,'branch_admin','Branch Management','2026-09-16 10:55:16',NULL,1,'2026-09-16 10:52:39');

/*!40101 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
