# SIAMPARAN – Sistem Informasi Pemetaan Sarana

Aplikasi web untuk memetakan dan mengelola data sarana secara interaktif menggunakan Leaflet. Proyek ini berfokus pada kemudahan administrasi data, performa pemuatan peta, dan UX yang ringkas namun kaya fitur.

## Fitur Utama

- Pemetaan interaktif (Leaflet + MarkerCluster) pada `public/index.php`.
- Filter peta lengkap (Kabupaten/Kecamatan/Kelurahan/Jenis) + autosuggest nama sarana.
- Ikon sarana per jenis:
  - Custom icon tersimpan di kolom `icon` tabel `jenis_sarana` (dibaca sebagai `icon_base64` via API).
  - Fallback ke file `assets/icon/<slug-nama-jenis>.png` bila icon khusus tidak ada.
- Admin Dashboard (`admin/index.php`):
  - CRUD data sarana dan jenis.
  - Export CSV/XLSX (sesuai filter aktif) – menu dropdown “Export”.
  - Pencarian cepat (debounce) dan pencarian fleksibel (tokenized LIKE) di API.
  - Sidebar dapat disembunyikan/ditampilkan + persist (localStorage) + shortcut Ctrl+I.
  - Filter “Jenis” di header Data Sarana dengan dropdown elegan + autosuggest + animasi, tidak terpotong konten (posisi floating).
- Ubah Jenis Sarana Massal (`admin/jenis_sarana_massal.php`):
  - Ubah massal langsung (jenis lama → jenis baru).
  - Import CSV mapping (jenis_lama, jenis_baru).
  - Submenu “Pindahkan Per Data”: muat sarana pada jenis tertentu, pilih sebagian (checkbox), pindahkan ke jenis lain. Hot reload + toast.

## Struktur Direktori

```
SIAMPARAN/
├─ admin/          # Halaman admin (dashboard, import, mass update, dll.)
├─ api/            # API endpoints (PHP)
├─ assets/         # Aset statis: ikon default per jenis (assets/icon/*.png), favicon, template CSV
├─ config/         # Konfigurasi (db, auth)
├─ public/         # Halaman peta (public/index.php) dan skrip peta
├─ sql/            # Skema database (schema_core.sql)
├─ styles/         # (opsional) sumber CSS/Tailwind
└─ tools/ docs/    # utilitas & dokumentasi tambahan
```

## Teknologi

- Backend: PHP (tanpa framework)
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, JavaScript
- Peta: Leaflet + leaflet.markercluster
- Styling: CSS utilitas ringan (opsional Tailwind)
- Autentikasi: session-based

## Instalasi

### Prasyarat

- PHP 7.4+ (atau 8.x)
- MySQL / MariaDB
- Web server (Apache/Nginx) terkonfigurasi

### Langkah

1) Clone repo
```bash
git clone <url-repository>
cd SIAMPARAN
```

2) Buat database & impor skema
```sql
CREATE DATABASE siarmap_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
```bash
mysql -u <user> -p siarmap_db < sql/schema_core.sql
```

3) Konfigurasi koneksi DB
Edit `config/db.php` atau sediakan `config/db.custom.php`:
```php
<?php
$DB_HOST = 'localhost';
$DB_NAME = 'siarmap_db';
$DB_USER = 'root';
$DB_PASS = '';
```

4) Pastikan aset peta lokal tersedia
- `public/vendor/leaflet/leaflet.js` & `leaflet.css`
- `public/vendor/leaflet.markercluster/leaflet.markercluster.js`
- `public/vendor/leaflet.markercluster/MarkerCluster.css` & `MarkerCluster.Default.css`

Catatan: `public/index.php` sudah menyiapkan CSS fallback CDN; JS Leaflet/Cluster dimuat lokal dulu dan akan mencoba CDN jika gagal.

5) Konfigurasi akses admin
- Lihat `config/auth.php` untuk mekanisme login.
- Buat akun admin sesuai kebutuhan (sesuaikan dengan environment Anda).

## Menjalankan Secara Lokal

Letakkan proyek pada root server lokal (mis. XAMPP/Apache) lalu akses:
- Peta Publik: `http://localhost/SIAMPARAN/public/index.php`
- Admin: `http://localhost/SIAMPARAN/admin/index.php`

## Ikon Jenis Sarana

- Custom icon (PNG 32×32) bisa diunggah via Admin → Jenis Sarana (disimpan sebagai BLOB di kolom `icon`).
- Fallback: `assets/icon/<slug-nama-jenis>.png`. Penamaan otomatis: huruf kecil, spasi/karakter non-alfanumerik → `-`.
  - Contoh: “Puskesmas Pembantu” → `assets/icon/puskesmas-pembantu.png`.

## Export Data

- CSV: `api/sarana.php?export=csv&...filter`
- XLSX: `api/sarana.php?export=xlsx&...filter` (ZipArchive; tanpa dependensi eksternal)
- Dari Admin → Data Sarana → tombol “Export ▾” (menghormati q & filterJenis yang aktif)

## Perbaikan & Peningkatan Terbaru

- Peta publik (public/app_map.js) distabilkan:
  - Fallback provider tile, invalidasi ukuran setelah render, clustering.
  - Autosuggest nama, GPS dengan auto-center sekali, Reset mengembalikan view default.
  - Popup marker menampilkan nama/wilayah/jenis + tautan ke Google Maps.
- Admin Dashboard:
  - Sidebar toggle (persist, Ctrl+I) + animasi; konten menjadi full lebar saat sidebar tersembunyi.
  - Kolom “Jenis” memiliki dropdown filter cantik dengan autosuggest, floating (tidak terpotong konten) + animasi open/close halus.
  - Pencarian admin (q) fleksibel (tokenized LIKE) dan debounce di input.
  - Export CSV/XLSX dengan dropdown; XLSX generator minimal.
- Mass Update Jenis (`admin/jenis_sarana_massal.php`):
  - Submenu “Pindahkan Per Data” (GET daftar sarana by jenis, POST pindahkan sebagian)
  - Hot reload daftar dan jumlah per jenis setelah pindah (toasts/konfirmasi SweetAlert, fallback aman ke alert/confirm).

## API Ringkas

- `api/sarana.php` (GET)
  - Filter: `q`, `kabupaten`, `kecamatan`, `kelurahan`, `jenis` (id/nama, comma-separated), `bbox`
  - Paginasi admin: `page`, `per_page`; respon: `{ data, total, total_pages }`
  - Export: `export=csv|xlsx`
- `api/jenis.php` (GET)
  - Mengembalikan daftar jenis beserta `count`, dan `icon_base64` bila kolom `icon` ada.
- `api/jenis_sarana_massal.php` (POST JSON `_method=PUT`)
  - Ubah semua mapping dari `old_jenis_id` ke `new_jenis_id`.
- `api/count_jenis_sarana.php` (POST `{ jenis_id }`)
  - Hitung berapa data di jenis tersebut.
- `api/jenis_sarana_select.php`
  - GET `?jenis_id=`: daftar sarana di jenis tertentu.
  - POST `{ old_jenis_id, new_jenis_id, sarana_ids: [] }`: pindahkan sebagian.
- `api/import_jenis_sarana.php` (POST multipart CSV)
  - Import mapping jenis massal dari CSV.

## Catatan Deployment

- Pastikan file vendor Leaflet/MarkerCluster tersedia; bila tidak, gunakan CDN (sudah disiapkan di `public/index.php`).
- Aktifkan zip (ZipArchive) untuk export XLSX (umumnya sudah tersedia di hosting).
- Hard refresh (Ctrl+F5) setelah update `public/app_map.js` dan `admin/index.php` agar cache browser tersingkir.

## Kontribusi & Push ke GitHub

1) Buat branch fitur/bugfix:
```bash
git checkout -b feat/filter-jenis-admin
```
2) Commit terarah dan kecil:
```bash
git add -A
git commit -m "admin: jenis header filter (dropdown + autosuggest + floating)"
```
3) Push branch:
```bash
git push origin feat/filter-jenis-admin
```
4) Buka Pull Request dengan ringkasan perubahan, langkah uji, dan dampak.

## Troubleshooting

- Peta abu-abu:
  - Cek konsol untuk error Leaflet loading; pastikan vendor/leaflet ada atau gunakan fallback CDN.
  - Pastikan `#map` memiliki tinggi (di `public/index.php` telah diset 100vh).
- Export XLSX gagal:
  - Cek dukungan `ZipArchive`; aktifkan di PHP config hosting.
- Dropdown “Jenis” terpotong:
  - Sudah diatasi dengan mode floating (position: fixed). Pastikan tidak ada overlay lain menutupinya.

---

Lisensi: internal proyek. Hubungi pemilik repositori untuk penggunaan eksternal.

