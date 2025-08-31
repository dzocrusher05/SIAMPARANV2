-- Skema inti untuk aplikasi Pemetaan Sarana
-- Tabel utama data sarana, tabel jenis, dan relasi pivot

CREATE TABLE IF NOT EXISTS `data_sarana` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_sarana` varchar(255) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `kabupaten` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wilayah_kab` (`kabupaten`),
  KEY `idx_wilayah_kec` (`kecamatan`),
  KEY `idx_wilayah_kel` (`kelurahan`),
  KEY `idx_coords` (`latitude`,`longitude`),
  KEY `idx_nama` (`nama_sarana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jenis_sarana` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_jenis` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nama_jenis` (`nama_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sarana_jenis` (
  `sarana_id` int(10) UNSIGNED NOT NULL,
  `jenis_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`sarana_id`,`jenis_id`),
  KEY `idx_jenis_id` (`jenis_id`),
  CONSTRAINT `fk_sj_sarana` FOREIGN KEY (`sarana_id`) REFERENCES `data_sarana` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sj_jenis` FOREIGN KEY (`jenis_id`) REFERENCES `jenis_sarana` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

