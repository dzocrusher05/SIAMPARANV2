# SIAMPARAN - Sistem Informasi Pemetaan Sarana

SIAMPARAN adalah sistem informasi berbasis web untuk pemetaan sarana kesehatan secara interaktif menggunakan Google Maps. Aplikasi ini memungkinkan pengguna untuk mengelola data sarana kesehatan, memvisualisasikannya dalam bentuk peta, dan menganalisis distribusi sarana di berbagai wilayah.

## Fitur Utama

- **Pemetaan Interaktif**: Visualisasi data sarana kesehatan menggunakan peta interaktif
- **Manajemen Data**: CRUD (Create, Read, Update, Delete) data sarana kesehatan
- **Import Data**: Import data dari file Excel
- **Filter dan Pencarian**: Filter data berdasarkan wilayah dan jenis sarana
- **Autentikasi Admin**: Sistem login untuk pengelolaan data
- **Proteksi Halaman**: Halaman peta hanya dapat diakses setelah login

## Struktur Direktori

```
SIAMPARAN/
├── admin/           # Halaman administrasi
├── api/             # API endpoints
├── assets/          # File assets (gambar, ikon, dll)
├── config/          # Konfigurasi aplikasi
├── public/          # Halaman publik (peta)
├── sql/             # File SQL skema database
├── styles/          # File CSS/Tailwind
├── tools/           # Tools tambahan
├── docs/            # Dokumentasi
└── ...
```

## Teknologi yang Digunakan

- **Backend**: PHP (tanpa framework)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Pemetaan**: Leaflet.js dengan Google Maps
- **Styling**: Tailwind CSS
- **Autentikasi**: Session-based
- **Package Manager**: npm (untuk Tailwind CSS)

## Instalasi

### Prasyarat

- Server web dengan PHP 7.4+
- Database MySQL
- Composer (opsional)
- npm (untuk membangun CSS)

### Langkah Instalasi

1. **Clone atau download repository**
   ```bash
   git clone <url-repository>
   ```

2. **Buat database baru**
   ```sql
   CREATE DATABASE pemetaan_sarana_db;
   ```

3. **Impor skema database**
   ```bash
   mysql -u root -p pemetaan_sarana_db < sql/schema_core.sql
   ```

4. **Konfigurasi database**
   - Edit file `config/db.php` atau buat file `config/db.custom.php`:
   ```php
   <?php
   $DB_HOST = 'localhost';
   $DB_NAME = 'pemetaan_sarana_db';
   $DB_USER = 'root';
   $DB_PASS = '';
   ```

5. **Install dependensi npm (untuk Tailwind CSS)**
   ```bash
   npm install
   ```

6. **Bangun file CSS**
   ```bash
   npm run build:css
   ```

7. **Akses aplikasi**
   Buka aplikasi melalui browser di `http://localhost/siamparan`

## Sistem Autentikasi Admin

### Akses Login
- URL: `/admin/login.php`

### Kredensial Default
- Username: `admin`
- Password: `admin123`

### Halaman Admin
Setelah login, pengguna akan diarahkan ke dashboard admin (`/admin/index.php`) yang berisi:
- Manajemen data sarana
- Manajemen jenis sarana
- Import data sarana
- Manajemen wilayah
- Manajemen user (khusus admin)
- Ganti password
- Logout

## Proteksi Halaman

Halaman peta (`/public/index.php`) dilindungi dan hanya dapat diakses oleh pengguna yang telah login. Jika pengguna mencoba mengakses halaman peta tanpa login, mereka akan diarahkan ke halaman login admin.

## Pengembangan

### Struktur Basis Data

Aplikasi menggunakan 3 tabel utama:

1. `data_sarana`: Menyimpan informasi dasar sarana
2. `jenis_sarana`: Menyimpan jenis-jenis sarana
3. `sarana_jenis`: Tabel pivot untuk relasi many-to-many

### API Endpoints

API endpoints berada di direktori `/api/`:
- `/api/sarana.php`: Mengelola data sarana
- `/api/jenis.php`: Mengelola jenis sarana
- `/api/wilayah.php`: Mengelola data wilayah
- `/api/import_sarana.php`: Endpoint untuk import data

### Pengembangan CSS

Aplikasi menggunakan Tailwind CSS yang perlu dibangun:

```bash
# Bangun CSS untuk halaman publik dan admin
npm run build:css

# Bangun CSS hanya untuk halaman publik
npm run build:css:public

# Bangun CSS hanya untuk halaman admin
npm run build:css:admin
```

### Penyesuaian Aplikasi

Untuk menyesuaikan aplikasi:

1. **Ubah konfigurasi database**: Edit file `config/db.php` atau buat `config/db.custom.php`
2. **Sesuaikan jenis sarana**: Modifikasi file `public/app_map.js` bagian `JENIS_META`
3. **Ubah tampilan**: Edit file CSS di direktori `styles/` kemudian jalankan `npm run build:css`
4. **Tambahkan fitur**: Tambahkan file PHP baru di direktori `admin/` atau `api/`

### Tambahkan Fitur Baru

Untuk menambahkan fitur baru:

1. Buat endpoint API baru di direktori `/api/`
2. Tambahkan halaman admin di direktori `/admin/`
3. Tambahkan logika frontend di `/public/app_map.js` jika diperlukan
4. Tambahkan styling di `/styles/tailwind.css` jika diperlukan
5. Bangun ulang CSS dengan `npm run build:css`

## Lisensi

Aplikasi ini dikembangkan untuk keperluan internal dan tidak memiliki lisensi open source resmi. Silakan hubungi pengembang untuk informasi lebih lanjut.

## Dukungan

Untuk bantuan teknis, silakan hubungi tim pengembang atau buat issue di repository ini.