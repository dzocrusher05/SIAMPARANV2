-- Menambahkan kolom icon untuk tabel jenis_sarana
-- Kolom icon akan menyimpan data gambar dalam format BLOB

ALTER TABLE `jenis_sarana` 
ADD COLUMN `icon` MEDIUMBLOB NULL AFTER `nama_jenis`;

-- Menambahkan indeks untuk kolom icon jika diperlukan
-- ALTER TABLE `jenis_sarana` ADD INDEX `idx_icon` (`icon`);