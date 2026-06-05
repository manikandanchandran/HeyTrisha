-- Run once on heytrisha_db (or your API DB) if artisan migrate is not available.
-- Matches migration 2026_04_10_create_site_specification_chunks_table.php

CREATE TABLE IF NOT EXISTS `site_specification_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` bigint unsigned NOT NULL,
  `spec_version` varchar(64) NOT NULL,
  `chunk_index` smallint unsigned NOT NULL,
  `content` text NOT NULL,
  `embedding` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spec_chunk_unique` (`site_id`,`spec_version`,`chunk_index`),
  KEY `site_specification_chunks_site_id_index` (`site_id`),
  KEY `site_specification_chunks_spec_version_index` (`spec_version`),
  CONSTRAINT `site_specification_chunks_site_id_foreign`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_specifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` bigint unsigned NOT NULL,
  `spec_version` varchar(64) NOT NULL,
  `allowlist_json` mediumtext NOT NULL,
  `rules_summary` text DEFAULT NULL,
  `forbidden_tables_json` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_specifications_site_id_unique` (`site_id`),
  KEY `site_specifications_site_id_index` (`site_id`),
  CONSTRAINT `site_specifications_site_id_foreign`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
