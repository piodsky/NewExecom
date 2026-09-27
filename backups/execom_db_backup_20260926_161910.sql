-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: execom_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 00:23:28'),(2,1,'admin','LOGOUT','auth','User logged out','::1','2026-09-16 00:23:49'),(3,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 00:32:53'),(9,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 00:36:31'),(24,1,'admin','CREATE_USER','settings','Created account \"badmin1\" (branch_admin)','::1','2026-09-16 00:39:03'),(26,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 00:50:50'),(43,1,'admin','CREATE_USER','settings','Created account \"bu2\" (branch_user)','::1','2026-09-16 00:52:59'),(46,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 00:53:59'),(47,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:17:54'),(51,6,'bu_mlb','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:20:58'),(52,7,'ba_mrm','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:20:58'),(59,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:38:00'),(72,8,'bu_mlb2','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:50:06'),(73,9,'ba_mrm2','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:50:07'),(74,1,'admin','CREATE_SUPPLIER','suppliers','Created supplier \"Supplier 1\"','::1','2026-09-16 01:54:34'),(75,1,'admin','UPDATE_SUPPLIER','suppliers','Updated supplier \"Supplier Sample 1\"','::1','2026-09-16 01:54:56'),(76,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:59:13'),(81,10,'ba_mlb_cpr','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:59:50'),(82,11,'ba_mrm_cpr','LOGIN','auth','User logged in from ::1','::1','2026-09-16 01:59:50'),(83,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:13:15'),(86,12,'ba_mrm_p6','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:15:08'),(89,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:47:25'),(90,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:52:53'),(91,13,'mlb.admin','CREATE_PR','purchase_requisition','Created MLB-PR-2026-0001 and submitted','::1','2026-09-16 02:52:53'),(92,13,'mlb.admin','APPROVE_PR','purchase_requisition','approved MLB-PR-2026-0001: Approved, proceed to canvass','::1','2026-09-16 02:53:02'),(93,13,'mlb.admin','CREATE_INTERNAL_RFQ','internal_rfq','Created MLB-IRFQ-2026-0001 from MLB-PR-2026-0001','::1','2026-09-16 02:53:02'),(94,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 02:53:20'),(95,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 02:53:20'),(96,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 02:53:20'),(97,13,'mlb.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MLB-IRFQ-2026-0001','::1','2026-09-16 02:53:20'),(98,13,'mlb.admin','CONVERT_RFQ_TO_PO','internal_rfq','MLB-IRFQ-2026-0001 -> MLB-POI-2026-0001, MLB-POI-2026-0002','::1','2026-09-16 02:53:35'),(99,13,'mlb.admin','SEND_PO_INTERNAL','po_internal','Sent MLB-POI-2026-0001 to V INC.','::1','2026-09-16 02:53:47'),(100,13,'mlb.admin','SEND_PO_INTERNAL','po_internal','Sent MLB-POI-2026-0002 to Alpha Distributors','::1','2026-09-16 02:53:47'),(101,13,'mlb.admin','CREATE_RR','receiving_reports','Posted MLB-RR-2026-0001 against MLB-POI-2026-0001','::1','2026-09-16 02:53:47'),(102,13,'mlb.admin','CREATE_RR','receiving_reports','Posted MLB-RR-2026-0002 against MLB-POI-2026-0002','::1','2026-09-16 02:53:47'),(103,15,'mrm.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:53:59'),(104,15,'mrm.admin','CREATE_PR','purchase_requisition','Created MRM-PR-2026-0001 and submitted','::1','2026-09-16 02:53:59'),(105,15,'mrm.admin','APPROVE_PR','purchase_requisition','approved MRM-PR-2026-0001','::1','2026-09-16 02:53:59'),(106,15,'mrm.admin','CREATE_INTERNAL_RFQ','internal_rfq','Created MRM-IRFQ-2026-0001 from MRM-PR-2026-0001','::1','2026-09-16 02:53:59'),(107,15,'mrm.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MRM-IRFQ-2026-0001','::1','2026-09-16 02:54:38'),(108,15,'mrm.admin','QUOTE_INTERNAL_RFQ','internal_rfq','Recorded quote on MRM-IRFQ-2026-0001','::1','2026-09-16 02:54:38'),(109,15,'mrm.admin','CONVERT_RFQ_TO_PO','internal_rfq','MRM-IRFQ-2026-0001 -> MRM-POI-2026-0001','::1','2026-09-16 02:54:49'),(110,15,'mrm.admin','SEND_PO_INTERNAL','po_internal','Sent MRM-POI-2026-0001 to TechSource Philippines','::1','2026-09-16 02:54:49'),(111,15,'mrm.admin','CREATE_RR','receiving_reports','Posted MRM-RR-2026-0001 against MRM-POI-2026-0001','::1','2026-09-16 02:54:59'),(112,13,'mlb.admin','CREATE_STOCK_TRANSFER','stock_transfer','Requested MLB-ST-2026-0001','::1','2026-09-16 02:55:16'),(113,13,'mlb.admin','SHIP_STOCK_TRANSFER','stock_transfer','Shipped MLB-ST-2026-0001','::1','2026-09-16 02:55:16'),(114,16,'val.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:55:16'),(115,16,'val.admin','RECEIVE_STOCK_TRANSFER','stock_transfer','Received MLB-ST-2026-0001','::1','2026-09-16 02:55:16'),(116,14,'mlb.staff','LOGIN','auth','User logged in from ::1','::1','2026-09-16 02:55:26'),(117,14,'mlb.staff','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0001 for 9,000.00','::1','2026-09-16 02:55:26'),(118,16,'val.admin','CREATE_ISSUANCE','issuance','Posted VAL-ISS-2026-0001 for 102,000.00','::1','2026-09-16 02:55:26'),(119,13,'mlb.admin','CREATE_OUTGOING_RFQ','outgoing_rfq','Logged MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office','::1','2026-09-16 02:55:34'),(120,13,'mlb.admin','CREATE_OUTGOING_QUOTATION','outgoing_quotations','Created MLB-OQ-2026-0001 for LGU Malaybalay - General Services Office','::1','2026-09-16 02:55:42'),(121,13,'mlb.admin','CONVERT_QUOTATION_TO_PO','outgoing_quotations','MLB-OQ-2026-0001 -> MLB-POO-2026-0001','::1','2026-09-16 02:55:51'),(122,13,'mlb.admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0002 for 118,500.00','::1','2026-09-16 02:56:05'),(123,13,'mlb.admin','CREATE_DR','delivery_reports','Posted MLB-DR-2026-0001 against MLB-POO-2026-0001','::1','2026-09-16 02:56:05'),(124,15,'mrm.admin','CREATE_OUTGOING_RFQ','outgoing_rfq','Logged MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office','::1','2026-09-16 02:56:16'),(125,15,'mrm.admin','CREATE_OUTGOING_QUOTATION','outgoing_quotations','Created MRM-OQ-2026-0001 for Bukidnon State University - Supply Office','::1','2026-09-16 02:56:16'),(126,15,'mrm.admin','CONVERT_QUOTATION_TO_PO','outgoing_quotations','MRM-OQ-2026-0001 -> MRM-POO-2026-0001','::1','2026-09-16 02:56:17'),(127,16,'val.admin','CREATE_PR','purchase_requisition','Created VAL-PR-2026-0001 and submitted','::1','2026-09-16 02:57:52'),(128,14,'mlb.staff','CREATE_STOCK_TRANSFER','stock_transfer','Requested MLB-ST-2026-0002','::1','2026-09-16 02:57:52'),(129,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 04:54:24'),(130,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 04:55:20'),(131,1,'admin','LOGOUT','auth','User logged out','::1','2026-09-16 05:00:37'),(132,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 05:00:56'),(133,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 05:08:39'),(134,13,'mlb.admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 05:11:20'),(135,13,'mlb.admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0003 for 108,600.00','::1','2026-09-16 05:11:31'),(136,13,'mlb.admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0003','::1','2026-09-16 05:11:47'),(137,1,'admin','CREATE_ISSUANCE','issuance','Posted MRM-ISS-2026-0001 for 39,900.00','::1','2026-09-16 05:14:43'),(138,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 05:18:27'),(139,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0004 for 3,600.00','::1','2026-09-16 08:36:33'),(140,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:41:34'),(141,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 08:49:25'),(142,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-16 23:50:31'),(143,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 00:14:45'),(144,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 02:44:33'),(145,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0005 for 1,800.00','::1','2026-09-17 02:44:33'),(146,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0005','::1','2026-09-17 02:44:47'),(147,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 02:50:06'),(148,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0006 for 1,800.00','::1','2026-09-17 02:50:06'),(149,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0007 for 1,800.00','::1','2026-09-17 02:50:07'),(150,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0006','::1','2026-09-17 02:50:25'),(151,1,'admin','VOID_ISSUANCE','issuance','Voided MLB-ISS-2026-0007','::1','2026-09-17 02:50:25'),(152,1,'admin','CREATE_ISSUANCE','issuance','Posted MLB-ISS-2026-0008 for 3,600.00','::1','2026-09-17 02:51:19'),(153,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 02:55:25'),(154,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 07:45:12'),(155,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-17 23:41:00'),(156,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-18 07:09:12'),(157,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-18 07:09:44'),(163,1,'admin','CREATE_ITEM','items','Created item \"Monitor 27inch\"','::1','2026-09-18 07:29:48'),(164,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:41:56'),(165,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:42:06'),(166,1,'admin','CREATE_WAREHOUSE_LOCATION','warehouse','Created bin \"A-01\"','::1','2026-09-19 00:42:06'),(167,1,'admin','PLACE_INVENTORY_UNIT','warehouse','VICTUS Clamshell 15\"','::1','2026-09-19 00:42:06'),(168,1,'admin','PLACE_INVENTORY_STOCK','warehouse','8GB DDR4 RAM','::1','2026-09-19 00:42:06'),(169,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:43:01'),(170,1,'admin','CREATE_WAREHOUSE_LOCATION','warehouse','Created bin \"A-01\"','::1','2026-09-19 00:43:01'),(171,1,'admin','PLACE_INVENTORY_UNIT','warehouse','VICTUS Clamshell 15\"','::1','2026-09-19 00:43:01'),(172,1,'admin','PLACE_INVENTORY_STOCK','warehouse','8GB DDR4 RAM','::1','2026-09-19 00:43:01'),(173,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:43:19'),(174,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:43:50'),(175,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-19 00:47:39'),(176,1,'admin','PLACE_INVENTORY_UNIT','warehouse','VICTUS Clamshell 15\"','::1','2026-09-19 00:47:40'),(177,1,'admin','PLACE_INVENTORY_STOCK','warehouse','8GB DDR4 RAM','::1','2026-09-19 00:47:40'),(178,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:06:14'),(179,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:13:49'),(180,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:32:49'),(181,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:37:24'),(182,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:38:54'),(183,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:43:06'),(184,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:45:48'),(185,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:46:57'),(186,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0001','::1','2026-09-26 04:46:58'),(187,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:48:10'),(188,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0002','::1','2026-09-26 04:48:11'),(189,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0002 -> repaired','::1','2026-09-26 04:48:13'),(190,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0002','::1','2026-09-26 04:48:14'),(191,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:49:34'),(192,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0003','::1','2026-09-26 04:49:36'),(193,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0003 -> repaired','::1','2026-09-26 04:49:38'),(194,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0003','::1','2026-09-26 04:49:38'),(195,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0004','::1','2026-09-26 04:49:40'),(196,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0004 -> repaired','::1','2026-09-26 04:49:42'),(197,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0004 -> invoice CDO-SI-2026-0001','::1','2026-09-26 04:49:43'),(198,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:50:38'),(199,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0005','::1','2026-09-26 04:50:39'),(200,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0005 -> repaired','::1','2026-09-26 04:50:41'),(201,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0005','::1','2026-09-26 04:50:42'),(202,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0006','::1','2026-09-26 04:50:44'),(203,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0006 -> repaired','::1','2026-09-26 04:50:46'),(204,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0006 -> invoice CDO-SI-2026-0002','::1','2026-09-26 04:50:46'),(205,1,'admin','CREATE_COLLECTION','collections','Recorded MLB-OR-2026-0001 for 300.00','::1','2026-09-26 04:50:50'),(206,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 04:52:08'),(207,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0007','::1','2026-09-26 04:52:10'),(208,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0007 -> repaired','::1','2026-09-26 04:52:12'),(209,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0007','::1','2026-09-26 04:52:12'),(210,1,'admin','CREATE_JOB_ORDER','job_orders','Created CDO-JO-2026-0008','::1','2026-09-26 04:52:14'),(211,1,'admin','UPDATE_JOB_ORDER_STATUS','job_orders','CDO-JO-2026-0008 -> repaired','::1','2026-09-26 04:52:16'),(212,1,'admin','RELEASE_JOB_ORDER','job_orders','Released CDO-JO-2026-0008 -> invoice CDO-SI-2026-0003','::1','2026-09-26 04:52:17'),(213,1,'admin','CREATE_PDC','collections','Recorded MLB-PDC-2026-0001','::1','2026-09-26 04:52:23'),(214,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 05:08:11'),(215,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 05:23:11'),(216,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 05:24:28'),(217,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 05:25:10'),(218,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 06:13:06'),(219,1,'admin','LOGIN','auth','User logged in from ::1','::1','2026-09-26 06:40:59');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'MLB','Malaybalay City',NULL,NULL,1,'2026-09-16 00:21:51'),(2,'MRM','Maramag City',NULL,NULL,1,'2026-09-16 00:21:51'),(3,'DVO','Davao City',NULL,NULL,1,'2026-09-16 00:21:51'),(4,'CDO','Cagayan De Oro City',NULL,NULL,1,'2026-09-16 00:21:51'),(5,'VAL','Valencia City',NULL,NULL,1,'2026-09-16 00:21:51');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `client_type` enum('business','walkin') NOT NULL DEFAULT 'business',
  `payment_terms_days` int(11) DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','Engr. Paolo Ranin','09051234567','business',NULL,0.00,1,'2026-09-16 02:51:49'),(7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','Ms. Elena Cordova','09179988776','business',NULL,0.00,1,'2026-09-16 02:51:49'),(8,'LGU Valencia - Engineering Office','Poblacion, Valencia City, Bukidnon','Engr. Danilo Ang','09201122334','business',NULL,0.00,1,'2026-09-16 02:51:49'),(9,'Maria Santos','456 Mabini St.',NULL,'0917-000-0001','walkin',NULL,0.00,0,'2026-09-17 02:50:06'),(10,'PIODOS',NULL,NULL,NULL,'walkin',NULL,0.00,1,'2026-09-17 02:51:19');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `collection_applications`
--

DROP TABLE IF EXISTS `collection_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collection_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `sales_invoice_id` int(11) NOT NULL,
  `amount_applied` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ca_collection` (`collection_id`),
  KEY `idx_ca_invoice` (`sales_invoice_id`),
  CONSTRAINT `collection_applications_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collection_applications_ibfk_2` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `collection_applications`
--

LOCK TABLES `collection_applications` WRITE;
/*!40000 ALTER TABLE `collection_applications` DISABLE KEYS */;
INSERT INTO `collection_applications` VALUES (1,1,1,285.00);
/*!40000 ALTER TABLE `collection_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `collections`
--

DROP TABLE IF EXISTS `collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `or_number` varchar(30) NOT NULL,
  `client_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer') NOT NULL DEFAULT 'cash',
  `gross_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `withholding_tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `or_number` (`or_number`),
  KEY `received_by` (`received_by`),
  KEY `idx_col_client` (`client_id`),
  KEY `idx_col_date` (`payment_date`),
  CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `collections_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `collections`
--

LOCK TABLES `collections` WRITE;
/*!40000 ALTER TABLE `collections` DISABLE KEYS */;
INSERT INTO `collections` VALUES (1,'MLB-OR-2026-0001',7,'2026-09-26','cash',300.00,15.00,285.00,NULL,1,0,'2026-09-26 04:50:50');
/*!40000 ALTER TABLE `collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_report_items`
--

DROP TABLE IF EXISTS `delivery_report_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_report_items`
--

LOCK TABLES `delivery_report_items` WRITE;
/*!40000 ALTER TABLE `delivery_report_items` DISABLE KEYS */;
INSERT INTO `delivery_report_items` VALUES (3,'MLB-DR-2026-0001',13,1.00,0),(4,'MLB-DR-2026-0001',17,5.00,0);
/*!40000 ALTER TABLE `delivery_report_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_reports`
--

DROP TABLE IF EXISTS `delivery_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_reports`
--

LOCK TABLES `delivery_reports` WRITE;
/*!40000 ALTER TABLE `delivery_reports` DISABLE KEYS */;
INSERT INTO `delivery_reports` VALUES (3,'MLB-DR-2026-0001',1,'MLB-POO-2026-0001',13,'EXECOM Delivery Van','2026-09-28','Engr. Paolo Ranin','2026-09-28',0,'2026-09-16 02:56:05');
/*!40000 ALTER TABLE `delivery_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Sales',1,'2026-09-19 00:37:01'),(2,'Warehouse',1,'2026-09-19 00:37:01'),(3,'Technical/Service',1,'2026-09-19 00:37:01'),(4,'Admin',1,'2026-09-19 00:37:01');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disbursement_applications`
--

DROP TABLE IF EXISTS `disbursement_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursement_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `disbursement_id` int(11) NOT NULL,
  `supplier_invoice_id` int(11) NOT NULL,
  `amount_applied` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_da_disbursement` (`disbursement_id`),
  KEY `idx_da_invoice` (`supplier_invoice_id`),
  CONSTRAINT `disbursement_applications_ibfk_1` FOREIGN KEY (`disbursement_id`) REFERENCES `disbursements` (`id`),
  CONSTRAINT `disbursement_applications_ibfk_2` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disbursement_applications`
--

LOCK TABLES `disbursement_applications` WRITE;
/*!40000 ALTER TABLE `disbursement_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `disbursement_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disbursements`
--

DROP TABLE IF EXISTS `disbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dv_number` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer') NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dv_number` (`dv_number`),
  KEY `paid_by` (`paid_by`),
  KEY `idx_disb_supplier` (`supplier_id`),
  KEY `idx_disb_date` (`payment_date`),
  CONSTRAINT `disbursements_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `disbursements_ibfk_2` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disbursements`
--

LOCK TABLES `disbursements` WRITE;
/*!40000 ALTER TABLE `disbursements` DISABLE KEYS */;
/*!40000 ALTER TABLE `disbursements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doc_sequences`
--

DROP TABLE IF EXISTS `doc_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doc_sequences` (
  `branch_id` int(11) NOT NULL,
  `doc_type` varchar(20) NOT NULL,
  `year` smallint(6) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`branch_id`,`doc_type`,`year`),
  CONSTRAINT `doc_sequences_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doc_sequences`
--

LOCK TABLES `doc_sequences` WRITE;
/*!40000 ALTER TABLE `doc_sequences` DISABLE KEYS */;
INSERT INTO `doc_sequences` VALUES (1,'DR',2026,1),(1,'DV',2026,2),(1,'IRFQ',2026,1),(1,'ISS',2026,8),(1,'OQ',2026,1),(1,'OR',2026,1),(1,'ORFQ',2026,1),(1,'PDC',2026,2),(1,'PINV',2026,1),(1,'POI',2026,2),(1,'POO',2026,1),(1,'PR',2026,1),(1,'RR',2026,2),(1,'ST',2026,2),(2,'IRFQ',2026,1),(2,'ISS',2026,1),(2,'OQ',2026,1),(2,'ORFQ',2026,1),(2,'POI',2026,1),(2,'POO',2026,1),(2,'PR',2026,1),(2,'RR',2026,1),(4,'JO',2026,8),(4,'SI',2026,3),(5,'ISS',2026,1),(5,'PR',2026,1);
/*!40000 ALTER TABLE `doc_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_stock`
--

DROP TABLE IF EXISTS `inventory_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `quantity_on_hand` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(14,2) NOT NULL DEFAULT 0.00,
  `location_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_branch` (`item_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `inventory_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `inventory_stock_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `inventory_stock_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_stock`
--

LOCK TABLES `inventory_stock` WRITE;
/*!40000 ALTER TABLE `inventory_stock` DISABLE KEYS */;
INSERT INTO `inventory_stock` VALUES (9,17,1,6.00,0.00,23);
/*!40000 ALTER TABLE `inventory_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_unassigned_units`
--

DROP TABLE IF EXISTS `inventory_unassigned_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_unassigned_units`
--

LOCK TABLES `inventory_unassigned_units` WRITE;
/*!40000 ALTER TABLE `inventory_unassigned_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_unassigned_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_units`
--

DROP TABLE IF EXISTS `inventory_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `location_id` int(11) DEFAULT NULL,
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
  KEY `idx_invu_location` (`location_id`),
  CONSTRAINT `inventory_units_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `inventory_units_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `inventory_units_ibfk_3` FOREIGN KEY (`source_rr_number`) REFERENCES `receiving_reports` (`rr_number`),
  CONSTRAINT `inventory_units_ibfk_4` FOREIGN KEY (`reserved_for_client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `inventory_units_ibfk_5` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_units`
--

LOCK TABLES `inventory_units` WRITE;
/*!40000 ALTER TABLE `inventory_units` DISABLE KEYS */;
INSERT INTO `inventory_units` VALUES (7,13,1,'HP-VC15-0001','issued',92000.00,'MLB-RR-2026-0001',NULL,NULL,'2026-09-20','2026-09-16',NULL,NULL,NULL,0,'2026-09-16 02:53:47'),(8,13,1,'HP-VC15-0002','in_stock',92000.00,'MLB-RR-2026-0001',NULL,NULL,'2026-09-20',NULL,NULL,3,NULL,0,'2026-09-16 02:53:47'),(9,13,5,'HP-VC15-0003','issued',92000.00,'MLB-RR-2026-0001',NULL,NULL,'2026-09-20','2026-09-16',NULL,NULL,NULL,0,'2026-09-16 02:53:47'),(10,19,2,'EP-L3210-0001','issued',8300.00,'MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,NULL,0,'2026-09-16 02:54:59'),(11,19,2,'EP-L3210-0002','issued',8300.00,'MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,NULL,0,'2026-09-16 02:54:59'),(12,20,2,'HIK-4CH-0001','issued',14200.00,'MRM-RR-2026-0001',NULL,NULL,'2026-09-25','2026-09-16',NULL,NULL,NULL,0,'2026-09-16 02:54:59');
/*!40000 ALTER TABLE `inventory_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `issuance_items`
--

DROP TABLE IF EXISTS `issuance_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `issuance_items`
--

LOCK TABLES `issuance_items` WRITE;
/*!40000 ALTER TABLE `issuance_items` DISABLE KEYS */;
INSERT INTO `issuance_items` VALUES (12,'MLB-ISS-2026-0001',NULL,17,5.00,1800.00,9000.00,0),(13,'VAL-ISS-2026-0001',9,NULL,1.00,102000.00,102000.00,0),(14,'MLB-ISS-2026-0002',7,NULL,1.00,108000.00,108000.00,0),(15,'MLB-ISS-2026-0002',NULL,17,5.00,2100.00,10500.00,0),(16,'MLB-ISS-2026-0003',8,NULL,1.00,105000.00,105000.00,0),(17,'MLB-ISS-2026-0003',NULL,17,2.00,1800.00,3600.00,0),(18,'MRM-ISS-2026-0001',12,NULL,1.00,18900.00,18900.00,0),(19,'MRM-ISS-2026-0001',10,NULL,1.00,10500.00,10500.00,0),(20,'MRM-ISS-2026-0001',11,NULL,1.00,10500.00,10500.00,0),(21,'MLB-ISS-2026-0004',NULL,17,2.00,1800.00,3600.00,0),(22,'MLB-ISS-2026-0005',NULL,17,1.00,1800.00,1800.00,0),(23,'MLB-ISS-2026-0006',NULL,17,1.00,1800.00,1800.00,0),(24,'MLB-ISS-2026-0007',NULL,17,1.00,1800.00,1800.00,0),(25,'MLB-ISS-2026-0008',NULL,17,2.00,1800.00,3600.00,0);
/*!40000 ALTER TABLE `issuance_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `issuances`
--

DROP TABLE IF EXISTS `issuances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `issuances`
--

LOCK TABLES `issuances` WRITE;
/*!40000 ALTER TABLE `issuances` DISABLE KEYS */;
INSERT INTO `issuances` VALUES (11,'MLB-ISS-2026-0001',1,NULL,NULL,NULL,NULL,NULL,14,'2026-09-16 10:55:26',9000.00,0,'2026-09-16 02:55:26'),(12,'VAL-ISS-2026-0001',5,NULL,NULL,NULL,NULL,NULL,16,'2026-09-16 10:55:26',102000.00,0,'2026-09-16 02:55:26'),(13,'MLB-ISS-2026-0002',1,6,NULL,NULL,NULL,'MLB-POO-2026-0001',13,'2026-09-16 10:56:05',118500.00,0,'2026-09-16 02:56:05'),(14,'MLB-ISS-2026-0003',1,NULL,NULL,NULL,NULL,NULL,13,'2026-09-16 13:11:31',108600.00,1,'2026-09-16 05:11:31'),(15,'MRM-ISS-2026-0001',2,7,NULL,NULL,NULL,NULL,1,'2026-09-16 13:14:43',39900.00,0,'2026-09-16 05:14:43'),(16,'MLB-ISS-2026-0004',1,NULL,NULL,NULL,NULL,NULL,1,'2026-09-16 16:36:33',3600.00,0,'2026-09-16 08:36:33'),(17,'MLB-ISS-2026-0005',1,NULL,'Juan Dela Cruz','123 Sampaguita St.','0917-555-1234',NULL,1,'2026-09-17 10:44:33',1800.00,1,'2026-09-17 02:44:33'),(18,'MLB-ISS-2026-0006',1,9,'Maria Santos','456 Mabini St.','0917-000-0001',NULL,1,'2026-09-17 10:50:06',1800.00,1,'2026-09-17 02:50:06'),(19,'MLB-ISS-2026-0007',1,9,'Maria Santos',NULL,NULL,NULL,1,'2026-09-17 10:50:07',1800.00,1,'2026-09-17 02:50:07'),(20,'MLB-ISS-2026-0008',1,10,'PIODOS',NULL,NULL,NULL,1,'2026-09-17 10:51:19',3600.00,0,'2026-09-17 02:51:19');
/*!40000 ALTER TABLE `issuances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_serialized` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
INSERT INTO `item_categories` VALUES (1,'Monitor',1,1),(2,'Laptop / All-in-One',1,1),(3,'Processor / Motherboard',1,1),(4,'SSD / HDD / External Storage',1,1),(5,'RAM',0,1),(6,'Printer',1,1),(7,'CCTV',1,1),(8,'Projector / Scanner / Webcam',1,1),(9,'Others',0,1);
/*!40000 ALTER TABLE `item_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (13,'LAP-VICTUS-15','VICTUS Clamshell 15\"',2,'HP','unit',1,90000.00,100000.00,1,'2026-09-16 02:52:28'),(14,'LAP-ASPIRE-5','Aspire 5 Laptop',2,'Acer','unit',1,32000.00,39900.00,1,'2026-09-16 02:52:28'),(15,'MON-24-LED','Samsung 24\" LED Monitor',1,'Samsung','unit',1,6200.00,7800.00,1,'2026-09-16 02:52:28'),(16,'SSD-480GB','480GB SATA SSD',4,'Kingston','unit',1,2200.00,2900.00,1,'2026-09-16 02:52:28'),(17,'RAM-8GB-DDR4','8GB DDR4 RAM',5,'Kingston','pc',0,1200.00,1800.00,1,'2026-09-16 02:52:28'),(18,'RAM-16GB-DDR4','16GB DDR4 RAM',5,'Kingston','pc',0,2300.00,3100.00,1,'2026-09-16 02:52:28'),(19,'PRN-L3210','EcoTank L3210 Printer',6,'Epson','unit',1,8500.00,10500.00,1,'2026-09-16 02:52:28'),(20,'CCTV-4CH-KIT','4-Channel CCTV Kit',7,'Hikvision','set',1,14500.00,18900.00,1,'2026-09-16 02:52:28'),(21,'CBL-HDMI-2M','HDMI Cable 2m',9,'Generic','pc',0,120.00,250.00,1,'2026-09-16 02:52:28'),(22,'USB-32GB','USB Flash Drive 32GB',9,'SanDisk','pc',0,280.00,450.00,1,'2026-09-16 02:52:28'),(23,'MonitorGambias','Monitor 27inch',1,'Gambias','unit',1,10000.00,9000.00,1,'2026-09-18 07:29:48');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_order_parts`
--

DROP TABLE IF EXISTS `job_order_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_order_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jo_number` varchar(30) NOT NULL,
  `inventory_unit_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` decimal(11,2) DEFAULT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `inventory_unit_id` (`inventory_unit_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_jop_jo` (`jo_number`),
  CONSTRAINT `job_order_parts_ibfk_1` FOREIGN KEY (`jo_number`) REFERENCES `job_orders` (`jo_number`),
  CONSTRAINT `job_order_parts_ibfk_2` FOREIGN KEY (`inventory_unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `job_order_parts_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_order_parts`
--

LOCK TABLES `job_order_parts` WRITE;
/*!40000 ALTER TABLE `job_order_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_order_parts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_orders`
--

DROP TABLE IF EXISTS `job_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jo_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `walkin_name` varchar(150) DEFAULT NULL,
  `walkin_contact` varchar(50) DEFAULT NULL,
  `unit_description` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `problem_description` varchar(255) DEFAULT NULL,
  `accessories_received` varchar(255) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `status` enum('received','in_diagnosis','awaiting_parts','repaired','released','cancelled') NOT NULL DEFAULT 'received',
  `is_under_warranty` tinyint(1) NOT NULL DEFAULT 0,
  `labor_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `date_received` datetime DEFAULT NULL,
  `date_released` datetime DEFAULT NULL,
  `issuance_number` varchar(30) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `jo_number` (`jo_number`),
  KEY `client_id` (`client_id`),
  KEY `technician_id` (`technician_id`),
  KEY `issuance_number` (`issuance_number`),
  KEY `received_by` (`received_by`),
  KEY `idx_jo_branch` (`branch_id`),
  KEY `idx_jo_status` (`status`),
  CONSTRAINT `job_orders_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `job_orders_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `job_orders_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`),
  CONSTRAINT `job_orders_ibfk_4` FOREIGN KEY (`issuance_number`) REFERENCES `issuances` (`issuance_number`),
  CONSTRAINT `job_orders_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_orders`
--

LOCK TABLES `job_orders` WRITE;
/*!40000 ALTER TABLE `job_orders` DISABLE KEYS */;
INSERT INTO `job_orders` VALUES (1,'CDO-JO-2026-0001',4,NULL,'E2E Test Customer','09170000000','E2E Test Laptop Model X','E2E-SN-0001','Will not power on',NULL,NULL,'received',0,250.00,'2026-09-26 12:46:58',NULL,NULL,NULL,1,0,'2026-09-26 04:46:58'),(2,'CDO-JO-2026-0002',4,NULL,'E2E Test Customer','09170000000','E2E Test Laptop Model X','E2E-SN-0001','Will not power on',NULL,NULL,'released',0,250.00,'2026-09-26 12:48:11','2026-09-26 12:48:14',NULL,NULL,1,0,'2026-09-26 04:48:11'),(3,'CDO-JO-2026-0003',4,NULL,'E2E Test Customer','09170000000','E2E Test Laptop Model X','E2E-SN-0001','Will not power on',NULL,NULL,'released',0,250.00,'2026-09-26 12:49:36','2026-09-26 12:49:38',NULL,NULL,1,0,'2026-09-26 04:49:36'),(4,'CDO-JO-2026-0004',4,7,NULL,NULL,'E2E Client Laptop',NULL,'Screen flickering',NULL,NULL,'released',0,500.00,'2026-09-26 12:49:40','2026-09-26 12:49:43',NULL,NULL,1,0,'2026-09-26 04:49:40'),(5,'CDO-JO-2026-0005',4,NULL,'E2E Test Customer','09170000000','E2E Test Laptop Model X','E2E-SN-0001','Will not power on',NULL,NULL,'released',0,250.00,'2026-09-26 12:50:39','2026-09-26 12:50:42',NULL,NULL,1,0,'2026-09-26 04:50:39'),(6,'CDO-JO-2026-0006',4,7,NULL,NULL,'E2E Client Laptop',NULL,'Screen flickering',NULL,NULL,'released',0,500.00,'2026-09-26 12:50:44','2026-09-26 12:50:46',NULL,NULL,1,0,'2026-09-26 04:50:44'),(7,'CDO-JO-2026-0007',4,NULL,'E2E Test Customer','09170000000','E2E Test Laptop Model X','E2E-SN-0001','Will not power on',NULL,NULL,'released',0,250.00,'2026-09-26 12:52:10','2026-09-26 12:52:12',NULL,NULL,1,0,'2026-09-26 04:52:10'),(8,'CDO-JO-2026-0008',4,7,NULL,NULL,'E2E Client Laptop',NULL,'Screen flickering',NULL,NULL,'released',0,500.00,'2026-09-26 12:52:14','2026-09-26 12:52:17',NULL,NULL,1,0,'2026-09-26 04:52:14');
/*!40000 ALTER TABLE `job_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_dismissals`
--

DROP TABLE IF EXISTS `notification_dismissals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_dismissals` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dismissed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_dismissals_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_dismissals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_dismissals`
--

LOCK TABLES `notification_dismissals` WRITE;
/*!40000 ALTER TABLE `notification_dismissals` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_dismissals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_reads`
--

DROP TABLE IF EXISTS `notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_reads`
--

LOCK TABLES `notification_reads` WRITE;
/*!40000 ALTER TABLE `notification_reads` DISABLE KEYS */;
INSERT INTO `notification_reads` VALUES (14,1,'2026-09-16 23:50:39'),(16,1,'2026-09-16 23:50:39'),(18,1,'2026-09-16 23:50:39'),(20,1,'2026-09-16 23:50:39'),(22,1,'2026-09-16 23:50:39'),(24,1,'2026-09-16 23:50:39'),(26,1,'2026-09-16 23:50:39'),(28,1,'2026-09-16 23:50:39'),(30,1,'2026-09-16 23:50:39'),(32,1,'2026-09-16 23:50:39'),(34,1,'2026-09-16 23:50:39'),(36,1,'2026-09-16 23:50:39'),(38,1,'2026-09-16 23:50:39'),(40,1,'2026-09-16 23:50:39'),(42,1,'2026-09-16 23:50:39');
/*!40000 ALTER TABLE `notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (13,'branch_admin','Requisition approved','MLB-PR-2026-0001 was approved by Rosemarie Tan','purchase_requisitions.php','2026-09-16 02:53:02'),(14,'super_admin','Requisition approved','MLB-PR-2026-0001 was approved by Rosemarie Tan','purchase_requisitions.php','2026-09-16 02:53:02'),(15,'branch_admin','Internal PO created','MLB-IRFQ-2026-0001 converted to MLB-POI-2026-0001, MLB-POI-2026-0002','po_internal.php','2026-09-16 02:53:35'),(16,'super_admin','Internal PO created','MLB-IRFQ-2026-0001 converted to MLB-POI-2026-0001, MLB-POI-2026-0002','po_internal.php','2026-09-16 02:53:35'),(17,'branch_admin','Purchase Order fully received','MLB-POI-2026-0001 from V INC. is complete','po_internal.php','2026-09-16 02:53:47'),(18,'super_admin','Purchase Order fully received','MLB-POI-2026-0001 from V INC. is complete','po_internal.php','2026-09-16 02:53:47'),(19,'branch_admin','Purchase Order fully received','MLB-POI-2026-0002 from Alpha Distributors is complete','po_internal.php','2026-09-16 02:53:47'),(20,'super_admin','Purchase Order fully received','MLB-POI-2026-0002 from Alpha Distributors is complete','po_internal.php','2026-09-16 02:53:47'),(21,'branch_admin','Requisition approved','MRM-PR-2026-0001 was approved by Kevin Salvador','purchase_requisitions.php','2026-09-16 02:53:59'),(22,'super_admin','Requisition approved','MRM-PR-2026-0001 was approved by Kevin Salvador','purchase_requisitions.php','2026-09-16 02:53:59'),(23,'branch_admin','Internal PO created','MRM-IRFQ-2026-0001 converted to MRM-POI-2026-0001','po_internal.php','2026-09-16 02:54:49'),(24,'super_admin','Internal PO created','MRM-IRFQ-2026-0001 converted to MRM-POI-2026-0001','po_internal.php','2026-09-16 02:54:49'),(25,'branch_admin','Stock transfer requested','MLB-ST-2026-0001 is awaiting shipment','stock_transfers.php','2026-09-16 02:55:16'),(26,'super_admin','Stock transfer requested','MLB-ST-2026-0001 is awaiting shipment','stock_transfers.php','2026-09-16 02:55:16'),(27,'branch_admin','Stock transfer in transit','MLB-ST-2026-0001 is on its way to Valencia City','stock_transfers.php','2026-09-16 02:55:16'),(28,'super_admin','Stock transfer in transit','MLB-ST-2026-0001 is on its way to Valencia City','stock_transfers.php','2026-09-16 02:55:16'),(29,'branch_admin','Stock transfer received','MLB-ST-2026-0001 has arrived at Valencia City','stock_transfers.php','2026-09-16 02:55:16'),(30,'super_admin','Stock transfer received','MLB-ST-2026-0001 has arrived at Valencia City','stock_transfers.php','2026-09-16 02:55:16'),(31,'branch_admin','Canvass received','MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office needs a quotation','outgoing_rfq.php','2026-09-16 02:55:34'),(32,'super_admin','Canvass received','MLB-ORFQ-2026-0001 from LGU Malaybalay - General Services Office needs a quotation','outgoing_rfq.php','2026-09-16 02:55:34'),(33,'branch_admin','Client order awarded','MLB-OQ-2026-0001 converted to MLB-POO-2026-0001 for LGU Malaybalay - General Services Office','po_outgoing.php','2026-09-16 02:55:51'),(34,'super_admin','Client order awarded','MLB-OQ-2026-0001 converted to MLB-POO-2026-0001 for LGU Malaybalay - General Services Office','po_outgoing.php','2026-09-16 02:55:51'),(35,'branch_admin','Order completed','MLB-POO-2026-0001 signed for by Engr. Paolo Ranin and closed','po_outgoing.php','2026-09-16 02:56:05'),(36,'super_admin','Order completed','MLB-POO-2026-0001 signed for by Engr. Paolo Ranin and closed','po_outgoing.php','2026-09-16 02:56:05'),(37,'branch_admin','Canvass received','MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office needs a quotation','outgoing_rfq.php','2026-09-16 02:56:16'),(38,'super_admin','Canvass received','MRM-ORFQ-2026-0001 from Bukidnon State University - Supply Office needs a quotation','outgoing_rfq.php','2026-09-16 02:56:16'),(39,'branch_admin','Client order awarded','MRM-OQ-2026-0001 converted to MRM-POO-2026-0001 for Bukidnon State University - Supply Office','po_outgoing.php','2026-09-16 02:56:17'),(40,'super_admin','Client order awarded','MRM-OQ-2026-0001 converted to MRM-POO-2026-0001 for Bukidnon State University - Supply Office','po_outgoing.php','2026-09-16 02:56:17'),(41,'branch_admin','Stock transfer requested','MLB-ST-2026-0002 is awaiting shipment','stock_transfers.php','2026-09-16 02:57:52'),(42,'super_admin','Stock transfer requested','MLB-ST-2026-0002 is awaiting shipment','stock_transfers.php','2026-09-16 02:57:52');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_quotation_items`
--

DROP TABLE IF EXISTS `outgoing_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_quotation_items`
--

LOCK TABLES `outgoing_quotation_items` WRITE;
/*!40000 ALTER TABLE `outgoing_quotation_items` DISABLE KEYS */;
INSERT INTO `outgoing_quotation_items` VALUES (5,'MLB-OQ-2026-0001',13,'VICTUS Clamshell 15\"',1.00,'unit',108000.00,108000.00,0),(6,'MLB-OQ-2026-0001',17,'8GB DDR4 RAM',5.00,'pc',2100.00,10500.00,0),(7,'MRM-OQ-2026-0001',20,'4-Channel CCTV Kit',1.00,'set',18900.00,18900.00,0),(8,'MRM-OQ-2026-0001',19,'EcoTank L3210 Printer',2.00,'unit',11200.00,22400.00,0);
/*!40000 ALTER TABLE `outgoing_quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_quotations`
--

DROP TABLE IF EXISTS `outgoing_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_quotations`
--

LOCK TABLES `outgoing_quotations` WRITE;
/*!40000 ALTER TABLE `outgoing_quotations` DISABLE KEYS */;
INSERT INTO `outgoing_quotations` VALUES (3,'MLB-OQ-2026-0001','MLB-ORFQ-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon',15,'7 working days',118500.00,'converted',13,0,'2026-09-16 02:55:42'),(4,'MRM-OQ-2026-0001','MRM-ORFQ-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon',10,'10 working days',41300.00,'converted',15,0,'2026-09-16 02:56:16');
/*!40000 ALTER TABLE `outgoing_quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_rfq`
--

DROP TABLE IF EXISTS `outgoing_rfq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_rfq`
--

LOCK TABLES `outgoing_rfq` WRITE;
/*!40000 ALTER TABLE `outgoing_rfq` DISABLE KEYS */;
INSERT INTO `outgoing_rfq` VALUES (2,'MLB-ORFQ-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','2026-09-18','2026-10-02','converted',13,0,'2026-09-16 02:55:34'),(3,'MRM-ORFQ-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','2026-09-20','2026-10-10','converted',15,0,'2026-09-16 02:56:16');
/*!40000 ALTER TABLE `outgoing_rfq` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_rfq_items`
--

DROP TABLE IF EXISTS `outgoing_rfq_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_rfq_items`
--

LOCK TABLES `outgoing_rfq_items` WRITE;
/*!40000 ALTER TABLE `outgoing_rfq_items` DISABLE KEYS */;
INSERT INTO `outgoing_rfq_items` VALUES (2,'MLB-ORFQ-2026-0001',13,'VICTUS Clamshell 15\"',1.00,'unit',0),(3,'MLB-ORFQ-2026-0001',17,'8GB DDR4 RAM',5.00,'pc',0),(4,'MRM-ORFQ-2026-0001',20,'4-Channel CCTV Kit',1.00,'set',0),(5,'MRM-ORFQ-2026-0001',19,'EcoTank L3210 Printer',2.00,'unit',0);
/*!40000 ALTER TABLE `outgoing_rfq_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_internal`
--

DROP TABLE IF EXISTS `po_internal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `po_internal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_address` varchar(255) DEFAULT NULL,
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
  KEY `created_by` (`created_by`),
  KEY `idx_poi_branch` (`branch_id`),
  KEY `idx_poi_status` (`status`),
  CONSTRAINT `po_internal_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `po_internal_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `po_internal_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_internal`
--

LOCK TABLES `po_internal` WRITE;
/*!40000 ALTER TABLE `po_internal` DISABLE KEYS */;
INSERT INTO `po_internal` VALUES (4,'MLB-POI-2026-0001',1,7,'V INC.','Quezon City, Metro Manila',NULL,NULL,'received',276000.00,13,0,'2026-09-16 02:53:35'),(5,'MLB-POI-2026-0002',1,8,'Alpha Distributors','Cebu City, Cebu',NULL,NULL,'received',23000.00,13,0,'2026-09-16 02:53:35'),(6,'MRM-POI-2026-0001',2,9,'TechSource Philippines','Davao City, Davao del Sur',NULL,NULL,'partially_received',61600.00,15,0,'2026-09-16 02:54:49');
/*!40000 ALTER TABLE `po_internal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_internal_items`
--

DROP TABLE IF EXISTS `po_internal_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_internal_items`
--

LOCK TABLES `po_internal_items` WRITE;
/*!40000 ALTER TABLE `po_internal_items` DISABLE KEYS */;
INSERT INTO `po_internal_items` VALUES (4,'MLB-POI-2026-0001',13,NULL,'VICTUS Clamshell 15\"',3.00,'unit',92000.00,276000.00,0),(5,'MLB-POI-2026-0002',17,NULL,'8GB DDR4 RAM',20.00,'pc',1150.00,23000.00,0),(6,'MRM-POI-2026-0001',19,NULL,'EcoTank L3210 Printer',4.00,'unit',8300.00,33200.00,0),(7,'MRM-POI-2026-0001',20,NULL,'4-Channel CCTV Kit',2.00,'set',14200.00,28400.00,0);
/*!40000 ALTER TABLE `po_internal_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_outgoing`
--

DROP TABLE IF EXISTS `po_outgoing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_outgoing`
--

LOCK TABLES `po_outgoing` WRITE;
/*!40000 ALTER TABLE `po_outgoing` DISABLE KEYS */;
INSERT INTO `po_outgoing` VALUES (6,'MLB-POO-2026-0001',1,6,'LGU Malaybalay - General Services Office','Capitol Compound, Malaybalay City, Bukidnon','MLB-OQ-2026-0001','GSO Malaybalay','Capitol Compound, Malaybalay City','FOB Destination','30 days','completed',118500.00,13,0,'2026-09-16 02:55:51'),(7,'MRM-POO-2026-0001',2,7,'Bukidnon State University - Supply Office','Fortich St, Malaybalay City, Bukidnon','MRM-OQ-2026-0001','BukSU Supply Office','Fortich St, Malaybalay City','FOB Destination','Cash on Delivery','open',41300.00,15,0,'2026-09-16 02:56:17');
/*!40000 ALTER TABLE `po_outgoing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_outgoing_items`
--

DROP TABLE IF EXISTS `po_outgoing_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_outgoing_items`
--

LOCK TABLES `po_outgoing_items` WRITE;
/*!40000 ALTER TABLE `po_outgoing_items` DISABLE KEYS */;
INSERT INTO `po_outgoing_items` VALUES (8,'MLB-POO-2026-0001',13,'VICTUS Clamshell 15\"',1.00,'unit',108000.00,108000.00,0),(9,'MLB-POO-2026-0001',17,'8GB DDR4 RAM',5.00,'pc',2100.00,10500.00,0),(10,'MRM-POO-2026-0001',20,'4-Channel CCTV Kit',1.00,'set',18900.00,18900.00,0),(11,'MRM-POO-2026-0001',19,'EcoTank L3210 Printer',2.00,'unit',11200.00,22400.00,0);
/*!40000 ALTER TABLE `po_outgoing_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_dated_checks`
--

DROP TABLE IF EXISTS `post_dated_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_dated_checks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pdc_number` varchar(30) NOT NULL,
  `direction` enum('received','issued') NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `check_number` varchar(30) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `check_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('on_hand','deposited','cleared','bounced') NOT NULL DEFAULT 'on_hand',
  `collection_id` int(11) DEFAULT NULL,
  `disbursement_id` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdc_number` (`pdc_number`),
  KEY `client_id` (`client_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `collection_id` (`collection_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_pdc_status` (`status`),
  KEY `idx_pdc_checkdate` (`check_date`),
  KEY `disbursement_id` (`disbursement_id`),
  CONSTRAINT `post_dated_checks_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `post_dated_checks_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `post_dated_checks_ibfk_3` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `post_dated_checks_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `post_dated_checks_ibfk_5` FOREIGN KEY (`disbursement_id`) REFERENCES `disbursements` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_dated_checks`
--

LOCK TABLES `post_dated_checks` WRITE;
/*!40000 ALTER TABLE `post_dated_checks` DISABLE KEYS */;
INSERT INTO `post_dated_checks` VALUES (1,'MLB-PDC-2026-0001','received',7,NULL,'CHK-E2E-0001','BDO','2026-11-15',1000.00,'on_hand',NULL,NULL,NULL,1,0,'2026-09-26 04:52:23');
/*!40000 ALTER TABLE `post_dated_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receiving_report_items`
--

DROP TABLE IF EXISTS `receiving_report_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receiving_report_items`
--

LOCK TABLES `receiving_report_items` WRITE;
/*!40000 ALTER TABLE `receiving_report_items` DISABLE KEYS */;
INSERT INTO `receiving_report_items` VALUES (4,'MLB-RR-2026-0001',13,1.00,92000.00,'HP-VC15-0001',0),(5,'MLB-RR-2026-0001',13,1.00,92000.00,'HP-VC15-0002',0),(6,'MLB-RR-2026-0001',13,1.00,92000.00,'HP-VC15-0003',0),(7,'MLB-RR-2026-0002',17,20.00,1150.00,NULL,0),(8,'MRM-RR-2026-0001',19,1.00,8300.00,'EP-L3210-0001',0),(9,'MRM-RR-2026-0001',19,1.00,8300.00,'EP-L3210-0002',0),(10,'MRM-RR-2026-0001',20,1.00,14200.00,'HIK-4CH-0001',0);
/*!40000 ALTER TABLE `receiving_report_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receiving_reports`
--

DROP TABLE IF EXISTS `receiving_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receiving_reports`
--

LOCK TABLES `receiving_reports` WRITE;
/*!40000 ALTER TABLE `receiving_reports` DISABLE KEYS */;
INSERT INTO `receiving_reports` VALUES (4,'MLB-RR-2026-0001',1,'MLB-POI-2026-0001',13,'2026-09-20','All units in good condition',0,'2026-09-16 02:53:47'),(5,'MLB-RR-2026-0002',1,'MLB-POI-2026-0002',13,'2026-09-20','Complete delivery',0,'2026-09-16 02:53:47'),(6,'MRM-RR-2026-0001',2,'MRM-POI-2026-0001',15,'2026-09-25','Partial delivery, remainder on backorder',0,'2026-09-16 02:54:59');
/*!40000 ALTER TABLE `receiving_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ref_branches`
--

DROP TABLE IF EXISTS `ref_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ref_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_all_branches` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ref_branches`
--

LOCK TABLES `ref_branches` WRITE;
/*!40000 ALTER TABLE `ref_branches` DISABLE KEYS */;
INSERT INTO `ref_branches` VALUES (1,'ADMIN','ADMIN (Oversee all)',1,0,'2026-09-26 04:22:26'),(2,'MLB','Malaybalay City',0,1,'2026-09-26 04:22:26'),(3,'VAL','Valencia City',0,2,'2026-09-26 04:22:26'),(4,'CDO','Cagayan De Oro City',0,3,'2026-09-26 04:22:26'),(5,'MRM','Maramag City',0,4,'2026-09-26 04:22:26'),(6,'DVO','Davao City',0,5,'2026-09-26 04:22:26');
/*!40000 ALTER TABLE `ref_branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ref_roles`
--

DROP TABLE IF EXISTS `ref_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ref_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_key` varchar(30) NOT NULL,
  `label` varchar(100) NOT NULL,
  `rank` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ref_roles`
--

LOCK TABLES `ref_roles` WRITE;
/*!40000 ALTER TABLE `ref_roles` DISABLE KEYS */;
INSERT INTO `ref_roles` VALUES (1,'super_admin','Super Administrator',3,0,'2026-09-26 04:55:59'),(2,'branch_admin','Branch Administrator',2,1,'2026-09-26 04:55:59'),(3,'branch_user','Branch User',1,2,'2026-09-26 04:55:59');
/*!40000 ALTER TABLE `ref_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_module_access`
--

DROP TABLE IF EXISTS `role_module_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_module_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(30) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_module` (`role`,`module_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_module_access`
--

LOCK TABLES `role_module_access` WRITE;
/*!40000 ALTER TABLE `role_module_access` DISABLE KEYS */;
INSERT INTO `role_module_access` VALUES (1,'branch_user','settings',0,'2026-09-16 00:21:51');
/*!40000 ALTER TABLE `role_module_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoices`
--

DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(30) NOT NULL,
  `issuance_number` varchar(30) DEFAULT NULL,
  `jo_number` varchar(30) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `issuance_number` (`issuance_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_si_client` (`client_id`),
  KEY `idx_si_status` (`status`),
  KEY `idx_si_duedate` (`due_date`),
  KEY `idx_si_jo` (`jo_number`),
  CONSTRAINT `fk_si_jo` FOREIGN KEY (`jo_number`) REFERENCES `job_orders` (`jo_number`),
  CONSTRAINT `sales_invoices_ibfk_1` FOREIGN KEY (`issuance_number`) REFERENCES `issuances` (`issuance_number`),
  CONSTRAINT `sales_invoices_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `sales_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoices`
--

LOCK TABLES `sales_invoices` WRITE;
/*!40000 ALTER TABLE `sales_invoices` DISABLE KEYS */;
INSERT INTO `sales_invoices` VALUES (1,'CDO-SI-2026-0001',NULL,'CDO-JO-2026-0004',7,'2026-09-26',500.00,285.00,215.00,'partial',1,0,'2026-09-26 04:49:43'),(2,'CDO-SI-2026-0002',NULL,'CDO-JO-2026-0006',7,'2026-09-26',500.00,0.00,500.00,'unpaid',1,0,'2026-09-26 04:50:46'),(3,'CDO-SI-2026-0003',NULL,'CDO-JO-2026-0008',7,'2026-09-26',500.00,0.00,500.00,'unpaid',1,0,'2026-09-26 04:52:17');
/*!40000 ALTER TABLE `sales_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfer_items`
--

DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfer_items`
--

LOCK TABLES `stock_transfer_items` WRITE;
/*!40000 ALTER TABLE `stock_transfer_items` DISABLE KEYS */;
INSERT INTO `stock_transfer_items` VALUES (4,'MLB-ST-2026-0001',9,NULL,NULL,'VICTUS Clamshell 15\"','HP-VC15-0003',0),(5,'MLB-ST-2026-0002',NULL,17,5.00,'8GB DDR4 RAM',NULL,0);
/*!40000 ALTER TABLE `stock_transfer_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfers`
--

LOCK TABLES `stock_transfers` WRITE;
/*!40000 ALTER TABLE `stock_transfers` DISABLE KEYS */;
INSERT INTO `stock_transfers` VALUES (3,'MLB-ST-2026-0001',1,5,13,13,'received','2026-09-16 10:55:16','2026-09-16 10:55:16',0,'2026-09-16 02:55:16'),(4,'MLB-ST-2026-0002',1,4,14,NULL,'pending','2026-09-16 10:57:52',NULL,0,'2026-09-16 02:57:52');
/*!40000 ALTER TABLE `stock_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_invoices`
--

DROP TABLE IF EXISTS `supplier_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(30) NOT NULL,
  `rr_number` varchar(30) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `due_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
  `created_by` int(11) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_pinv_supplier` (`supplier_id`),
  KEY `idx_pinv_status` (`status`),
  KEY `idx_pinv_duedate` (`due_date`),
  KEY `idx_pinv_rr` (`rr_number`),
  CONSTRAINT `supplier_invoices_ibfk_1` FOREIGN KEY (`rr_number`) REFERENCES `receiving_reports` (`rr_number`),
  CONSTRAINT `supplier_invoices_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `supplier_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_invoices`
--

LOCK TABLES `supplier_invoices` WRITE;
/*!40000 ALTER TABLE `supplier_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `payment_terms_days` int(11) DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (7,'V INC.','Quezon City, Metro Manila','Marie Cris S. Nini','09178458198',NULL,'Net 30',NULL,0.00,1,'2026-09-16 02:51:49'),(8,'Alpha Distributors','Cebu City, Cebu','Ramon Ty','09228841122',NULL,'COD',NULL,0.00,1,'2026-09-16 02:51:49'),(9,'TechSource Philippines','Davao City, Davao del Sur','Grace Villamor','09173321567',NULL,'Net 15',NULL,0.00,1,'2026-09-16 02:51:49');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_module_access`
--

DROP TABLE IF EXISTS `user_module_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_module_access`
--

LOCK TABLES `user_module_access` WRITE;
/*!40000 ALTER TABLE `user_module_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_module_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$0w832hH3EFlb4KMRhAW6zOnCB9MVpvgCg8nP54Wkc/.mVdev4LG/u','System Administrator','System',NULL,'Administrator',NULL,NULL,'super_admin',NULL,'2026-09-26 14:40:59',NULL,1,'2026-09-16 00:21:51'),(13,'mlb.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Rosemarie Tan','Rosemarie',NULL,'Tan',NULL,1,'branch_admin','Branch Management','2026-09-16 13:11:20',NULL,1,'2026-09-16 02:52:39'),(14,'mlb.staff','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Jerome Dagoc','Jerome',NULL,'Dagoc',NULL,1,'branch_user','Sales','2026-09-16 10:55:26',NULL,1,'2026-09-16 02:52:39'),(15,'mrm.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Kevin Salvador','Kevin',NULL,'Salvador',NULL,2,'branch_admin','Branch Management','2026-09-16 10:53:59',NULL,1,'2026-09-16 02:52:39'),(16,'val.admin','$2y$10$wEdLZi//XnMAmVsEF0kJ8OLXhz/zlRD5eXV3NgCB8RZMbJtn0Xmb2','Angela Bacus','Angela',NULL,'Bacus',NULL,5,'branch_admin','Branch Management','2026-09-16 10:55:16',NULL,1,'2026-09-16 02:52:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_locations`
--

DROP TABLE IF EXISTS `warehouse_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `grid_row` int(11) NOT NULL DEFAULT 1,
  `grid_col` int(11) NOT NULL DEFAULT 1,
  `capacity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `loc_branch_code` (`branch_id`,`code`),
  KEY `idx_wl_branch` (`branch_id`),
  CONSTRAINT `warehouse_locations_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_locations`
--

LOCK TABLES `warehouse_locations` WRITE;
/*!40000 ALTER TABLE `warehouse_locations` DISABLE KEYS */;
INSERT INTO `warehouse_locations` VALUES (3,1,'A-01','Aisle A, Rack 1',1,1,20,1,'2026-09-19 00:47:30'),(4,2,'A-01','Aisle A, Rack 1',1,1,20,1,'2026-09-19 00:47:30'),(5,3,'A-01','Aisle A, Rack 1',1,1,20,1,'2026-09-19 00:47:30'),(6,4,'A-01','Aisle A, Rack 1',1,1,20,1,'2026-09-19 00:47:30'),(7,5,'A-01','Aisle A, Rack 1',1,1,20,1,'2026-09-19 00:47:30'),(8,1,'A-02','Aisle A, Rack 2',1,2,20,1,'2026-09-19 00:47:30'),(9,2,'A-02','Aisle A, Rack 2',1,2,20,1,'2026-09-19 00:47:30'),(10,3,'A-02','Aisle A, Rack 2',1,2,20,1,'2026-09-19 00:47:30'),(11,4,'A-02','Aisle A, Rack 2',1,2,20,1,'2026-09-19 00:47:30'),(12,5,'A-02','Aisle A, Rack 2',1,2,20,1,'2026-09-19 00:47:30'),(13,1,'A-03','Aisle A, Rack 3',1,3,20,1,'2026-09-19 00:47:30'),(14,2,'A-03','Aisle A, Rack 3',1,3,20,1,'2026-09-19 00:47:30'),(15,3,'A-03','Aisle A, Rack 3',1,3,20,1,'2026-09-19 00:47:30'),(16,4,'A-03','Aisle A, Rack 3',1,3,20,1,'2026-09-19 00:47:30'),(17,5,'A-03','Aisle A, Rack 3',1,3,20,1,'2026-09-19 00:47:30'),(18,1,'A-04','Aisle A, Rack 4',1,4,20,1,'2026-09-19 00:47:30'),(19,2,'A-04','Aisle A, Rack 4',1,4,20,1,'2026-09-19 00:47:30'),(20,3,'A-04','Aisle A, Rack 4',1,4,20,1,'2026-09-19 00:47:30'),(21,4,'A-04','Aisle A, Rack 4',1,4,20,1,'2026-09-19 00:47:30'),(22,5,'A-04','Aisle A, Rack 4',1,4,20,1,'2026-09-19 00:47:30'),(23,1,'B-01','Aisle B, Rack 1',2,1,NULL,1,'2026-09-19 00:47:30'),(24,2,'B-01','Aisle B, Rack 1',2,1,NULL,1,'2026-09-19 00:47:30'),(25,3,'B-01','Aisle B, Rack 1',2,1,NULL,1,'2026-09-19 00:47:30'),(26,4,'B-01','Aisle B, Rack 1',2,1,NULL,1,'2026-09-19 00:47:30'),(27,5,'B-01','Aisle B, Rack 1',2,1,NULL,1,'2026-09-19 00:47:30'),(28,1,'B-02','Aisle B, Rack 2',2,2,NULL,1,'2026-09-19 00:47:30'),(29,2,'B-02','Aisle B, Rack 2',2,2,NULL,1,'2026-09-19 00:47:30'),(30,3,'B-02','Aisle B, Rack 2',2,2,NULL,1,'2026-09-19 00:47:30'),(31,4,'B-02','Aisle B, Rack 2',2,2,NULL,1,'2026-09-19 00:47:30'),(32,5,'B-02','Aisle B, Rack 2',2,2,NULL,1,'2026-09-19 00:47:30'),(33,1,'B-03','Aisle B, Rack 3',2,3,NULL,1,'2026-09-19 00:47:30'),(34,2,'B-03','Aisle B, Rack 3',2,3,NULL,1,'2026-09-19 00:47:30'),(35,3,'B-03','Aisle B, Rack 3',2,3,NULL,1,'2026-09-19 00:47:30'),(36,4,'B-03','Aisle B, Rack 3',2,3,NULL,1,'2026-09-19 00:47:30'),(37,5,'B-03','Aisle B, Rack 3',2,3,NULL,1,'2026-09-19 00:47:30'),(38,1,'B-04','Aisle B, Rack 4',2,4,NULL,1,'2026-09-19 00:47:30'),(39,2,'B-04','Aisle B, Rack 4',2,4,NULL,1,'2026-09-19 00:47:30'),(40,3,'B-04','Aisle B, Rack 4',2,4,NULL,1,'2026-09-19 00:47:30'),(41,4,'B-04','Aisle B, Rack 4',2,4,NULL,1,'2026-09-19 00:47:30'),(42,5,'B-04','Aisle B, Rack 4',2,4,NULL,1,'2026-09-19 00:47:30');
/*!40000 ALTER TABLE `warehouse_locations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-26 16:19:11
