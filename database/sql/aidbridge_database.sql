-- =====================================================================
--  AidBridge — Welfare Aid & Cash Assistance Distribution Management System
--
--  Complete database creation and initial data script.
--
--  Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
--  Generated: 2026-09-04
--
--  Usage:
--      mysql -u root -p < database/sql/aidbridge_database.sql
--
--  Section 1 — schema for all 16 tables
--  Section 2 — initial demo data (users, programmes, applications,
--              documents, disbursements, audit trail)
--
--  Runtime tables (sessions, cache, jobs, personal_access_tokens) are
--  created but intentionally left empty.
-- =====================================================================
-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: aidbridge
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Current Database: `aidbridge`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `aidbridge` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `aidbridge`;

--
-- Table structure for table `aid_programs`
--

DROP TABLE IF EXISTS `aid_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aid_programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('cash_disbursement','voucher','emergency_grant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget_allocated` decimal(15,2) NOT NULL DEFAULT '0.00',
  `budget_remaining` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payout_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `income_threshold` decimal(12,2) NOT NULL DEFAULT '5250.00',
  `min_dependents` tinyint unsigned NOT NULL DEFAULT '0',
  `status` enum('draft','open','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `opens_at` date DEFAULT NULL,
  `closes_at` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aid_programs_slug_unique` (`slug`),
  KEY `aid_programs_created_by_foreign` (`created_by`),
  KEY `aid_programs_type_index` (`type`),
  KEY `aid_programs_status_index` (`status`),
  CONSTRAINT `aid_programs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `aid_program_id` bigint unsigned NOT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `household_income` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dependents_count` tinyint unsigned NOT NULL DEFAULT '0',
  `state` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disaster_victim` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `eligibility_score` smallint unsigned DEFAULT NULL,
  `eligibility_breakdown` json DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `agency_verification` json DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `applications_user_id_aid_program_id_unique` (`user_id`,`aid_program_id`),
  UNIQUE KEY `applications_reference_unique` (`reference`),
  KEY `applications_aid_program_id_foreign` (`aid_program_id`),
  KEY `applications_decided_by_foreign` (`decided_by`),
  KEY `applications_status_aid_program_id_index` (`status`,`aid_program_id`),
  KEY `applications_status_index` (`status`),
  CONSTRAINT `applications_aid_program_id_foreign` FOREIGN KEY (`aid_program_id`) REFERENCES `aid_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correlation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_correlation_id_index` (`correlation_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disbursements`
--

DROP TABLE IF EXISTS `disbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint unsigned NOT NULL,
  `reference_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','disbursed','reconciled','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_channel` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bank_transfer',
  `bank_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `disbursed_at` timestamp NULL DEFAULT NULL,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disbursements_reference_code_unique` (`reference_code`),
  KEY `disbursements_application_id_foreign` (`application_id`),
  KEY `disbursements_approved_by_foreign` (`approved_by`),
  KEY `disbursements_status_index` (`status`),
  CONSTRAINT `disbursements_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disbursements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint unsigned NOT NULL,
  `document_type` enum('nric','income_proof','household_proof','disability_cert','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int unsigned NOT NULL,
  `checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_application_id_foreign` (`application_id`),
  KEY `documents_verified_by_foreign` (`verified_by`),
  KEY `documents_document_type_index` (`document_type`),
  CONSTRAINT `documents_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','beneficiary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beneficiary',
  `nric_encrypted` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_receipts`
--

DROP TABLE IF EXISTS `webhook_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment_gateway',
  `event_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disbursement_id` bigint unsigned DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_receipts_idempotency_key_unique` (`idempotency_key`),
  KEY `webhook_receipts_disbursement_id_foreign` (`disbursement_id`),
  CONSTRAINT `webhook_receipts_disbursement_id_foreign` FOREIGN KEY (`disbursement_id`) REFERENCES `disbursements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-04  6:36:10

-- ============ SECTION 2: INITIAL DATA ============

-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: aidbridge
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Nurul Admin','admin@aidbridge.test',NULL,'$2y$12$sQCnRMmdNss9JYLFciczBu9Q5NfnwV.ADjGc2q/jPAGCtyol5UoGS','admin','eyJpdiI6InIvaWVac1B2UDZ2cmozNzBUN3pUemc9PSIsInZhbHVlIjoiazZ1cGVkSWltOGhNVnFoMzB1TTBzUT09IiwibWFjIjoiMGZhNmY2ZjM4MmVlYzlkZjUwNDU2NDM4ZWE0NzkxMzAxZDk1OGRhYzc4YjljOWQwNTc3MjQyZjNhMTRjNTg4MSIsInRhZyI6IiJ9',NULL,'Kuala Lumpur',0,NULL,'2026-09-03 21:15:46','2026-09-03 21:15:46'),(2,'Siti Nurhaliza binti Rahman','siti@example.test',NULL,'$2y$12$ngKuVJoynZP/4Fp3XXB2YuZsH1xVi/14bW5KSa9nTu/W37ogbfQle','beneficiary','eyJpdiI6ImNlUENwWGV5QkZSMEZ6d1JxSjY1Nnc9PSIsInZhbHVlIjoiVzRTUFNCc21wQ0d6Z21PN2REUG1KUT09IiwibWFjIjoiNzlhNjE0ZDdkOGZlMmEwZTFlM2M1MTMxMWI4MWIyMzc1YzYyMGY0YWFhMjBhZTFmNzYwMTAwNGI4MjVhMzkwZiIsInRhZyI6IiJ9','016-6595987','Selangor',0,NULL,'2026-09-03 21:15:46','2026-09-03 21:15:46'),(3,'Ahmad Faizal bin Osman','ahmad@example.test',NULL,'$2y$12$mU3D2i7R7A9TTMxXcgfTYu/I0oJsUxxYWrWQgQIZFFD1nxLy5WUsy','beneficiary','eyJpdiI6IlVLaW5TRS8yMThBZlNzK3JBVXdWVWc9PSIsInZhbHVlIjoiZzVJNDZXM25yMlhUTkV0bENnT25Edz09IiwibWFjIjoiMDFiYjM2ZDE1ZjI1MDY1OGU1NDVkZDNjZmRmYjJiMGViYTg4OWMwZmE5MGNhNzQwODU4MzZiOWQyZDM5MDRmOCIsInRhZyI6IiJ9','019-6140691','Kelantan',0,NULL,'2026-09-03 21:15:46','2026-09-03 21:15:46'),(4,'Lim Wei Ming','lim@example.test',NULL,'$2y$12$P3bl//frhwM29O0A.5YfaO9yrQQ2TDWUVz4X5ar/h3y0Rdka4U39C','beneficiary','eyJpdiI6IjhvQXFrSTg4b0tMZFRxN0pCb3hEMUE9PSIsInZhbHVlIjoieTVjaE02Zm03RG9YRjZ0YzJVZ3k0UT09IiwibWFjIjoiNThkNTJkZTgwZjgzMDM2ZTFkODk2N2I3MTJlY2NiNDkzN2EwMDZmNzgyMWZkMjllMjU2ZDkyZmFmZjhlNTFhMyIsInRhZyI6IiJ9','018-8586407','Pulau Pinang',1,NULL,'2026-09-03 21:15:46','2026-09-03 21:15:46'),(5,'Kavitha a/p Subramaniam','kavitha@example.test',NULL,'$2y$12$g1LMlzmvduBveVSVlCJJOeVJlmAJWgJP9kvr39WyrjMmbW4BQx6nC','beneficiary','eyJpdiI6Im5ZWjhIQVlxOG5uZkE4OERWejJZaVE9PSIsInZhbHVlIjoieUhaK0N3V1haYW5aQ2NrOXNFdW14UT09IiwibWFjIjoiMWNkN2M1NDJhMmNmNTE4YTU0ZWU4MjRlYmE3YzRjYThlNTFjZDExNjc5NWI4MDZjN2RkODZmZjQzZWFhYzBiMSIsInRhZyI6IiJ9','019-6096135','Johor',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(6,'Mohd Hafiz bin Ismail','hafiz@example.test',NULL,'$2y$12$anFu295Qqa4274E1t.HXCuUJ7kI9jdOLYA/RjEUHcczBJF7nDSLCW','beneficiary','eyJpdiI6ImtOc1MvY0JRc1E2SDhRdlR2eGdIQ3c9PSIsInZhbHVlIjoiR1FZV2ZMUnBmRW5TRkxCL3BpNzRGUT09IiwibWFjIjoiNjg5MjA5N2U0NDIwMzc1MWIzMmNjMjJhYTdiOTVmMTYzODIzYTE4N2I4MGNkY2U5NDM3Y2U4OGVhMDIyYmZiZCIsInRhZyI6IiJ9','011-6301369','Pahang',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(7,'Rosnah binti Yusof','rosnah@example.test',NULL,'$2y$12$PfhZ2.BuWdQ7Yyd6yCDD2uSsAdazRGutXCylsZYfoimSBH11bQLRm','beneficiary','eyJpdiI6IlF1UzJibDhLQlpMdmk4WTR5M0tpdEE9PSIsInZhbHVlIjoic1B6Z245OTByZTRQTG95VXU0UHAwQT09IiwibWFjIjoiNjU5YjA5YTNiZjFlOGFiMTQ2ZDZlNzY1MTY2OTU4ZTNlMjVhYTEzZDQ4ODczNWE1OWEyZTRjZGMwN2RjN2RiYyIsInRhZyI6IiJ9','011-9945401','Sabah',1,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(8,'Tan Chee Keong','tan@example.test',NULL,'$2y$12$TNzpReHOvAkL1dwiIp1sQOq9EL4VPW.eZXCoQFquRUPAsyNSyX2gC','beneficiary','eyJpdiI6IkZkRGQ3TU9OeFc1M3NjVVg1c29WK3c9PSIsInZhbHVlIjoia3piTVdTUVloV09WTXlsWHZ1dDlWQT09IiwibWFjIjoiMDVlMmMzNzdhZDQ0NTc4MzMwMzE3Mzg0YThkY2Q5MDM4ZjQwMjc1ZTQ4N2ExYmI2YjJkOWI0NGE1Mzk5OTRkNyIsInRhZyI6IiJ9','016-4522417','Kuala Lumpur',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(9,'Nurul Ain binti Karim','nurul@example.test',NULL,'$2y$12$YER1au.x35M.q7/mkqbfcOJkLlJ57c.sUZLdMuA6DR20pZu50TVU2','beneficiary','eyJpdiI6ImhneFBiNjZLaXdhcUhSUnI3dHdBWlE9PSIsInZhbHVlIjoidWc4VUVSb2JxZk5rZDBHVWN0R25idz09IiwibWFjIjoiMjQ2NjA5MTdkNDkwM2RjN2U1OWY4MTZiMTFjNzhmOWZmYzkxNDEwMGI1NmEwMzRmZDY4ZGQzMmIzNzFlM2UyMCIsInRhZyI6IiJ9','014-8918003','Perak',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(10,'Anak Jimmy anak Belaja','jimmy@example.test',NULL,'$2y$12$p8k/cdD/y/GpHIGgtQYbUemQgvvr8OKUXmVZViti09DgTcS3B1u7u','beneficiary','eyJpdiI6Ims0bWI5TFlLdHdzU0V2Zno4OWZLaWc9PSIsInZhbHVlIjoiNEY3a0N0Smh0c1Rxc1JzMjl6QTRiQT09IiwibWFjIjoiZTA0MmQ2Mjc2YjJiZDA4MWFmNmU5ZDNhZDA3YjAwYmRkY2ZkMjlmOGVmMDNkOTllMDJlNzc0YjczNGQ4OWM4MCIsInRhZyI6IiJ9','014-3002724','Sarawak',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47'),(11,'Fatimah binti Zakaria','fatimah@example.test',NULL,'$2y$12$NXjcBk7pb8q2s10e.1wMNef/NUF2bJtH4jbA3.E6C/rWWnYDhOyn.','beneficiary','eyJpdiI6IjFlODdnUENkOEdIR3NSb0FDNHBFd0E9PSIsInZhbHVlIjoiTWk4cStyN252eElvYVRjaTZKT1YzUT09IiwibWFjIjoiMDkwZTM3NDM5NTRmY2IxOWZjNDk4YWNlMzRlMTllMDUwOWRiMTE5OWJhOTExYjIxNzhiYzgzNmQ4OTE5M2MyYyIsInRhZyI6IiJ9','017-9466412','Kedah',0,NULL,'2026-09-03 21:15:47','2026-09-03 21:15:47');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Dumping data for table `aid_programs`
--

/*!40000 ALTER TABLE `aid_programs` DISABLE KEYS */;
INSERT INTO `aid_programs` VALUES (1,'Monthly B40 Food Subsidy','monthly-b40-food-subsidy','Recurring cash assistance towards essential food costs for B40 households.','cash_disbursement',250000.00,248050.00,500.00,5250.00,0,'open','2026-08-03','2027-03-03',1,'2026-09-03 21:15:46','2026-09-03 21:15:48'),(2,'Back-to-School Fund','back-to-school-fund','Vouchers for uniforms, books and school supplies for families with school-age children.','voucher',120000.00,119650.00,200.00,5000.00,1,'open','2026-08-03','2026-12-03',1,'2026-09-03 21:15:46','2026-09-03 21:15:48'),(3,'Emergency Flood Relief 2026','emergency-flood-relief-2026','One-off grant for households displaced by the declared monsoon flooding.','emergency_grant',400000.00,397160.00,1000.00,7000.00,0,'open','2026-08-03','2026-11-03',1,'2026-09-03 21:15:46','2026-09-03 21:15:48');
/*!40000 ALTER TABLE `aid_programs` ENABLE KEYS */;

--
-- Dumping data for table `applications`
--

/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
INSERT INTO `applications` VALUES (1,'60d103e3-72d0-46ff-ac76-e6814ea0bd4e',2,1,'approved',2800.00,3,'Selangor',0,NULL,57,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 57, \"reasons\": [\"Household income RM 2,800.00 against an adjusted threshold of RM 6,450.00 (base RM 5,250.00 + 3 dependents).\", \"Income-based need score: 57/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 57, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-06-22 21:15:47','2026-06-27 21:15:47',1,'2026-06-22 21:15:47','2026-09-03 21:15:48'),(2,'720697a7-0dc5-4448-b1e0-d532eaf15d8b',3,1,'approved',3400.00,4,'Kelantan',0,NULL,50,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 50, \"reasons\": [\"Household income RM 3,400.00 against an adjusted threshold of RM 6,850.00 (base RM 5,250.00 + 4 dependents).\", \"Income-based need score: 50/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 50, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-08-25 21:15:48','2026-08-31 21:15:48',1,'2026-08-25 21:15:48','2026-09-03 21:15:48'),(3,'e5f34c5f-a88b-4371-8448-148478671feb',4,1,'approved',4100.00,2,'Pulau Pinang',0,NULL,56,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 32, \"reasons\": [\"Household income RM 4,100.00 against an adjusted threshold of RM 6,050.00 (base RM 5,250.00 + 2 dependents).\", \"Income-based need score: 32/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}, {\"score\": 95, \"reasons\": [\"Household includes a registered person with disability.\", \"Verified disability certificate on file.\", \"Care burden uplift for dependents: +10.\"], \"eligible\": true, \"strategy\": \"disability_support\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 56, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-07-08 21:15:48','2026-07-16 21:15:48',1,'2026-07-08 21:15:48','2026-09-03 21:15:48'),(4,'087c02e0-8e42-4c1d-8b47-774fbddfe7c7',5,2,'approved',3200.00,3,'Johor',0,NULL,48,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 48, \"reasons\": [\"Household income RM 3,200.00 against an adjusted threshold of RM 6,200.00 (base RM 5,000.00 + 3 dependents).\", \"Income-based need score: 48/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 48, \"recommendation\": \"manual_review\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-07-06 21:15:48','2026-07-08 21:15:48',1,'2026-07-06 21:15:48','2026-09-03 21:15:48'),(5,'fa0c3098-3230-4fe5-9e8e-6c4a52438fe9',6,2,'under_review',4800.00,2,'Pahang',0,NULL,17,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 17, \"reasons\": [\"Household income RM 4,800.00 against an adjusted threshold of RM 5,800.00 (base RM 5,000.00 + 2 dependents).\", \"Income-based need score: 17/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 17, \"recommendation\": \"manual_review\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-07-04 21:15:48',NULL,NULL,'2026-07-04 21:15:48','2026-09-03 21:15:48'),(6,'207b7bf0-b827-4603-8887-e8e5c9dc94a9',7,3,'approved',3900.00,5,'Sabah',1,NULL,83,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 57, \"reasons\": [\"Household income RM 3,900.00 against an adjusted threshold of RM 9,000.00 (base RM 7,000.00 + 5 dependents).\", \"Income-based need score: 57/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}, {\"score\": 100, \"reasons\": [\"Household includes a registered person with disability.\", \"Verified disability certificate on file.\", \"Care burden uplift for dependents: +15.\"], \"eligible\": true, \"strategy\": \"disability_support\"}, {\"score\": 100, \"reasons\": [\"Applicant is registered as affected by a declared disaster.\", \"Applying to a dedicated emergency grant programme.\", \"Large displaced household.\"], \"eligible\": true, \"strategy\": \"emergency_relief\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 83, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-08-04 21:15:48','2026-08-12 21:15:48',1,'2026-08-04 21:15:48','2026-09-03 21:15:48'),(7,'36b0a60f-3169-46b5-920f-94d5756c7e44',8,3,'under_review',5600.00,1,'Kuala Lumpur',1,NULL,58,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 24, \"reasons\": [\"Household income RM 5,600.00 against an adjusted threshold of RM 7,400.00 (base RM 7,000.00 + 1 dependents).\", \"Income-based need score: 24/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}, {\"score\": 95, \"reasons\": [\"Applicant is registered as affected by a declared disaster.\", \"Applying to a dedicated emergency grant programme.\"], \"eligible\": true, \"strategy\": \"emergency_relief\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 58, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-08-13 21:15:48',NULL,NULL,'2026-08-13 21:15:48','2026-09-03 21:15:48'),(8,'aa3cf6db-7d35-49d3-9ffa-ed0cbc296ad7',9,1,'rejected',6200.00,1,'Perak',0,'Household income exceeds the B40 threshold for this programme.',0,'{\"eligible\": false, \"threshold\": 50, \"strategies\": [{\"score\": 0, \"reasons\": [\"Household income RM 6,200.00 against an adjusted threshold of RM 5,650.00 (base RM 5,250.00 + 1 dependents).\", \"Income exceeds the B40 threshold for this programme.\"], \"eligible\": false, \"strategy\": \"b40_income\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 0, \"recommendation\": \"reject\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-06-14 21:15:48','2026-06-17 21:15:48',1,'2026-06-14 21:15:48','2026-09-03 21:15:48'),(9,'67d6d98e-6c88-437d-a252-af70cae00353',10,3,'approved',2400.00,4,'Sarawak',1,NULL,85,'{\"eligible\": true, \"threshold\": 50, \"strategies\": [{\"score\": 72, \"reasons\": [\"Household income RM 2,400.00 against an adjusted threshold of RM 8,600.00 (base RM 7,000.00 + 4 dependents).\", \"Income-based need score: 72/100.\"], \"eligible\": true, \"strategy\": \"b40_income\"}, {\"score\": 100, \"reasons\": [\"Applicant is registered as affected by a declared disaster.\", \"Applying to a dedicated emergency grant programme.\", \"Large displaced household.\"], \"eligible\": true, \"strategy\": \"emergency_relief\"}], \"assessed_at\": \"2026-09-03T21:15:48+00:00\", \"blended_score\": 85, \"recommendation\": \"approve\", \"flagged_for_review\": false}','2026-09-03 21:15:48',NULL,'2026-07-23 21:15:48','2026-07-28 21:15:48',1,'2026-07-23 21:15:48','2026-09-03 21:15:48'),(10,'0cb03175-2e24-4065-bed6-9bfd40ce0e18',11,2,'submitted',3600.00,2,'Kedah',0,NULL,NULL,NULL,NULL,NULL,'2026-05-18 21:15:48',NULL,NULL,'2026-05-18 21:15:48','2026-09-03 21:15:48'),(14,'44737db5-d445-452e-bbd9-16b521ad20c2',2,2,'draft',2400.00,4,'Selangor',0,'test',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-03 21:26:38','2026-09-03 21:26:38');
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;

--
-- Dumping data for table `documents`
--

/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,'nric','documents/1/seed-5d882f03-7125-44f6-abee-236ef4b1fec4.pdf','nric.pdf','application/pdf',392496,'1a713403aabbfa55783484dfb29d65572f94defd0a9effbed9ac8ce72fd6f766','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(2,1,'income_proof','documents/1/seed-b5e0c797-9cc9-48b2-ac0c-6fa00b8a982f.pdf','income_proof.pdf','application/pdf',892801,'aded0e28e6b0c76ae93de36fe5c677ce93b0453901e5462afcacd48f3e96c9c2','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(3,2,'nric','documents/2/seed-dfda5a5d-ccb1-40c9-a474-196067a93adb.pdf','nric.pdf','application/pdf',752567,'83a89af2506bdb5a097852d6028dd9948b91a32ebb2ab6eec0fe7d2316c02410','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(4,2,'income_proof','documents/2/seed-e9b730c5-e012-4946-b1c6-df5baef9b934.pdf','income_proof.pdf','application/pdf',644745,'ea9e8fc50ad16c6a96fbd921557b3d76ee8c1074c7e9ca5c437e7b766056c565','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(5,3,'nric','documents/3/seed-7cc31882-d3b3-485c-87fc-18ff00c9e443.pdf','nric.pdf','application/pdf',280509,'775e563ccc19a24ac74565427d148aa2c601519a3babe2527c7828746eca8514','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(6,3,'income_proof','documents/3/seed-5b2965b8-d64e-4cf5-8b3a-e71e20d10ab7.pdf','income_proof.pdf','application/pdf',339204,'c4a525f7574fbe273f78ab1e19f78cc4bf7053b516252f0e681fa701e21c7513','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(7,3,'disability_cert','documents/3/seed-3a492641-75d4-4b6f-98a7-fd3c9d244fcd.pdf','disability_cert.pdf','application/pdf',774724,'4cdd5376c840106cf7160abf1777c037448c64f3b76b3104be16f5d6e365603d','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(8,4,'nric','documents/4/seed-a2e63163-4ecc-4bb7-9085-74398b3ed55c.pdf','nric.pdf','application/pdf',501833,'92a0042bcdb8fe55338bf85e079a852d5a33917cc36d77459e9c0cccbd01ac77','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(9,4,'income_proof','documents/4/seed-fcde3e12-a146-420a-9c59-820dca646625.pdf','income_proof.pdf','application/pdf',688063,'09e69995a0a871ea4059b6b8d077639000d14ad4d0cdd3cc1a27cb1ceb727685','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(10,4,'household_proof','documents/4/seed-5add7f72-919a-43b1-b1b4-fe5b15093984.pdf','household_proof.pdf','application/pdf',240843,'fc9802295b502bfb41d157b96269b5b80389f2cf6f6484d55dd11ed55889172d','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(11,5,'nric','documents/5/seed-4589eb9a-8fd4-44a2-bd8f-570ea3d02288.pdf','nric.pdf','application/pdf',802567,'4a213022155924d76050e2cd63801bf4979132c319fb627a91210054e48593f5','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(12,5,'income_proof','documents/5/seed-9598d5f5-f6f4-4d1e-bef7-1c94ee97fc44.pdf','income_proof.pdf','application/pdf',113760,'ebfd31c174c4fd41a8ef0d8e8b32182c07a0182959088264c718da4b2553b3d9','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(13,5,'household_proof','documents/5/seed-8e43f404-e136-43a0-b7a6-0975babbe34e.pdf','household_proof.pdf','application/pdf',894016,'4c8f47edb8e26a03ede3a84ac6561506376de519178eb2e39748320fce77b4bb','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(14,6,'nric','documents/6/seed-fbae7234-ae27-4c6e-bbbe-6258d83c0b80.pdf','nric.pdf','application/pdf',376978,'9f4d4c44c477aab4e6282212e074d1e3914ba5c8c711853e40c5cb14f9be4bb5','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(15,6,'income_proof','documents/6/seed-ed8311c0-549b-45b6-89a2-a8dac90b483f.pdf','income_proof.pdf','application/pdf',667126,'97301a6fa4d47fdad2707c7b2d484f6f812baa0aabe673d588b3ee235befa5a2','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(16,6,'disability_cert','documents/6/seed-bd80709d-d030-4f3e-bab4-91b5f7a5de83.pdf','disability_cert.pdf','application/pdf',449074,'75e47486f0e53c9e4c74019626df9fa376e945bc11d9607b0ca4d7594ece0118','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(17,7,'nric','documents/7/seed-130cfcec-1186-4625-8f4f-c9744920dfe5.pdf','nric.pdf','application/pdf',158095,'3630e4d9bca2b1c259373dae3f17848919ff4884e11788a7dd1c18dd70790b79','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(18,7,'income_proof','documents/7/seed-19c8770f-e549-4a1d-96e1-d881c3bd4b48.pdf','income_proof.pdf','application/pdf',527010,'5169117fa49c743d23e1010b702470b7af8dd79a095bf7a34d6bf4b1c048a1b8','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(19,8,'nric','documents/8/seed-09ce7613-4a74-4016-b083-7ad68c0a387b.pdf','nric.pdf','application/pdf',473453,'daf5d447f32533c62fe73f442456bc1e680613f9a00870c6a1f96703265fd57c','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(20,8,'income_proof','documents/8/seed-42c8eb8d-19c0-4de8-aeb8-3e74d805e954.pdf','income_proof.pdf','application/pdf',733905,'05fe8071b86dd58cff66cf544bef1d9ca1c53b59c230f9adc2176137f5348b0b','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(21,9,'nric','documents/9/seed-93ef996f-3916-4878-9c51-bcfbaee68394.pdf','nric.pdf','application/pdf',157775,'ed1151fd533266518cef85d8f90d45cceb560ce47b0c97622b7aefcdeea89189','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(22,9,'income_proof','documents/9/seed-66d36019-3f75-4ac5-8e4f-f05b80327de0.pdf','income_proof.pdf','application/pdf',795689,'2fa02121a37199b14e91e291911aed0923654307040f6a59a43991f04b803217','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(23,10,'nric','documents/10/seed-7324d30c-a8b6-4be6-bed8-b8d70f40b2b6.pdf','nric.pdf','application/pdf',335363,'774ffb625a8b9d939ada2abb7c27031ec373755beaa5966d706bd5df9948de6f','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(24,10,'income_proof','documents/10/seed-4173da4f-7e7a-4c08-87b5-d38dded7f511.pdf','income_proof.pdf','application/pdf',875164,'bd27064abebe45b30d964200080c63b4a62b04c8b5f406487f6459d1f2cbbd0f','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(25,10,'household_proof','documents/10/seed-d3bdef60-c71f-4f61-b151-af138a3f4585.pdf','household_proof.pdf','application/pdf',232155,'44f77a050f4892628895ff1a9e555bb139e2205015046bdc2c640848159b21fd','2026-09-03 21:15:48',NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;

--
-- Dumping data for table `disbursements`
--

/*!40000 ALTER TABLE `disbursements` DISABLE KEYS */;
INSERT INTO `disbursements` VALUES (1,1,'AB-20260903-XEICNL1T',650.00,'reconciled','bank_transfer','BNK1TNZRA5U01',NULL,'2026-09-03 21:15:48',1,'2026-07-01 21:15:47','2026-09-03 21:15:48','2026-09-03 21:15:48','2026-09-03 21:15:48'),(2,2,'AB-20260903-B6KJ0THL',700.00,'disbursed','bank_transfer','BNKCZZ7RZRUSB',NULL,'2026-09-03 21:15:48',1,'2026-09-05 21:15:48',NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(3,3,'AB-20260903-SFVDX5AZ',600.00,'approved','bank_transfer',NULL,NULL,'2026-09-03 21:15:48',1,NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(4,4,'AB-20260903-XXTTW9WG',350.00,'reconciled','bank_transfer','BNKJLTU106WIE',NULL,'2026-09-03 21:15:48',1,'2026-07-11 21:15:48','2026-09-03 21:15:48','2026-09-03 21:15:48','2026-09-03 21:15:48'),(5,6,'AB-20260903-I279SDRX',1415.00,'disbursed','bank_transfer','BNKSZ7F69E6HN',NULL,'2026-09-03 21:15:48',1,'2026-08-16 21:15:48',NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(6,9,'AB-20260903-JSZPZHJA',1425.00,'approved','bank_transfer',NULL,NULL,'2026-09-03 21:15:48',1,NULL,NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48');
/*!40000 ALTER TABLE `disbursements` ENABLE KEYS */;

--
-- Dumping data for table `audit_logs`
--

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 1}','2026-09-03 21:15:47','2026-09-03 21:15:47'),(2,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-06-22 21:15:47\", \"submitted_at\": \"2026-06-22 21:15:47\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:47.000000Z\"}}','2026-09-03 21:15:47','2026-09-03 21:15:47'),(3,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:47','2026-09-03 21:15:47'),(4,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 57, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":57,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":57,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 2,800.00 against an adjusted threshold of RM 6,450.00 (base RM 5,250.00 + 3 dependents).\\\",\\\"Income-based need score: 57\\\\/100.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(5,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"score\": 57, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(6,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(7,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(8,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-06-27 21:15:47\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(9,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',1,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(10,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',1,'127.0.0.1','Symfony','{\"amount\": 650, \"application\": \"60d103e3-72d0-46ff-ac76-e6814ea0bd4e\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(11,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',1,'127.0.0.1','Symfony','{\"amount\": \"650.00\", \"programme_budget_remaining\": \"249350.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(12,1,'disbursement.disbursed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',1,'127.0.0.1','Symfony','{\"bank_reference\": \"BNK1TNZRA5U01\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(13,1,'disbursement.reconciled','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',1,'127.0.0.1','Symfony',NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(14,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 1}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(15,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-08-25 21:15:48\", \"submitted_at\": \"2026-08-25 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(16,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(17,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 50, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":50,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":50,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 3,400.00 against an adjusted threshold of RM 6,850.00 (base RM 5,250.00 + 4 dependents).\\\",\\\"Income-based need score: 50\\\\/100.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(18,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"score\": 50, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(19,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(20,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(21,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-08-31 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(22,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',2,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(23,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',2,'127.0.0.1','Symfony','{\"amount\": 700, \"application\": \"720697a7-0dc5-4448-b1e0-d532eaf15d8b\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(24,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',2,'127.0.0.1','Symfony','{\"amount\": \"700.00\", \"programme_budget_remaining\": \"248650.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(25,1,'disbursement.disbursed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',2,'127.0.0.1','Symfony','{\"bank_reference\": \"BNKCZZ7RZRUSB\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(26,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 1}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(27,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-07-08 21:15:48\", \"submitted_at\": \"2026-07-08 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(28,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(29,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 56, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":56,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":32,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 4,100.00 against an adjusted threshold of RM 6,050.00 (base RM 5,250.00 + 2 dependents).\\\",\\\"Income-based need score: 32\\\\/100.\\\"]},{\\\"strategy\\\":\\\"disability_support\\\",\\\"score\\\":95,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household includes a registered person with disability.\\\",\\\"Verified disability certificate on file.\\\",\\\"Care burden uplift for dependents: +10.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(30,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"score\": 56, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(31,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(32,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(33,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-07-16 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(34,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',3,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(35,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',3,'127.0.0.1','Symfony','{\"amount\": 600, \"application\": \"e5f34c5f-a88b-4371-8448-148478671feb\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(36,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',3,'127.0.0.1','Symfony','{\"amount\": \"600.00\", \"programme_budget_remaining\": \"248050.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(37,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 2}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(38,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-07-06 21:15:48\", \"submitted_at\": \"2026-07-06 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(39,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(40,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 48, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":48,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"manual_review\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":48,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 3,200.00 against an adjusted threshold of RM 6,200.00 (base RM 5,000.00 + 3 dependents).\\\",\\\"Income-based need score: 48\\\\/100.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(41,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"score\": 48, \"eligible\": true, \"recommendation\": \"manual_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(42,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(43,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(44,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-07-08 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(45,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',4,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(46,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',4,'127.0.0.1','Symfony','{\"amount\": 350, \"application\": \"087c02e0-8e42-4c1d-8b47-774fbddfe7c7\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(47,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',4,'127.0.0.1','Symfony','{\"amount\": \"350.00\", \"programme_budget_remaining\": \"119650.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(48,1,'disbursement.disbursed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',4,'127.0.0.1','Symfony','{\"bank_reference\": \"BNKJLTU106WIE\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(49,1,'disbursement.reconciled','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',4,'127.0.0.1','Symfony',NULL,'2026-09-03 21:15:48','2026-09-03 21:15:48'),(50,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 2}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(51,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-07-04 21:15:48\", \"submitted_at\": \"2026-07-04 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(52,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(53,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 17, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":17,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"manual_review\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":17,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 4,800.00 against an adjusted threshold of RM 5,800.00 (base RM 5,000.00 + 2 dependents).\\\",\\\"Income-based need score: 17\\\\/100.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(54,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"score\": 17, \"eligible\": true, \"recommendation\": \"manual_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(55,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(56,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',5,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(57,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 3}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(58,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-08-04 21:15:48\", \"submitted_at\": \"2026-08-04 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(59,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(60,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 83, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":83,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":57,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 3,900.00 against an adjusted threshold of RM 9,000.00 (base RM 7,000.00 + 5 dependents).\\\",\\\"Income-based need score: 57\\\\/100.\\\"]},{\\\"strategy\\\":\\\"disability_support\\\",\\\"score\\\":100,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household includes a registered person with disability.\\\",\\\"Verified disability certificate on file.\\\",\\\"Care burden uplift for dependents: +15.\\\"]},{\\\"strategy\\\":\\\"emergency_relief\\\",\\\"score\\\":100,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Applicant is registered as affected by a declared disaster.\\\",\\\"Applying to a dedicated emergency grant programme.\\\",\\\"Large displaced household.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(61,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"score\": 83, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(62,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(63,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(64,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-08-12 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(65,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',6,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(66,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',5,'127.0.0.1','Symfony','{\"amount\": 1415, \"application\": \"207b7bf0-b827-4603-8887-e8e5c9dc94a9\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(67,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',5,'127.0.0.1','Symfony','{\"amount\": \"1415.00\", \"programme_budget_remaining\": \"398585.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(68,1,'disbursement.disbursed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',5,'127.0.0.1','Symfony','{\"bank_reference\": \"BNKSZ7F69E6HN\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(69,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 3}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(70,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-08-13 21:15:48\", \"submitted_at\": \"2026-08-13 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(71,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(72,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 58, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":58,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":24,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 5,600.00 against an adjusted threshold of RM 7,400.00 (base RM 7,000.00 + 1 dependents).\\\",\\\"Income-based need score: 24\\\\/100.\\\"]},{\\\"strategy\\\":\\\"emergency_relief\\\",\\\"score\\\":95,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Applicant is registered as affected by a declared disaster.\\\",\\\"Applying to a dedicated emergency grant programme.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(73,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"score\": 58, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(74,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(75,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',7,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(76,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 1}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(77,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-06-14 21:15:48\", \"submitted_at\": \"2026-06-14 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(78,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(79,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 0, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":false,\\\"blended_score\\\":0,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"reject\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":0,\\\"eligible\\\":false,\\\"reasons\\\":[\\\"Household income RM 6,200.00 against an adjusted threshold of RM 5,650.00 (base RM 5,250.00 + 1 dependents).\\\",\\\"Income exceeds the B40 threshold for this programme.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(80,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"score\": 0, \"eligible\": false, \"recommendation\": \"reject\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(81,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(82,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(83,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": {\"notes\": \"Household income exceeds the B40 threshold for this programme.\", \"status\": \"rejected\", \"decided_at\": \"2026-06-17 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(84,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',8,'127.0.0.1','Symfony','{\"to\": \"rejected\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(85,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 3}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(86,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-07-23 21:15:48\", \"submitted_at\": \"2026-07-23 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(87,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(88,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": {\"verified_at\": \"2026-09-03 21:15:48\", \"eligibility_score\": 85, \"agency_verification\": null, \"eligibility_breakdown\": \"{\\\"assessed_at\\\":\\\"2026-09-03T21:15:48+00:00\\\",\\\"eligible\\\":true,\\\"blended_score\\\":85,\\\"threshold\\\":50,\\\"recommendation\\\":\\\"approve\\\",\\\"flagged_for_review\\\":false,\\\"strategies\\\":[{\\\"strategy\\\":\\\"b40_income\\\",\\\"score\\\":72,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Household income RM 2,400.00 against an adjusted threshold of RM 8,600.00 (base RM 7,000.00 + 4 dependents).\\\",\\\"Income-based need score: 72\\\\/100.\\\"]},{\\\"strategy\\\":\\\"emergency_relief\\\",\\\"score\\\":100,\\\"eligible\\\":true,\\\"reasons\\\":[\\\"Applicant is registered as affected by a declared disaster.\\\",\\\"Applying to a dedicated emergency grant programme.\\\",\\\"Large displaced household.\\\"]}]}\"}, \"from\": []}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(89,NULL,'application.assessed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"score\": 85, \"eligible\": true, \"recommendation\": \"approve\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(90,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": {\"status\": \"under_review\"}, \"from\": {\"status\": \"submitted\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(91,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": \"under_review\", \"from\": \"submitted\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(92,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": {\"status\": \"approved\", \"decided_at\": \"2026-07-28 21:15:48\", \"decided_by\": 1}, \"from\": {\"status\": \"under_review\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(93,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',9,'127.0.0.1','Symfony','{\"to\": \"approved\", \"from\": \"under_review\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(94,1,'disbursement.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',6,'127.0.0.1','Symfony','{\"amount\": 1425, \"application\": \"67d6d98e-6c88-437d-a252-af70cae00353\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(95,1,'disbursement.approved','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Disbursement',6,'127.0.0.1','Symfony','{\"amount\": \"1425.00\", \"programme_budget_remaining\": \"397160.00\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(96,NULL,'application.created','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',10,'127.0.0.1','Symfony','{\"status\": \"draft\", \"aid_program_id\": 2}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(97,NULL,'application.updated','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',10,'127.0.0.1','Symfony','{\"to\": {\"status\": \"submitted\", \"created_at\": \"2026-05-18 21:15:48\", \"submitted_at\": \"2026-05-18 21:15:48\"}, \"from\": {\"status\": \"draft\", \"created_at\": \"2026-09-03T21:15:48.000000Z\"}}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(98,NULL,'application.status_changed','aa6413d7-6287-4cc3-a408-77dcedc653ae','App\\Models\\Application',10,'127.0.0.1','Symfony','{\"to\": \"submitted\", \"from\": \"draft\"}','2026-09-03 21:15:48','2026-09-03 21:15:48'),(99,2,'auth.login','0fc6a805-3549-4a11-acc6-09ecd20642dc','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:16:20','2026-09-03 21:16:20'),(100,2,'application.created','f79cc931-e54c-4320-8d74-e902d0493fe4','App\\Models\\Application',11,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','{\"status\": \"draft\", \"aid_program_id\": 3}','2026-09-03 21:16:33','2026-09-03 21:16:33'),(101,2,'application.deleted','80a0a1e3-29a3-4e95-860f-e6fd5489b0f9','App\\Models\\Application',11,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','{\"reference\": \"98fb576b-5828-443c-b9fd-b5712a9dbac5\"}','2026-09-03 21:16:35','2026-09-03 21:16:35'),(102,2,'application.created','52a4d70e-020a-4cf3-83a7-5bc80010013d','App\\Models\\Application',12,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','{\"status\": \"draft\", \"aid_program_id\": 3}','2026-09-03 21:17:31','2026-09-03 21:17:31'),(103,2,'application.deleted','de9d0c8c-81af-41ce-9ade-2f2b169c11ff','App\\Models\\Application',12,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','{\"reference\": \"251d7297-b409-48f7-a6df-562cb7218a68\"}','2026-09-03 21:17:47','2026-09-03 21:17:47'),(105,2,'auth.logout','47264fcf-4202-43db-9699-569abfa7fc0b','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:18:50','2026-09-03 21:18:50'),(106,1,'auth.login','96d02dad-6c46-42e5-8ef0-383fda01b386','App\\Models\\User',1,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:19:03','2026-09-03 21:19:03'),(107,2,'auth.login','6fbbdf9c-b95d-48fc-82ef-a55ab9b11cc1','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:22:30','2026-09-03 21:22:30'),(108,2,'auth.login','31b15f2f-4980-469e-9c0c-c28c1849dbf1','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:23:01','2026-09-03 21:23:01'),(109,2,'auth.login','e360da34-7419-419f-8b19-61e3fc79d22f','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:24:23','2026-09-03 21:24:23'),(110,2,'application.created','393f2175-9861-4f2f-9188-931664ce0cf8','App\\Models\\Application',13,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36','{\"status\": \"draft\", \"aid_program_id\": 2}','2026-09-03 21:24:26','2026-09-03 21:24:26'),(111,2,'application.deleted','7fb0b4a0-7078-4eef-949e-85d5a7887209','App\\Models\\Application',13,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36','{\"reference\": \"da40fa6e-0504-489a-b792-2c4836888a38\"}','2026-09-03 21:24:28','2026-09-03 21:24:28'),(112,2,'auth.login','491fa254-a9c5-4548-a592-6ff08baf52e1','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:25:43','2026-09-03 21:25:43'),(113,2,'auth.login','f7245ca9-2d1d-46ee-8441-ca0a02440fc5','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.7462',NULL,'2026-09-03 21:26:38','2026-09-03 21:26:38'),(114,2,'application.created','bd215055-1a50-4fd5-b0c2-4b7d312cf2b8','App\\Models\\Application',14,'172.19.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.7462','{\"status\": \"draft\", \"aid_program_id\": 2}','2026-09-03 21:26:38','2026-09-03 21:26:38'),(115,2,'auth.login','f4eac82c-fe8c-4837-a6fd-19bbc86e9ff9','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:26:56','2026-09-03 21:26:56'),(116,2,'auth.login','03b76486-4229-454a-aad5-4a5ee6d0d74f','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:30:00','2026-09-03 21:30:00'),(117,2,'auth.login','565a4252-dcc5-4812-bcef-a72583df6029','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:30:03','2026-09-03 21:30:03'),(118,2,'auth.login','03e83ad8-7802-42ac-be0e-6b24b722b03c','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:32:09','2026-09-03 21:32:09'),(119,2,'auth.login','b5c82be9-8145-4ba8-9ed1-be674fca72d8','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:32:12','2026-09-03 21:32:12'),(120,2,'auth.login','d0541722-2f27-4220-8227-6cf9240ebc7c','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:32:37','2026-09-03 21:32:37'),(121,2,'auth.login','76cad347-4759-4742-b086-25636eb92f6e','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:36:46','2026-09-03 21:36:46'),(122,2,'auth.login','d95b6fbf-6877-4732-91df-0a4b854a9f8f','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:38:29','2026-09-03 21:38:29'),(123,1,'auth.logout','305d0afe-7e74-48d6-8329-3692e2e4af6f','App\\Models\\User',1,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:40:14','2026-09-03 21:40:14'),(124,2,'auth.login','576346ef-77ac-46f7-b8ba-be4ce39f37f1','App\\Models\\User',2,'172.19.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',NULL,'2026-09-03 21:40:26','2026-09-03 21:40:26');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-04  6:36:10
