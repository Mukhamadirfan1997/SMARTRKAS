# SmartRKAS

Aplikasi **Rencana Kegiatan dan Anggaran Sekolah (RKAS)** berbasis Laravel 12. Mendukung dua mode penggunaan:

1. **Web server** — dijalankan dengan `php artisan serve` (mis. untuk server lokal sekolah).
2. **Desktop** — dibungkus **Tauri 2** (Windows), server Laravel dijalankan otomatis di dalam aplikasi bersama scheduler.

Tidak menggunakan multi-sekolah (`sekolah_id`); satu instalasi = satu sekolah, satu pengguna admin. Identitas sekolah diisi lewat menu **Profil Sekolah**.

---

## Fitur

- **Master data**: Tahun Anggaran, Sumber Dana, Jenis Belanja, Master Program, Master Kode Rekening (termasuk impor Excel).
- **Item RKAS**: perencanaan per bulan (`RkasItemBulan`), import RKAS dari Excel (batch, asinkron-sinkron), dedup, renumber, sync jumlah.
- **BKU (Transaksi BKU)**: kas masuk/keluar, mutasi antarbank, override anggaran.
- **Laporan**: BKU, Rekap Rekening, Rekap Kuartal (Tribulan), Rekap SIPLAH — preview web, cetak PDF, dan export Excel asinkron (`ExportJob`).
- **Dashboard**: ringkasan statistik + tabel item RKAS dinamis (rencana/realisasi/sisa, filter bulan, paginasi).
- **Backup & Pemulihan**: backup otomatis terjadwal (lihat [Jadwal Scheduler](#jadwal-scheduler)), halaman Backup untuk menjalankan manual + unduh file `.zip`.
- **Riwayat Aktivitas**: audit log operasional (tambah/ubah/hapus/import/override) dengan filter.
- **Keamanan**: login + register, lupa/reset password, audit log, escapade flash, batas ukuran upload, index DB untuk performa.
- **Notifikasi Telegram** (opsional): error log + event backup dikirim ke chat.

---

## Panduan Penggunaan

### 1. Memulai
- **Mode Desktop**: jalankan `SmartRKAS` dari menu Start (atau file installer `.exe`). Database dibuat otomatis saat pertama kali dijalankan — langsung bisa login.
- **Mode Web**: jalankan `php artisan serve` lalu buka `http://127.0.0.1:8000`.

Login dengan akun admin (lihat `database/seeders/DatabaseSeeder.php` saat mode web; di desktop gunakan menu **Lupa password?** atau perintah `php artisan user:reset-password` dari konsol). Setelah login, isi identitas sekolah di menu **Pengaturan → Profil Sekolah**.

### 2. Alur Kerja Harian
1. **Pengaturan → Profil Sekolah** — lengkapi NPSN, nama, alamat, dll. (dipakai di kop laporan).
2. **Tahun Anggaran** — aktifkan tahun berjalan; data lama tersimpan untuk riwayat.
3. **Master data** — isi **Sumber Dana**, **Jenis Belanja**, **Master Program**, **Master Kode Rekening** (bisa impor dari Excel).
4. **Item RKAS** — isi rencana kegiatan per item per bulan, atau **impor dari file Excel** melalui halaman `/import-rkas` (format template bisa diunduh di halaman tersebut).
5. **BKU (Transaksi BKU)** — catat kas masuk/keluar setiap transaksi; dashboard dan laporan otomatis menghitung realisasi.
6. **Laporan** — cetak **BKU**, **Rekap Rekening**, **Rekap Kuartal (Tribulan)**, dan **Rekap SIPLAH** dalam bentuk preview web, PDF, atau export Excel.

### 3. Pemantauan & Keamanan Data
- **Dashboard** — pantau rencana vs realisasi per item; badge status (Normal / Hampir Habis / Over Budget / Belum Realisasi).
- **Pengaturan → Backup & Pemulihan** — klik **Backup Sekarang** untuk membuat cadangan manual, atau andalkan backup otomatis harian (01:30). Unduh file `.zip` sebagai arsip.
- **Pengaturan → Riwayat Aktivitas** — audit log semua perubahan (siapa, kapan, apa).
- Ganti password: menu **Profil** di pojok kanan atas.

### 4. Catatan Penting
- Satu instalasi = satu sekolah (tanpa konsep multi-sekolah).
- Restore backup = mengunduh file `.zip` (restore otomatis dari UI tidak tersedia).
- Notifikasi Telegram opsional; atur `TELEGRAM_BOT_TOKEN` dan `TELEGRAM_CHAT_ID` di `.env`.

---

## Persyaratan

- PHP **8.2+** dengan ekstensi: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `xml`, `zip`, `gd` (untuk ikon/PDF).
- Composer 2.
- Node.js 18+ (untuk membangun aset & desktop Tauri).
- Rust toolchain (hanya untuk membangun desktop Tauri).
- WebView2 runtime (Windows 10/11 umumnya sudah terpasang) untuk mode desktop.

---

## Instalasi (mode Web)

```bash
composer install
npm install
npm run build          # kompilasi aset (CSS/JS)
copy .env.example .env # Windows; `cp` di Linux/macOS

php artisan key:generate
php artisan migrate --seed   # seed: pengguna admin + Tahun Anggaran default
php artisan serve
```

Buka `http://127.0.0.1:8000`. Kredensial default admin lihat di `database/seeders/DatabaseSeeder.php` (ubah setelah login).

> Mode desktop tidak perlu langkah di atas — database dibuat otomatis saat pertama kali dijalankan.

---

## Variabel Lingkungan

| Variabel | Keterangan |
|---|---|
| `APP_NAME` | Nama aplikasi; dipakai sebagai nama direktori backup (`laravel-backup` default). |
| `DB_CONNECTION=sqlite` | Database utama. Di mode desktop otomatis diarahkan ke data-dir aplikasi. |
| `SMARTRKAS_DATA_DIR` | (Deskripsi) lokasi data runtime untuk instalasi portable; bila tidak diset, memakai lokasi default. |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | Notifikasi Telegram (opsional). Dikosongkan untuk menonaktifkan. |
| `LOG_TELEGRAM_LEVEL` | Level minimum log yang dikirim ke Telegram (default `error`). |
| `BACKUP_DISKS` / `BACKUP_DATABASE_CONNECTION` | Konfigurasi backup Spatie laravel-backup (lihat `config/backup.php`). |

---

## Jadwal Scheduler

Jadwal terdaftar di `routes/console.php` dan berjalan otomatis di mode desktop (proses `artisan schedule:work` di-spawn bersama `artisan serve`). Di mode web, jalankan `php artisan schedule:work`.

| Waktu | Perintah |
|---|---|
| Setiap hari 01:00 | `backup:clean` (hapus backup kadaluarsa) |
| Setiap hari 01:30 | `backup:run` (buat backup database + file) |
| Minggu 02:00 | `audit:clean 90` (hapus audit log > 90 hari) |
| Minggu 03:00 | hapus `failed_jobs` > 30 hari |
| Bulanan 04:00 | `kwitansi:clean 2` (hapus kwitansi > 2 tahun) |

Lihat daftar dengan `php artisan schedule:list`.

---

## Perintah CLI Berguna

| Perintah | Fungsi |
|---|---|
| `php artisan app:install` | Setup awal (migrasi + seed) — otomatis saat run pertama desktop. |
| `php artisan user:reset-password {user} {password?}` | Reset password (cari via email atau id). Kosongkan `password` = generate acak. |
| `php artisan rkas:dedup --dry-run` | Gabung item RKAS duplikat. |
| `php artisan rkas:renumber` | Penomoran ulang `no_urut` item 1..N. |
| `php artisan rkas:sync-jumlah` | Set `jumlah` = jumlah rencana semua bulan. |
| `php artisan audit:clean {days=90}` | Hapus audit log lebih lama dari N hari. |
| `php artisan backup:run` / `backup:clean` | Backup / bersihkan backup (Spatie). |
| `php artisan schedule:work` | Jalankan scheduler (mode web). |

---

## Pengujian

```bash
vendor\bin\phpunit               # full suite (sqlite :memory:)
vendor\bin\phpstan analyse --no-progress   # PHPStan level 6
```

Konfigurasi test di `phpunit.xml`: `DB_CONNECTION=sqlite`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`.

---

## Membangun Desktop (Tauri, Windows)

1. Pastikan PHP berada di `PATH` (atau bundle manual, lihat di bawah) dan toolchain Rust terpasang.
2. Kompilasi aset web lalu build installer:

   ```bash
   npm install
   npm run build
   npm run tauri -- build
   ```

   Hasil installer: `src-tauri/target/release/bundle/nsis/SmartRKAS_<version>_x64-setup.exe` dan `bundle/msi/SmartRKAS_<version>_x64_en-US.msi`.

3. **Bundle PHP otomatis (dianjurkan)**: salin folder instalasi PHP (dengan ekstensi `pdo_sqlite`, `sqlite3`, `mbstring`, `zip`) ke `src-tauri/php/` **sebelum** `npm run tauri -- build`. Folder tersebut ikut dibundel sebagai resource `php/`, sehingga installer berjalan tanpa PHP di PATH. Tanpa langkah ini aplikasi memakai `php` dari PATH.
4. Data aplikasi (database sqlite `smartrkas.sqlite`, backup, upload) disimpan di data-dir aplikasi; file dibundel di resource-dir hanya untuk kode.

Regenerasi ikon desktop:

```bash
npm run tauri -- icon "icon smartrkas.png"
```

---

## Struktur Penting

- `app/Imports/RkasImport.php` — import RKAS (identitas item via `(tahun, sumber_dana, program, kode_rekening)` + `normalizeUraian`).
- `app/Imports/RkasImportHeaderDetector.php` — deteksi baris header & kolom file Excel.
- `app/Jobs/ProcessRkasImport.php` — job import (sinkron).
- `app/Console/Commands/` — perintah CLI (lihat tabel di atas).
- `app/Logging/TelegramLogHandler.php` — channel log ke Telegram.
- `src-tauri/src/lib.rs` — startup desktop: inisialisasi DB, spawn `artisan serve` + `schedule:work`.
- `routes/console.php` — jadwal scheduler.

---

## Lisensi

Proyek internal. Hak cipta milik pengembang aplikasi.
