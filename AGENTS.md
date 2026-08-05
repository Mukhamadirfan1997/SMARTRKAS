# SmartRKAS Desktop — AGENTS.md

Aplikasi desktop Windows offline-first untuk perencanaan & monitoring RKAS
per sekolah. Proyek BARU, memakai `sira-rkas` sebagai referensi (jangan diubah).

- Blueprint: `SmartRKAS_Blueprint_v2_Integrasi_Monitoring_RKAS_SIRA_RKAS.md` (di repo sira-rkas).
- Prinsip: "Sederhana di Sekolah, Lengkap di Server."
- Stack: Laravel 12 + PHP 8.4 (dev 8.2) + Blade + Tailwind + Alpine.js + Tauri 2 + SQLite + Laravel Excel + DomPDF.
- Login: single user lokal per instalasi. Offline-first. Sinkronisasi = V2, Portal Kecamatan = V3.

## Struktur
- `src-tauri/` — shell desktop (Rust). Menjalankan PHP sidecar (`php artisan serve`) lalu membuka window ke `http://127.0.0.1:<port>/`.
- `bootstrap/app.php` — jika `SMARTRKAS_DATA_DIR` diset, storage di-relokasi ke `{DATA}/storage` dan skeleton dir dibuat.
- `app/Console/Commands/AppInstall.php` — `php artisan app:install`: migrate + seed (dipanggil Tauri saat first-run).
- `src-tauri/php/` — runtime PHP yang dibundel (lihat `README.md` di folder itu).

## Env penting (di-set oleh Tauri saat runtime)
- `SMARTRKAS_DATA_DIR` — data user (AppData). DB ada di `{DATA}/smartrkas.sqlite`.
- `DB_DATABASE` — path absolut DB SQLite.
- Larangan: **jangan pernah `config:cache`** di produksi — DB path dibaca runtime dari env.

## Verifikasi M0 (sudah lulus)
- `cargo check` di `src-tauri/` → OK.
- Smoke test: `SMARTRKAS_DATA_DIR` + `DB_DATABASE` di-set → `app:install` → `artisan serve` → `/login` HTTP 200, storage terelokasi.
- `php artisan test` → 25 pass.

## Command rutin
- `php artisan test` — PHPUnit.
- `vendor/bin/phpstan analyse` — level 6.
- `npm run tauri dev` — jalankan desktop dev (membutuhkan PHP di PATH).
- `npm run tauri build` — build installer (membutuhkan PHP dibundel di `src-tauri/php/`).

## Sesi
- **05 Agu 2026 (M0)** — Bootstrap: Laravel 12 + Breeze(blade) + SQLite, dep inti
  (excel, dompdf, backup, larastan), `phpstan.neon` level 6, command `app:install`,
  relokasi storage, shell Tauri 2 (spawn PHP server, first-run install, kill saat
  tutup), ikon, git init. Belum ada modul bisnis — mulai M1.
- **05 Agu 2026 (M1)** — Master Data & pengaturan:
  - 14 migrasi (pengaturan_sekolah, tahun_anggaran, sumber_dana, jenis_belanja,
    master_program, master_kode_rekening, rkas_item, rkas_item_bulan, transaksi_bku,
    kwitansi, audit_log, import_log, outbox, +auth_columns di users).
  - 14 model + 11 factory. Semua model PK UUID wajib `use HasUuids` (5 model tanpa
    HasUuids semula gagal create → sudah ditambah).
  - `routes/auth.php` = hanya login/logout/confirm-password/password.update.
    `routes/web.php` = dashboard, profile, pengaturan-sekolah, resource
    tahun-anggaran/sumber-dana/jenis-belanja/master-program/master-kode-rekening
    (+ import, download-template, hapus-semua, set-active).
  - Controller M1: Dashboard (single-sekolah, tanpa kecamatan), 5 resource master,
    PengaturanSekolah (edit/update). Import/export: `MasterProgramImport` (sheet 1
    "KEGIATAN"), `MasterKodeRekeningImport` (prefix rules → 8 JenisBelanja),
    `MasterKodeRekeningTemplateExport`.
  - DatabaseSeeder: user admin@sekolah.test / password, PengaturanSekolah default,
    TahunAnggaran 2026 aktif, SumberDana (BOSP-REG/KIN), 8 JenisBelanja.
  - Login: cek `is_active` (blokir nonaktif) + update `last_login_at`.
  - Views: layout app/guest + navigation dari sira-rkas (dibuang admin-kecamatan,
    hanya menu yang route-nya ada), `app.css` 562 baris disalin, `tailwind.config`
    + `darkMode: 'class'`, 16 view master/dashboard/pengaturan-sekolah.
  - Fitur Breeze dibuang: register, forgot/reset password, verify email, hapus akun
    (test + controller terkait dihapus).
  - `Outbox::push()` → `Outbox::record()` (menimpa `Model::push()` non-static).
  - Verifikasi: `migrate:fresh --seed` OK, `route:list` OK, `npm run build` OK,
    `php artisan test` 28 pass, PHPStan level 6: 0 error.
  - Test M1: `tests/Feature/MasterDataTest.php` (13 kasus: CRUD semua master,
    set-active, dashboard, login is_active/last_login_at, template, hapus-semua).

## M2 (belum mulai)
- SIRA RKAS: index monitoring CRUD + import Excel (RkasImport + header detector 2
  baris, renumber, sync-jumlah, dedup) + resource rkas/import-rkas + view.
- Transaksi BKU + Kwitansi, Laporan + Export (Excel/PDF), Backup.
- Tambah route/menu navigation secara bertahap seiring fitur selesai (jangan
  pasang link ke route yang belum ada).
