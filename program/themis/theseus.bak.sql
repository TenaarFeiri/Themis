-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: themis
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `db_err_logs`
--

DROP TABLE IF EXISTS `db_err_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `db_err_logs` (
  `log_num` int NOT NULL AUTO_INCREMENT,
  `log_text` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full error message.',
  `log_source` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Where did the error happen?',
  `log_triggered_by` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Who or what triggered it?',
  PRIMARY KEY (`log_num`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `db_err_logs`
--

LOCK TABLES `db_err_logs` WRITE;
/*!40000 ALTER TABLE `db_err_logs` DISABLE KEYS */;
INSERT INTO `db_err_logs` VALUES (4,'Could not load character data: Character ID is not an integer.','load','0'),(5,'Could not load character data: Character ID is not an integer.','load','Symphicat Resident'),(6,'Could not load character data: Character ID \'t\' is not an integer.','load','Symphicat Resident'),(7,'An unknown or uncategorised error has occurred. Errmsg: %s','load','Symphicat Resident'),(8,'An unknown or uncategorised error has occurred. Errmsg: %s','load','test'),(9,'Could not load character data: Character ID \'t\' is not an integer.','load','Symphicat Resident'),(10,'No titler defined after function. String: array (\n  \'func\' => \'character\',\n  \'method\' => \'save\',\n)','save','Symphicat Resident'),(11,'No name key exists in command array. String: array (\n  \'func\' => \'character\',\n  \'method\' => \'create\',\n)','create','Symphicat Resident'),(12,'Your character name cannot be empty. Press CREATE CHARACTER again to retry.','create','Symphicat Resident');
/*!40000 ALTER TABLE `db_err_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dialog_menus`
--

DROP TABLE IF EXISTS `dialog_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_menus` (
  `dialog_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  UNIQUE KEY `dialogs` (`dialog_name`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dialog_menus`
--

LOCK TABLES `dialog_menus` WRITE;
/*!40000 ALTER TABLE `dialog_menus` DISABLE KEYS */;
INSERT INTO `dialog_menus` VALUES ('CharacterList','CharacterList.json');
/*!40000 ALTER TABLE `dialog_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `launch_tokens`
--

DROP TABLE IF EXISTS `launch_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `launch_tokens` (
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `creator_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pin` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `launch_tokens`
--

LOCK TABLES `launch_tokens` WRITE;
/*!40000 ALTER TABLE `launch_tokens` DISABLE KEYS */;
INSERT INTO `launch_tokens` VALUES ('12345678','555555','2025-08-30 12:49:22','2025-08-30 12:49:22',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `launch_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `player_characters`
--

DROP TABLE IF EXISTS `player_characters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_characters` (
  `character_id` int NOT NULL AUTO_INCREMENT,
  `player_id` int NOT NULL,
  `character_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `character_titler` blob NOT NULL,
  `character_stats` blob,
  `stat_points` int NOT NULL DEFAULT '0',
  `character_options` blob,
  `legacy` int NOT NULL DEFAULT '0' COMMENT '0 if not a legacy character',
  PRIMARY KEY (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `player_characters`
--

LOCK TABLES `player_characters` WRITE;
/*!40000 ALTER TABLE `player_characters` DISABLE KEYS */;
INSERT INTO `player_characters` VALUES (1,1,'Sekhmet',_binary '{\"@invis@\":\"Sekhmet\",\"Daughter of Zerda\\nSpecies:\":\"Lion.\",\"Mood:\":\"Good.\",\"Info:\":\"Babyless!\\nHealthy!\",\"Scent:\":\"Leonine.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Sekhmet\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',51),(2,1,'Ritsika Chaudhri',_binary '{\"@invis@\":\"Ritsika Chaudhri\",\"Species:\":\"Extractum Vulpes.\",\"Mood:\":\"Good!\",\"Info:\":\"Dragonfox; Has a Hindi-like accent.\\nFit!\",\"Scent:\":\"Clean.\",\"Currently:\":\"Peddling bricks.\",\"template\":\"character\",\"0\":\"Ritsika Chaudhri\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',52),(3,1,'Tchala$n, formerly Candy',_binary '{\"@invis@\":\"Tchala$n, formerly Candy\",\"Species:\":\"Coyote.\",\"Mood:\":\"Hyped!\",\"Info:\":\"Yotely-hoo!\\nFit!\",\"Scent:\":\"Yotely.\",\"Currently:\":\"Up to no good.\",\"template\":\"character\",\"0\":\"Tchala$n, formerly Candy\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',56),(4,1,'Kajsa Hafsdraumr',_binary '{\"@invis@\":\"Kajsa Hafsdraumr\",\"Species:\":\"Arctic Fox.\",\"Mood:\":\"Calm.\",\"Info:\":\"Has a Nordic accent.\\nFit & thicc!\",\"Scent:\":\"Foxy.\",\"Currently:\":\"Also engaged!\",\"template\":\"character\",\"0\":\"Kajsa Hafsdraumr\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',60),(5,1,'Bahiti',_binary '{\"@invis@\":\"Bahiti\",\"Species:\":\"Fennec Fox.\",\"Mood:\":\"Calm.\",\"Info:\":\"Thick Qalasian accent.\\nPristine; Pregnant.\",\"Scent:\":\"Clean.\",\"Currently:\":\"Working.\",\"template\":\"character\",\"0\":\"Bahiti\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',70),(6,1,'Xocoyotl NecÄhual',_binary '{\"@invis@\":\"Xocoyotl Nec\\u00c4\\u0081hual\",\"Species:\":\"Coyote.\",\"Mood:\":\"Good.\",\"Info:\":\"Has a very foreign accent!\\nFit.\",\"Scent:\":\"Doggy.\",\"Currently:\":\"Idle.\",\"template\":\"character\",\"0\":\"Xocoyotl Nec\\u00c4\\u0081hual\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',101),(7,1,'Martha Warrick',_binary '{\"@invis@\":\"Martha Warrick\",\"Species:\":\"Human.\",\"Mood:\":\"Calm.\",\"Info:\":\"Definitely not from around here.\\nSturdy.\",\"Scent:\":\"Clean.\",\"Currently:\":\"Out and about.\",\"template\":\"character\",\"0\":\"Martha Warrick\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',112),(8,1,'Sigrid Na\'Varr',_binary '{\"@invis@\":\"Sigrid Na\'Varr\",\"Species:\":\"Vulpes Ramani.\",\"Mood:\":\"Nervous.\",\"Info:\":\"FLOOF!; Has a Qalasian accent.\\nHealthy; Muscled.\",\"Scent:\":\"Clean.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Sigrid Na\'Varr\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"239,255,88\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',113),(9,1,'Ruby al-Nejem',_binary '{\"@invis@\":\"Ruby al-Nejem\",\"the Bug Farmer\\nSpecies:\":\"Fennec Fox.\",\"Mood:\":\"Calm.\",\"Info: Has a faint demonic presence.\\nStatus:\":\"Pregnant.\\nHealthy!\",\"Scent:\":\"Foxy; Sand.\",\"Currently:\":\"On break!\",\"template\":\"character\",\"0\":\"Ruby al-Nejem\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',114),(10,1,'Tyrild Hafsdraumr',_binary '{\"@invis@\":\"Tyrild Hafsdraumr\",\"Species:\":\"Arctic Fox.\",\"Mood:\":\"...\",\"Info:\":\"Never should have come here!\\nFit!\",\"Scent:\":\"Blood, sweat, sex & multiple males (fennecs).\",\"Currently:\":\"Exhausted.\",\"template\":\"character\",\"0\":\"Tyrild Hafsdraumr\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',191),(11,1,'Sera',_binary '{\"@invis@\":\"Sera\",\"Species:\":\"Qalasian Fennec.\",\"Mood:\":\"Calm.\",\"Info:\":\"Thick accent; Head doctor.\\nHealthy; Fit.\",\"Scent:\":\"Clean; Foxy.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Sera\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',207),(12,1,'Arunnoz Tothol',_binary '{\"@invis@\":\"Arunnoz Tothol\",\"<Warlock>\\nSpecies:\":\"Fennec Fox.\",\"Mood:\":\"Good.\",\"Info:\":\"n\\/a\\nHealthy!\",\"Scent:\":\"Male fox.\",\"Currently:\":\"Waiting out the weather.\",\"template\":\"character\",\"0\":\"Arunnoz Tothol\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',364),(13,1,'Maia Aurumcauda',_binary '{\"@invis@\":\"Maia Aurumcauda\",\"Species:\":\"Fennec!\",\"Mood:\":\"Good.\",\"Info:\":\"Governor Solrin\'s wife.\\nHealthy; Stubby.\",\"Scent:\":\"Clean; Foxy.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Maia Aurumcauda\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,255\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',379),(14,1,'Re\'hotpe',_binary '{\"@invis@\":\"Re\'hotpe\",\"<Novice Geomancer>\\nSpecies:\":\"Fennec Fox.\",\"Mood:\":\"Calm.\",\"Info:\":\"Qalasian Guard.\\nHealthy; Big & muscular.\",\"Scent:\":\"Fox musk; Sand.\",\"Currently:\":\"On duty.\",\"template\":\"character\",\"0\":\"Re\'hotpe\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"240,230,140\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',431),(15,1,'Tavaah\'a',_binary '{\"@invis@\":\"Tavaah\'a\",\"Species:\":\"Qitsin!\",\"Mood:\":\"Calm.\",\"Info:\":\"Speaks with an accent (resembling Dutch).\\nHealthy & tall!\",\"Scent:\":\"Clean-ish; Catty.\",\"Currently:\":\"Idle.\",\"template\":\"character\",\"0\":\"Tavaah\'a\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',469),(16,1,'Alphonse Authier',_binary '{\"@invis@\":\"Alphonse Authier\",\"Species:\":\"Fennec Fox.\",\"Mood:\":\"Good!\",\"Info:\":\"Has a slight French accent.\\nHealthy; Lean; Feminine.\",\"Scent:\":\"Clean; Wildflowers.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Alphonse Authier\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',481),(17,1,'Sif',_binary '{\"@invis@\":\"Sif\",\"daughter of Senusnet\\nSpecies:\":\"Qalasian Fennec.\",\"Mood:\":\"Good.\",\"Info:\":\"Has a thick Qalasian accent.\\nHealthy; Pregnant.\",\"Scent:\":\"Lilies.\",\"Currently:\":\"Out and about.\",\"template\":\"character\",\"0\":\"Sif\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',488),(18,1,'Khepri',_binary '{\"@invis@\":\"Khepri\",\"<Property of King Senusnet>\\nSpecies:\":\"Ramau.\",\"Mood:\":\"Calm.\",\"Info:\":\"Has a thick Egyptian accent.\\nRecovering from injury; Damaged R shoulder.\",\"Scent:\":\"Clean; Honey.\",\"Currently:\":\"Engaged.\",\"template\":\"character\",\"0\":\"Khepri\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',522),(19,1,'@invis@\nSmall; Fit.',_binary '{\"@invis@\":\"@invis@\\nSmall; Fit.\",\"Species:\":\"Qalasian Fennec.\",\"Mood:\":\"Calm.\",\"Scent:\":\"Foxy.\",\"Currently:\":\"In town.\",\"template\":\"character\",\"0\":\"@invis@\\nSmall; Fit.\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',532),(20,1,'Braheem',_binary '{\"@invis@\":\"Braheem\",\"Species:\":\"Fennec.\",\"Mood:\":\"Good.\",\"Info:\":\"Qalasian accent.\\nA little chonky, but still fit; Muscled.\",\"Scent:\":\"Dirt & flowers.\",\"Currently:\":\"Out and about.\",\"template\":\"character\",\"0\":\"Braheem\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',584),(21,1,'Shu',_binary '{\"Jiangshi Xue\":\"Shu\",\"Species:\":\"Red Fox!\",\"Mood:\":\"Good.\",\"Info:\":\"Has a Chinese accent.\\nHealthy; Has a thick winter coat!\",\"Scent:\":\"Foxy.\",\"Currently:\":\"Chilling in the desert.\",\"template\":\"character\",\"0\":\"Shu\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',705),(22,1,'My name',_binary '{\"Name:\":\"My name\",\"Species:\":\"My species\",\"Mood:\":\"My mood\",\"Info:\":\"My info\\nMy body\",\"Scent:\":\"My scent\",\"Currently:\":\"My action\",\"template\":\"character\",\"0\":\"My name\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,255\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',2115),(23,1,'Sebek-khu',_binary '{\"@invis@\":\"Sebek-khu\",\"Species:\":\"Fox!\",\"Mood:\":\"Good.\",\"Info:\":\"Has a Qalasian accent.\\nFit!\",\"Scent:\":\"Wet fox.\",\"Currently:\":\"Enjoying the rain.\",\"template\":\"character\",\"0\":\"Sebek-khu\"}',_binary '{\"health\":1,\"strength\":10,\"dexterity\":10,\"constitution\":10,\"magic\":100,\"class\":0,\"template\":\"stats\"}',20,_binary '{\"color\":\"255,255,0\",\"opacity\":\"1.0\",\"attach_point\":\"head\",\"position\":\"<0,0,0>\",\"afk-ooc\":0,\"afk-msg\":\"\",\"ooc-msg\":\"\",\"template\":\"settings\"}',5133);
/*!40000 ALTER TABLE `player_characters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `player_tags`
--

DROP TABLE IF EXISTS `player_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tagname` (`tag`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `player_tags`
--

LOCK TABLES `player_tags` WRITE;
/*!40000 ALTER TABLE `player_tags` DISABLE KEYS */;
INSERT INTO `player_tags` VALUES (1,'Victim','Consents to being victimised by other characters. This can be assaults, mistreatment, etc.'),(2,'Horny','Very receptive of ERP requests.');
/*!40000 ALTER TABLE `player_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `players`
--

DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `player_id` int NOT NULL AUTO_INCREMENT,
  `player_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_created` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_last_online` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_current_character` int NOT NULL DEFAULT '0',
  `player_titler_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_hud_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`),
  UNIQUE KEY `user_uuid_unique` (`player_uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Primary user table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `players`
--

LOCK TABLES `players` WRITE;
/*!40000 ALTER TABLE `players` DISABLE KEYS */;
INSERT INTO `players` VALUES (1,'Symphicat Resident','59ee7fce-5203-4d8c-b4db-12cb50ad2c10','08/24/2025 11:23 AM','08/24/2025 11:23 AM',1,NULL,NULL,0),(2,'Test User 692c7c0f99f5f','test-uuid-692c7c0f99f5e','2025-11-30 17:17:03','2025-11-30 17:17:03',0,NULL,NULL,0),(3,'Test User 692c7c8ecd505','test-uuid-692c7c8ecd503','2025-11-30 17:19:10','2025-11-30 17:19:10',0,NULL,NULL,0),(4,'Test User 692c7d26bedf8','test-uuid-692c7d26bedf7','2025-11-30 17:21:42','2025-11-30 17:21:42',0,NULL,NULL,0),(5,'Test User 692c7fb415bd5','test-uuid-692c7fb415bd3','2025-11-30 17:32:36','2025-11-30 17:32:36',0,NULL,NULL,0);
/*!40000 ALTER TABLE `players` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires` datetime NOT NULL,
  `revoked` tinyint(1) DEFAULT '0',
  `meta` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES (1,'555555','59ee7fce-5203-4d8c-b4db-12cb50ad2c10','2025-08-30 13:28:18','2026-04-20 09:56:31',0,NULL);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-19 12:07:08
