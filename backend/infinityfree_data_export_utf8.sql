-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: attendify_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT  IGNORE INTO `attendance` VALUES (34,9,1,2,'2026-03-10',NULL,'unique_code','present','2026-03-09 20:44:55','2026-03-09 20:44:55','2026-03-09 20:44:55'),(35,9,4,2,'2026-03-10',NULL,'unique_code','present','2026-03-09 20:55:58','2026-03-09 20:55:58','2026-03-09 20:55:58'),(36,9,3,2,'2026-03-10',NULL,'unique_code','present','2026-03-09 21:13:12','2026-03-09 21:13:12','2026-03-09 21:13:12'),(37,9,5,2,'2026-03-10',NULL,'unique_code','present','2026-03-09 21:18:04','2026-03-09 21:18:04','2026-03-09 21:18:04'),(38,7,5,2,'2026-03-10',NULL,'unique_code','present','2026-03-09 21:18:51','2026-03-09 21:18:51','2026-03-09 21:18:51'),(59,9,2,2,'2026-03-10',NULL,'qr','present','2026-03-09 21:39:31','2026-03-09 21:39:31','2026-03-09 21:39:31'),(60,5,3,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 03:35:36','2026-03-10 03:35:36','2026-03-10 03:35:36'),(61,7,3,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 03:36:06','2026-03-10 03:36:06','2026-03-10 03:36:06'),(62,7,7,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 03:41:31','2026-03-10 03:41:31','2026-03-10 03:41:31'),(63,5,7,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 03:41:41','2026-03-10 03:41:41','2026-03-10 03:41:41'),(64,9,7,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 03:42:32','2026-03-10 03:42:32','2026-03-10 03:42:32'),(65,9,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:38:45','2026-03-10 16:38:45','2026-03-10 16:38:45'),(66,5,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:38:58','2026-03-10 16:38:58','2026-03-10 16:38:58'),(67,7,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:39:22','2026-03-10 16:39:22','2026-03-10 16:39:22'),(68,6,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:39:52','2026-03-10 16:39:52','2026-03-10 16:39:52'),(69,8,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:40:37','2026-03-10 16:40:37','2026-03-10 16:40:37'),(70,19,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:41:03','2026-03-10 16:41:03','2026-03-10 16:41:03'),(71,20,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:41:33','2026-03-10 16:41:33','2026-03-10 16:41:33'),(72,21,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:42:23','2026-03-10 16:42:23','2026-03-10 16:42:23'),(73,22,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:43:00','2026-03-10 16:43:00','2026-03-10 16:43:00'),(74,23,8,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:43:26','2026-03-10 16:43:26','2026-03-10 16:43:26'),(75,26,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 16:59:56','2026-03-10 16:59:56','2026-03-10 16:59:56'),(76,9,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:00:02','2026-03-10 17:00:02','2026-03-10 17:00:02'),(77,7,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:00:37','2026-03-10 17:00:37','2026-03-10 17:00:37'),(78,5,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:01:04','2026-03-10 17:01:04','2026-03-10 17:01:04'),(79,6,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:01:25','2026-03-10 17:01:25','2026-03-10 17:01:25'),(80,8,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:01:52','2026-03-10 17:01:52','2026-03-10 17:01:52'),(81,18,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:02:19','2026-03-10 17:02:19','2026-03-10 17:02:19'),(82,20,6,2,'2026-03-10',NULL,'unique_code','present','2026-03-10 17:03:26','2026-03-10 17:03:26','2026-03-10 17:03:26'),(83,5,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:43:05','2026-03-11 08:43:05','2026-03-11 08:43:05'),(84,9,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:43:14','2026-03-11 08:43:14','2026-03-11 08:43:14'),(85,6,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:43:44','2026-03-11 08:43:44','2026-03-11 08:43:44'),(86,7,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:44:08','2026-03-11 08:44:08','2026-03-11 08:44:08'),(87,8,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:44:40','2026-03-11 08:44:40','2026-03-11 08:44:40'),(88,18,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:45:24','2026-03-11 08:45:24','2026-03-11 08:45:24'),(89,19,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:45:56','2026-03-11 08:45:56','2026-03-11 08:45:56'),(90,20,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:46:28','2026-03-11 08:46:28','2026-03-11 08:46:28'),(91,21,2,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 08:46:51','2026-03-11 08:46:51','2026-03-11 08:46:51'),(92,9,3,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 09:23:04','2026-03-11 09:23:04','2026-03-11 09:23:04'),(93,7,3,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 09:23:26','2026-03-11 09:23:26','2026-03-11 09:23:26'),(94,18,3,2,'2026-03-11',NULL,'manual','present','2026-03-11 13:47:07','2026-03-11 09:24:06','2026-03-11 13:47:07'),(95,5,3,2,'2026-03-11',NULL,'unique_code','present','2026-03-11 09:27:37','2026-03-11 09:27:37','2026-03-11 09:27:37'),(96,18,2,2,'2026-03-10',NULL,'manual','present','2026-03-11 13:40:03','2026-03-11 13:40:03','2026-03-11 13:40:03'),(97,18,3,2,'2002-02-02',NULL,'manual','present','2026-03-11 13:44:13','2026-03-11 13:43:58','2026-03-11 13:44:13'),(98,18,3,2,'2026-03-10',NULL,'manual','present','2026-03-11 13:47:00','2026-03-11 13:44:48','2026-03-11 13:47:00'),(99,18,3,2,'2026-03-09',NULL,'manual','absent','2026-03-11 13:46:52','2026-03-11 13:46:52','2026-03-11 13:46:52'),(100,25,5,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:00:08','2026-03-11 15:00:08','2026-03-11 15:00:08'),(101,25,3,2,'2026-03-11',NULL,'manual','present','2026-03-11 15:00:47','2026-03-11 15:00:47','2026-03-11 15:00:47'),(102,25,3,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:00:55','2026-03-11 15:00:55','2026-03-11 15:00:55'),(103,21,1,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:11:41','2026-03-11 15:11:41','2026-03-11 15:11:41'),(104,21,7,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:11:45','2026-03-11 15:11:45','2026-03-11 15:11:45'),(105,21,6,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:11:52','2026-03-11 15:11:52','2026-03-11 15:11:52'),(106,21,3,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:12:02','2026-03-11 15:11:59','2026-03-11 15:12:02'),(107,21,3,2,'2026-03-11',NULL,'manual','present','2026-03-11 15:12:14','2026-03-11 15:12:11','2026-03-11 15:12:14'),(108,21,5,2,'2026-03-10',NULL,'manual','present','2026-03-11 15:12:27','2026-03-11 15:12:27','2026-03-11 15:12:27'),(109,9,7,2,'2026-03-11',NULL,'qr','present','2026-03-11 15:38:01','2026-03-11 15:38:01','2026-03-11 15:38:01'),(110,18,7,2,'2026-03-11',NULL,'manual','present','2026-03-11 19:12:33','2026-03-11 15:38:19','2026-03-11 19:12:33'),(111,21,7,2,'2026-03-11',NULL,'qr','present','2026-03-11 15:38:30','2026-03-11 15:38:30','2026-03-11 15:38:30'),(112,9,4,2,'2026-03-11',NULL,'qr','present','2026-03-11 15:56:02','2026-03-11 15:56:02','2026-03-11 15:56:02'),(113,18,4,2,'2026-03-11',NULL,'qr','present','2026-03-11 15:56:12','2026-03-11 15:56:12','2026-03-11 15:56:12'),(114,21,4,2,'2026-03-11',NULL,'qr','present','2026-03-11 15:56:22','2026-03-11 15:56:22','2026-03-11 15:56:22'),(115,9,1,2,'2026-03-11',NULL,'qr','present','2026-03-11 17:04:23','2026-03-11 17:04:23','2026-03-11 17:04:23'),(116,18,1,2,'2026-03-11',NULL,'qr','present','2026-03-11 17:04:40','2026-03-11 17:04:40','2026-03-11 17:04:40'),(117,21,1,2,'2026-03-11',NULL,'qr','present','2026-03-11 17:04:51','2026-03-11 17:04:51','2026-03-11 17:04:51'),(118,18,1,2,'2026-03-12',NULL,'unique_code','present','2026-03-12 04:33:17','2026-03-12 04:33:17','2026-03-12 04:33:17'),(119,18,7,2,'2026-03-12',NULL,'manual','present','2026-03-12 04:37:59','2026-03-12 04:34:10','2026-03-12 04:37:59'),(120,25,7,2,'2026-03-12',NULL,'unique_code','present','2026-03-12 04:34:19','2026-03-12 04:34:19','2026-03-12 04:34:19'),(121,21,3,2,'2026-03-12',NULL,'qr','present','2026-03-12 04:36:20','2026-03-12 04:36:20','2026-03-12 04:36:20'),(122,18,3,2,'2026-03-12',NULL,'qr','present','2026-03-12 04:36:29','2026-03-12 04:36:29','2026-03-12 04:36:29');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `attendance_codes`
--

LOCK TABLES `attendance_codes` WRITE;
/*!40000 ALTER TABLE `attendance_codes` DISABLE KEYS */;
INSERT  IGNORE INTO `attendance_codes` VALUES (1,'87WY2FD',10,'C Programming','2026-03-10 02:09:48','2026-03-10 02:14:48'),(2,'S25VGJY',2,'C Programming','2026-03-10 02:14:48','2026-03-10 02:19:48'),(3,'GCWECWG',2,'C Programming','2026-03-10 02:15:18','2026-03-10 02:20:18'),(4,'85KJ7HT',2,'C Programming','2026-03-10 02:23:00','2026-03-10 02:28:00'),(5,'92LK2FB',2,'C Programming','2026-03-10 02:23:25','2026-03-10 02:28:25'),(6,'WJ2Y69X',2,'PHP','2026-03-10 02:25:50','2026-03-10 02:30:50'),(7,'TL35U3U',2,'C Programming','2026-03-10 02:36:22','2026-03-10 02:41:22'),(8,'UFWUXH9',2,'Python Programming','2026-03-10 02:42:01','2026-03-10 02:47:01'),(9,'KE3ASPR',2,'Python Programming','2026-03-10 02:45:24','2026-03-10 02:50:24'),(10,'BD5F6JY',2,'SQL with Oracle','2026-03-10 02:47:49','2026-03-10 02:52:49'),(11,'Y2M2VHX',2,'Python Programming','2026-03-10 08:57:03','2026-03-10 09:02:03'),(12,'9MNGMER',2,'Python Programming','2026-03-10 09:04:18','2026-03-10 09:09:18'),(13,'KTWGHQ6',2,'Python Programming','2026-03-10 09:07:08','2026-03-10 09:12:08'),(14,'HEFNGAD',2,'SQL with Oracle','2026-03-10 09:10:12','2026-03-10 09:15:12'),(15,'7JK2FME',2,'Cloud Computing','2026-03-10 09:11:13','2026-03-10 09:16:13'),(16,'XGDWESG',2,'Cloud Computing','2026-03-10 09:11:17','2026-03-10 09:16:17'),(17,'6HPKH84',2,'Digital Marketing','2026-03-10 22:08:33','2026-03-10 22:13:33'),(18,'DJ3QVJK',2,'E Commerce','2026-03-10 22:29:44','2026-03-10 22:34:44'),(19,'NAQ2ZAL',2,'Core Java','2026-03-11 14:12:51','2026-03-11 14:17:51'),(20,'AQNGH9W',2,'Python Programming','2026-03-11 14:52:45','2026-03-11 14:57:45'),(21,'RKGZEZP',2,'C Programming','2026-03-12 10:03:02','2026-03-12 10:08:02'),(22,'M8ZHHH2',2,'C Programming','2026-03-12 10:03:38','2026-03-12 10:08:38'),(23,'YVJ9XC2',2,'Cloud Computing','2026-03-12 10:03:47','2026-03-12 10:08:47');
/*!40000 ALTER TABLE `attendance_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `attendance_proofs`
--

LOCK TABLES `attendance_proofs` WRITE;
/*!40000 ALTER TABLE `attendance_proofs` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_proofs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `attendance_sessions`
--

LOCK TABLES `attendance_sessions` WRITE;
/*!40000 ALTER TABLE `attendance_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `event_attendance`
--

LOCK TABLES `event_attendance` WRITE;
/*!40000 ALTER TABLE `event_attendance` DISABLE KEYS */;
INSERT  IGNORE INTO `event_attendance` VALUES (1,1,9,'qr','2026-03-09 21:54:44'),(4,2,7,'qr','2026-03-09 22:00:55'),(5,3,7,'qr','2026-03-09 22:13:06'),(6,4,9,'unique_code','2026-03-09 22:18:11'),(7,4,7,'unique_code','2026-03-09 22:19:09'),(8,4,5,'unique_code','2026-03-09 22:19:56'),(9,15,9,'unique_code','2026-03-09 22:31:17'),(10,16,21,'qr','2026-03-11 17:11:20'),(11,16,18,'qr','2026-03-11 17:11:20'),(12,16,9,'qr','2026-03-11 17:11:20'),(13,18,9,'qr','2026-03-11 19:04:25'),(14,18,18,'qr','2026-03-11 19:04:25'),(15,18,21,'qr','2026-03-11 19:04:25'),(16,19,21,'unique_code','2026-03-11 19:05:37'),(17,19,7,'unique_code','2026-03-11 19:05:56'),(18,19,25,'unique_code','2026-03-11 19:06:22'),(19,19,5,'unique_code','2026-03-11 19:06:47'),(20,19,18,'unique_code','2026-03-11 19:07:11'),(21,20,21,'unique_code','2026-03-11 19:19:02'),(22,20,18,'unique_code','2026-03-11 19:19:09');
/*!40000 ALTER TABLE `event_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT  IGNORE INTO `events` VALUES (1,'Hackathon','2026-12-12','12:00:00',2,NULL,NULL,'2026-03-09 21:54:32'),(2,'antaragni','2026-03-10','12:12:00',2,NULL,NULL,'2026-03-09 22:00:38'),(3,'Code Battle','2026-03-09','12:12:00',2,NULL,NULL,'2026-03-09 22:12:14'),(4,'Hackathon 1.o','2026-03-09','12:03:00',2,'9FAD02','2026-03-10 03:50:56','2026-03-09 22:17:56'),(5,'Hackathon 1.o','2026-03-09','12:03:00',2,'168188','2026-03-10 03:54:20','2026-03-09 22:21:20'),(6,'Hackathon 1.o','2026-03-09','12:03:00',2,'B13315','2026-03-10 03:54:29','2026-03-09 22:21:29'),(7,'Hackathon 1.o','2026-03-09','12:03:00',2,'F61ACA','2026-03-10 03:54:33','2026-03-09 22:21:33'),(8,'Hackathon 1.o','2026-03-09','12:03:00',2,'CE9D89','2026-03-10 03:54:34','2026-03-09 22:21:34'),(9,'Hackathon 1.o','2026-03-09','12:03:00',2,'9DEDC4','2026-03-10 03:54:51','2026-03-09 22:21:51'),(10,'movie event','2026-03-08','23:12:00',2,'AAC5C5','2026-03-10 04:03:24','2026-03-09 22:30:24'),(11,'movie event','2026-03-08','23:12:00',2,'6DA97F','2026-03-10 04:03:26','2026-03-09 22:30:26'),(12,'movie event','2026-03-08','23:12:00',2,'459BF9','2026-03-10 04:03:27','2026-03-09 22:30:27'),(13,'movie event','2026-03-08','23:12:00',2,'A74E6D','2026-03-10 04:03:28','2026-03-09 22:30:28'),(14,'movie event','2026-03-08','23:12:00',2,'D20335','2026-03-10 04:03:45','2026-03-09 22:30:45'),(15,'movie event','2026-03-08','23:12:00',2,'9AA519','2026-03-10 04:04:01','2026-03-09 22:31:01'),(16,'Carnival','2026-03-11','12:30:00',2,NULL,NULL,'2026-03-11 17:10:23'),(17,'FootBall Event','2026-03-10','15:30:00',2,NULL,NULL,'2026-03-11 19:00:55'),(18,'FootBall Event','2026-03-10','15:30:00',2,NULL,NULL,'2026-03-11 19:03:27'),(19,'Cricket Event','2026-03-10','15:30:00',2,'37CFDF','2026-03-12 00:38:23','2026-03-11 19:05:23'),(20,'Hackathon 2.o','2026-03-11','15:30:00',2,'118845','2026-03-12 00:51:49','2026-03-11 19:18:49'),(21,'FOOTBALL','2026-03-11','14:03:00',2,NULL,NULL,'2026-03-12 04:38:55');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT  IGNORE INTO `password_reset_tokens` VALUES (6,'dhanashreegame@gmail.com','$2y$10$20BuzoEzxfK7JTAplawcE.VlyBzCsl4I43ChGFQ4wToI6ksKC2dCG','2026-03-10 01:20:14',0,'2026-03-09 19:48:14'),(7,'nileshgale520@gmail.com','$2y$10$NY9aBnYU8DKcXTn4dA81GeIjUerPtMFC3ShZ5MDwjICRY2QOOnlJG','2026-03-10 01:23:03',0,'2026-03-09 19:51:03'),(8,'parinitapaigwar98@gmail.com','$2y$10$qsnVV/c.aiyTaQ36ayC2FuX7dFLzIDURTqckrFkBTty4YpjFKyIg.','2026-03-10 01:26:03',0,'2026-03-09 19:54:03'),(9,'lavparab@gmail.com','$2y$10$oy6pYLUUU4xcZU9rqFsEFOVwYTQi62mGtLeATaesERAH/mozRSiuu','2026-03-10 01:28:14',0,'2026-03-09 19:56:14'),(10,'karankonge26@gmail.com','$2y$10$H2recwHY0byY8vlLKvaldeXd.xHWA1HKcZbLshM2dKaqRlGj9YgV.','2026-03-10 01:31:46',0,'2026-03-09 19:59:46'),(11,'mansh9290@gmail.com','$2y$10$HLg29I2urPA.pVIkrivTke53jzVxLzGPIDNYYk69pGokHkkB5S0s6','2026-03-10 01:50:50',0,'2026-03-09 20:18:50'),(12,'gayatribhoyar18@gmail.com','$2y$10$5NU.T9uzsINv6EVP.m8e8e9SOwSypLFqxNEdljYkE6JEHYygA6uQu','2026-03-10 01:54:24',0,'2026-03-09 20:22:24'),(13,'lokhanderaunak01@gmail.com','$2y$10$FOYytoekSg6vbKpptKBwZukqleM9QGGu6YMjkE47pZ.tzIheFgFKy','2026-03-10 01:57:37',0,'2026-03-09 20:25:37'),(15,'nileshgale025@gmail.com','$2y$10$XoJppYqPFkLj4FjmSj.SauXtQi5GYtF3W.YXnV3du/WFWmkdRUbrG','2026-03-10 02:01:56',0,'2026-03-09 20:29:56'),(16,'nileshgale2616@gmail.com','$2y$10$mKjfhfZ3bD.ysFbacI3m4OfBVAVurbJlskF9dBoiOnlGh7UMibBde','2026-03-10 02:05:27',0,'2026-03-09 20:33:27'),(17,'ritugale2006@gmail.com','$2y$10$f/xqMPfs3mkKm/wld6Sy5ux5GwDaWUJ.gQE5dVw7VVS6YIb2KTHu.','2026-03-10 02:09:01',0,'2026-03-09 20:37:01'),(18,'himanshu1kar18@gmail.com','$2y$10$wuOUYxoQHbslqR1PZd/.Te01LoHAw2LhXrJYyJ2FNlhtu/4MNYQ6K','2026-03-10 11:55:18',0,'2026-03-10 06:23:18'),(19,'ranetanvi0203@gmail.com','$2y$10$srXrKnob/aqoBCUgiCI5oudDb0slc2dG49Y/E7ERkRFUsTkdA.Fm6','2026-03-10 11:59:02',0,'2026-03-10 06:27:02'),(21,'nurulansari4159@gmail.com','$2y$10$2lgMyxD5MwgI9drzeWOQquH3hKyd4EyPu/wyKbSLSC.qHmrU5R1I6','2026-03-10 12:03:48',0,'2026-03-10 06:31:48'),(23,'vallaripankaj@gmail.com','$2y$10$p3JQVwlZ.CXME2S9XWbBXuECLZpyzHLEcpgbwaAKxiGRwWjoeHnEu','2026-03-10 12:08:12',0,'2026-03-10 06:36:12'),(24,'shitalwaikar05@gmail.com','$2y$10$YMhSG/GYf.iSy47vE78waOMBGpPFWNN4cWv2zpkrU.V.2858ckStW','2026-03-10 12:12:34',0,'2026-03-10 06:40:34'),(26,'salonigharat140@gmail.com','$2y$10$wZ.kJllzu7QacAc5uMV5je93QUQFxu.Tza9ywrw.t1kEmqDELLeMS','2026-03-10 12:26:42',0,'2026-03-10 06:54:42'),(27,'Dhanajaygedam6@gmail.com','$2y$10$of3mzImjCbAPI3VW/Ubti.jihAyBy5vgsXUCXOLjiFba1lYvW9Gnu','2026-03-10 14:12:01',0,'2026-03-10 08:40:01'),(28,'vivekmandve3@gmail.com','$2y$10$jk8wqzBujLTJCYfJu3N7cuJjoSDj0ustDchbSc5GHJJGUCfBEl8tm','2026-03-11 17:42:01',0,'2026-03-11 12:10:01'),(29,'mohitkawre7020@gmail.com','$2y$10$HBMEyf0SDnXR1WlvIIxkWObn1oS7EY5QMN2eoYjKYMBzG0uuE5sV6','2026-03-11 20:18:22',1,'2026-03-11 14:46:22'),(30,'thakurgudiya307@gmail.com','$2y$10$b6rl8mDjFLVoRAFq7uFGf.wpP5qVFjtPpjQtF3.xeg7.Ij5MNnLp.','2026-03-11 20:49:53',0,'2026-03-11 15:17:53'),(31,'ninadrane107@gmail.com','$2y$10$OGj8qvfWfBX/9yHjmD9/g.ZyStsWwFfTY5DNGxuCLcGKX8phbUSqG','2026-03-11 22:31:06',0,'2026-03-11 16:59:06');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `student_subjects`
--

LOCK TABLES `student_subjects` WRITE;
/*!40000 ALTER TABLE `student_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT  IGNORE INTO `subjects` VALUES (1,'C Programming','CP101',NULL,4,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(2,'Core Java','CJ201',NULL,4,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(3,'Python Programming','PP301',NULL,4,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(4,'PHP','PHP101',NULL,4,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(5,'SQL with Oracle','SQL201',NULL,4,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(6,'E Commerce','EC301',NULL,3,'Commerce','2026-03-09 19:30:43','2026-03-09 19:30:43'),(7,'Cloud Computing','CC401',NULL,3,'Computer Science','2026-03-09 19:30:43','2026-03-09 19:30:43'),(8,'Digital Marketing','DM201',NULL,3,'Commerce','2026-03-09 19:30:43','2026-03-09 19:30:43');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `teacher_schedules`
--

LOCK TABLES `teacher_schedules` WRITE;
/*!40000 ALTER TABLE `teacher_schedules` DISABLE KEYS */;
INSERT  IGNORE INTO `teacher_schedules` VALUES (3,2,'Monday','10:00:00','11:00:00','C Programming','','Computer Science','2026-03-11 19:45:59','2026-03-11 19:45:59'),(4,2,'Monday','11:00:00','12:00:00','Python Programming','','Computer Science','2026-03-11 19:46:15','2026-03-11 19:46:15'),(5,2,'Tuesday','11:00:00','12:00:00','SQL with Oracle','','Computer Science','2026-03-11 19:46:47','2026-03-11 19:46:47'),(6,2,'Friday','10:00:00','11:00:00','E Commerce','','Computer Science','2026-03-11 19:47:07','2026-03-11 19:47:07'),(11,3,'Tuesday','11:00:00','12:00:00','E Commerce','','Computer Science','2026-03-11 19:58:41','2026-03-11 19:58:41'),(12,3,'Tuesday','12:00:00','13:00:00','Cloud Computing','','Computer Science','2026-03-11 19:58:41','2026-03-11 19:58:41'),(13,3,'Monday','10:00:00','11:00:00','Core Java','','Information Technology','2026-03-11 20:14:46','2026-03-11 20:14:46'),(14,3,'Monday','11:00:00','12:00:00','SQL with Oracle','','Information Technology','2026-03-11 20:14:46','2026-03-11 20:14:46'),(15,3,'Monday','12:00:00','13:00:00','Digital Marketing','','Information Technology','2026-03-11 20:14:46','2026-03-11 20:14:46'),(16,3,'Friday','10:00:00','11:00:00','C Programming','','Information Technology','2026-03-11 20:16:00','2026-03-11 20:16:00'),(17,3,'Friday','11:00:00','12:00:00','PHP','','Information Technology','2026-03-11 20:16:00','2026-03-11 20:16:00'),(18,3,'Friday','12:00:00','13:00:00','SQL with Oracle','','Information Technology','2026-03-11 20:16:00','2026-03-11 20:16:00'),(19,2,'Wednesday','10:00:00','11:00:00','C Programming','','Computer Science','2026-03-12 04:40:27','2026-03-12 04:40:27'),(20,2,'Wednesday','11:00:00','12:00:00','PHP','','Computer Science','2026-03-12 04:40:27','2026-03-12 04:40:27'),(21,2,'Wednesday','12:00:00','13:00:00','SQL with Oracle','','Computer Science','2026-03-12 04:40:27','2026-03-12 04:40:27'),(23,2,'Saturday','11:00:00','12:00:00','PHP','','Computer Science','2026-03-13 16:44:19','2026-03-13 16:44:19'),(24,2,'Saturday','12:00:00','13:00:00','SQL with Oracle','','Computer Science','2026-03-13 16:44:19','2026-03-13 16:44:19'),(25,2,'Saturday','10:00:00','11:00:00','Core Java','','Information Technology','2026-03-13 16:50:42','2026-03-13 16:50:42');
/*!40000 ALTER TABLE `teacher_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `teacher_subjects`
--

LOCK TABLES `teacher_subjects` WRITE;
/*!40000 ALTER TABLE `teacher_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT  IGNORE INTO `users` VALUES (1,'dhanashree.game89','dhanashreegame@gmail.com','$2y$10$3Jjez8IQRq1xGTLbYvUyR.haHA5apeTidB8uSgfM0.Srae6jj6g56','Dhanashree Game','ADMIN2024001',NULL,NULL,'admin','','','9834362431','uploads/photos/photo_69af244d66478.jpeg','2005-10-19','2026-03-09 19:49:33','2026-03-09 20:31:59',1),(2,'nilesh.gale99','nileshgale520@gmail.com','$2y$10$YvUWTxepZbop17t2D4CYD.XItr/UYGvPYSjsUE0T9sceyJlFj3Dwq','Nilesh Gale','TEA2024001',NULL,NULL,'teacher','Computer Science','','9373352046','uploads/photos/photo_69af24ee2b31b.jpg','2004-09-16','2026-03-09 19:52:14','2026-03-09 20:32:03',1),(3,'parinita.paigwar83','parinitapaigwar98@gmail.com','$2y$10$T4SZK2cA9JssMnjnGHGrY.iMStKffjkz7RGNN20tVdEy340DkwKiS','Parinita paigwar','TEA2024002',NULL,NULL,'teacher','Information Technology','','9834968017','uploads/photos/photo_69af25ad6e80a.jpeg','2005-10-18','2026-03-09 19:55:25','2026-03-09 20:32:06',1),(4,'lav.parab77','lavparab@gmail.com','$2y$10$zKoGzyanIsJR9S.xX.8Wn.tJKseJXLGN6CNtEK/V8ijVwNJnrw21G','Lav parab','TEA2024003',NULL,NULL,'teacher','Electronics','','7507830937','uploads/photos/photo_69af269b635f5.jpeg','2005-01-11','2026-03-09 19:59:23','2026-03-09 20:32:12',1),(5,'karan.konge83','karankonge26@gmail.com','$2y$10$GiiDtDnpQ7mp8d9sq2zpAeozGgGMTUaT7YWQV2fJI9yDxQyqgdi4W','Karan Konge','SEE2004001',NULL,'QR2004001','student','Computer Science','B.Tech','9309466315','uploads/photos/photo_69af274f005c6.jpeg','2005-09-26','2026-03-09 20:02:23','2026-03-09 20:32:18',1),(6,'ansh.mishra70','mansh9290@gmail.com','$2y$10$V/DwjGgBhFbHP9sGeHC1fOCjOKKeRDAK6FXMiiqH1s70Ix6zwHyJq','Ansh Mishra','SEE2004002',NULL,'QR2004002','student','Computer Science','B.Tech','8856035174','uploads/photos/photo_69af2bcbe6b78.jpeg','2005-05-18','2026-03-09 20:21:32','2026-03-09 20:32:26',1),(7,'gayatri.bhoyar77','gayatribhoyar18@gmail.com','$2y$10$S.D/RmeK2iCOQLjzcbUcseV.ztybRkdATGVwELVPb6ONdleIAHFPa','Gayatri Bhoyar','SEE2004003',NULL,'QR2004003','student','Computer Science','B.Tech','8208833252','uploads/photos/photo_69af2c7f32d08.jpeg','2005-01-25','2026-03-09 20:24:31','2026-03-09 20:32:34',1),(8,'raunak.lokhande43','lokhanderaunak01@gmail.com','$2y$10$0bc6NDd/FE9gxt4s.qCTH.Qvpmm6evtszTSMiysgQcj2bq6clkYv6','Raunak Lokhande','SEE2004004',NULL,'QR2004004','student','Information Technology','B.Tech','9130796656','uploads/photos/photo_69af2d0864c1c.jpg','2005-01-01','2026-03-09 20:26:48','2026-03-09 20:32:40',1),(9,'ayesha.ansari71','nileshgale025@gmail.com','$2y$10$yukzBYHLVu3TU5bOqo2L8.dYO4v5VW3RSZA5IW3jzhdpbajxiWKy.','Ayesha Ansari','SEE2004005',NULL,'QR2004005','student','Information Technology','B.Tech','8956632827','uploads/photos/photo_69af2e1776933.jpg','2002-04-26','2026-03-09 20:31:19','2026-03-09 20:32:46',1),(18,'gauri.kolhe48','nileshgale2616@gmail.com','$2y$10$GnBrgLSOZLSpQTjw02Ux3uMH1oPWO8CapF5o9b8Spuhm5UTZXaCwq','Gauri Kolhe','SEE2004006',NULL,'QR2004006','student','Information Technology','B.Tech','8080062384','uploads/photos/photo_69af2f41b3c15.jpeg','2004-09-17','2026-03-09 20:36:17','2026-03-09 20:36:17',1),(19,'harshal.parate16','ritugale2006@gmail.com','$2y$10$2aRnnHQ/kap5Y.NiF48TYubX7EpcgAh1yurECUVb9L7tXWGZLhzRG','Harshal Parate','SEE2004007',NULL,'QR2004007','student','Information Technology','B.Tech','9356918198','uploads/photos/photo_69af2fbea2f7a.jpg','2004-09-10','2026-03-09 20:38:22','2026-03-09 20:38:22',1),(20,'himanshu.wankar88','himanshu1kar18@gmail.com','$2y$10$FR0nELyoI48eF8EkQAXyI.M1TAihJ3GKEvSsiagZA3MHvPVdaKXOy','Himanshu Wankar','SEE2004008',NULL,'QR2004008','student','Computer Science','B.Tech','9158802425','uploads/photos/photo_69afb9a46d3d1.jpeg','2005-04-18','2026-03-10 06:26:44','2026-03-10 06:26:44',1),(21,'tanvi.rane65','ranetanvi0203@gmail.com','$2y$10$1SBJmhuaTaRWU9GS4SIdeeh69ZqsMMud4PZtONiab/hRTd4xRQ4jC','Tanvi Rane','SEE2004009',NULL,'QR2004009','student','Computer Science','B.Tech','9579944170','uploads/photos/photo_69afba3267bd6.jpeg','2005-03-02','2026-03-10 06:29:06','2026-03-10 06:29:06',1),(22,'nurul.ansari54','nurulansari4159@gmail.com','$2y$10$71VgsRwIuY6zayZaEs.51.RmHlkpsoVt9wVoKTYKvjJj/8.Ur8VlK','Nurul Ansari','SEE2004010',NULL,'QR2004010','student','Information Technology','B.Tech','9579658599','uploads/photos/photo_69afbb38daf36.jpg','2004-08-04','2026-03-10 06:33:28','2026-03-10 06:33:28',1),(23,'vallari.patil50','vallaripankaj@gmail.com','$2y$10$Qwdx2IHSGRoac1I9.Wn7B.mo.GIVpaDIZRMfBKL0X2q1pljuyfihC','Vallari Patil','SEE2004011',NULL,'QR2004011','student','Information Technology','B.Tech','8830950926','uploads/photos/photo_69afbcaf9fd92.jpg','2005-09-27','2026-03-10 06:39:43','2026-03-10 06:39:43',1),(24,'shital.waikar79','shitalwaikar05@gmail.com','$2y$10$xlfld2FfFvYRHQ3TlkhYJeH0OfdZfBDvclhLn4P9Rfrqp2i.VvPu2','Shital Waikar','SEE2004012',NULL,'QR2004012','student','Information Technology','B.Tech','9226055335','uploads/photos/photo_69afbd88a77ab.jpeg','2005-10-11','2026-03-10 06:43:20','2026-03-10 06:43:20',1),(25,'mohit.kawre74','mohitkawre7020@gmail.com','$2y$10$ZUM6cMb11.xZCFswlsWvTOzhCGb08BMrZoANFny2Vi.ECmVGnVUB6','Mohit Kawre','SEE2004013',NULL,'QR2004013','student','Computer Science','MCA','7058112896','uploads/photos/photo_69afbed617362.jpeg','2007-04-20','2026-03-10 06:48:54','2026-03-11 14:47:42',1),(26,'saloni.gharat33','salonigharat140@gmail.com','$2y$10$H3NXX6gBG3mAY.gb7GbN7ucrlzNmUMS20e854P20BzfzfwpJ/Nh92','Saloni Gharat','SEE2004014',NULL,'QR2004014','student','Information Technology','B.Tech','7588996621','uploads/photos/photo_69afd69495030.jpg','2005-12-12','2026-03-10 08:30:12','2026-03-10 08:30:12',1),(27,'vivek.mandve44','vivekmandve3@gmail.com','$2y$10$SSxkRoi6Yxmc2xxS9zrDxO64MncIfKfX50iAgFeDYem4.8E/RHp3.','Vivek Mandve','SEE2004015',NULL,'QR2004015','student','Computer Science','B.Tech','8799915742','uploads/photos/photo_69b15c98308aa.jpeg','2004-11-13','2026-03-11 12:14:16','2026-03-11 12:14:16',1),(28,'shruti.thakre36','thakurgudiya307@gmail.com','$2y$10$bZ6SL0Q1276bl6I3ete8l.qobHwH39T/AypaKeHLC932qP2DCnafy','Shruti Thakre','SEE2004016',NULL,'QR2004016','student','Computer Science','B.Tech','9359433253','uploads/photos/photo_69b18884446f2.png','2005-02-08','2026-03-11 15:21:40','2026-03-11 15:21:40',1),(29,'ninad.rane19','ninadrane107@gmail.com','$2y$10$PEEumA6QtYqRJvnPza2iMOazjCEQRjiKIERvSAe496rvJ9NRJA0/6','Ninad Rane','SEE2004017',NULL,'QR2004017','student','Computer Science','B.Tech','8010933782','uploads/photos/photo_69b19ff60872e.jpeg','2004-02-14','2026-03-11 17:01:42','2026-03-11 17:02:23',1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-15 20:50:47
