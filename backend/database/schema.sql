-- dzencode.loc — comments domain schema
--
-- Generated from the live `comments` table (source of truth: the
-- `2026_08_19_000001_create_comments_table` migration). Laravel's own
-- framework tables (cache, jobs, sessions, users, migrations — none of
-- them part of this app's actual data model, see README.md) are
-- intentionally excluded.
--
-- To open in MySQL Workbench: Database > Reverse Engineer... > pick this
-- file as the SQL script source, or File > Import > Reverse Engineer MySQL
-- Create Script.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `comments`;

CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `replied_to_comment_id` bigint unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `home_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `images` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_parent_id_created_at_index` (`parent_id`,`created_at`),
  CONSTRAINT `comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
