# SmartRKAS — Catatan Pengembangan

Proyek target Laravel untuk aplikasi RKAS (versi tanpa `ProfilSekolah`/`sekolah_id`). Referensi/asal fitur ada di `D:\aplikasi sekolah\New folder\sira-rkas` (repo terpisah, punya `sekolah_id` + `RkasItemObserver` — TIDAK ada di sini).

## Perbedaan Kunci vs sira-rkas (referensi)
- TIDAK ada model `ProfilSekolah`, kolom `sekolah_id`, atau `app/Observers/RkasItemObserver.php`.
- Struktur: `RkasItem` → `RkasItemBulan` (rencana per bulan), `TransaksiBku`, `MasterProgram`, `MasterKodeRekening`, `SumberDana`, `TahunAnggaran`, `ImportLog`, `AuditLog`, `PengaturanSekolah`, `Kwitansi`, `Outbox`, `User`.
- Semua id model utama adalah **UUID** (`HasUuids`) — jangan cast `(int)` untuk membandingkan id (mis. hasil JSON select2).

## Setup & Verifikasi
- `phpunit.xml`: sqlite `:memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`.
- `phpstan.neon`: level 6, path mencakup `app/`, `config/`, `database/factories/`, `tests/`.
- Test: `vendor\bin\phpunit` · PHPStan: `vendor\bin\phpstan analyse --no-progress`.

## Fitur Import RKAS
- `app/Imports/RkasImport.php` — konstruktor `(string $tahunAnggaranId, int $bulan, string $sumberDanaId, string $importLogId, ?int $headingRow = null, ?array $columns = null, ?int $startRow = null)`. Tanpa `WithHeadingRow`; identitas item via `(tahun, sumber_dana, program, kode_rekening)` + `normalizeUraian`, bukan `no_urut`.
- `app/Imports/RkasImportHeaderDetector.php` — `detectColumns(string $filePath): array{start_row: int, columns: array<string,int>}`. Header 2 baris (file PRD): baris 8 = no_urut/kode_rekening/kode_program/uraian/jumlah, baris 9 = volume/satuan/tarif; akses kolom = `$row[columns[field]-1]` (0-based).
- `app/Jobs/ProcessRkasImport.php` — SYNCHRONOUS (tanpa `ShouldQueue`), `(string $importLogId, string $filePath)`. Alur: hapus `rkas_item_bulan` bulan tsb (idempoten) → `detectColumns` → `Excel::import` → `renumber` (no_urut 1..N) → `syncJumlah` (jumlah = sum rencana) → AuditLog `tabel='import_rkas', aksi='import'` → hapus file + null-kan `file_path`.
- `ImportRkasController` — `store()` per file bulan: validasi `files.*` (xlsx/xls/csv, max 5MB) + `sumber_dana_id`; simpan ke `storage/app/import_rkas/`; dispatch job; rute `GET/POST /import-rkas`, `/import-rkas/download-template`, `/import-rkas/status`.

## Key Patterns
- **`increment()` pada kolom NULL TIDAK berfungsi** (`NULL + 1 = NULL` di MySQL & SQLite). `ImportLog` wajib punya default `baris_berhasil=0, baris_gagal=0` (model `$attributes`) sebelum di-`increment`.
- `RkasController::destroyAll` (POST `/rkas/hapus-semua`): bila `tahun` input tidak ditemukan → fallback ke tahun aktif; error hanya saat 0 item cocok.
- `with(['rel' => fn (Relation $q) => ...])` menerima `Relation`; `withSum([... => fn (Builder $q)])` menerima `Builder`.
- `updateQuietly` dipakai untuk recompute `no_urut`/`jumlah` agar tidak memicu observer/audit noise.
- `Excel::store` (FromArray) membuang baris kosong `[]` — untuk test layout baris persis, isi baris antara dengan `' '`.

## Route (sebagian)
- `/rkas`, POST `/rkas/hapus-semua`, `/rkas/{item}/edit|update|delete`
- `/rkas-items/select2` (param `q`, `exclude[]`, `bulan` → sisa kumulatif "Sisa s.d. bulan N")
- `/import-rkas`, `/import-rkas/download-template`, `/import-rkas/status`
- resource: `tahun-anggaran`, `sumber-dana`, `jenis-belanja`, `master-program`, `master-kode-rekening` (+ import/hapus-semua)
- Semua di bawah middleware `auth`; guest diarahkan ke `/login`.

## Command
- `rkas:dedup` (`DeduplicateRkas`) — gabung duplikat via uraian+program+rekening+sumber dana; `--sekolah` tidak ada di sini (tanpa sekolah_id); opsi `--dry-run`.
- `rkas:renumber` (`RenumberRkas`) — no_urut unik 1..N.
- `rkas:sync-jumlah` (`SyncRkasJumlah`) — `jumlah` = sum rencana semua bulan (termasuk soft-deleted).
- `app:install` (`AppInstall`) — setup awal.

---

# Sesi 05 Agu 2026 — Porting Test RKAS ke SmartRKAS + Fix ImportLog NULL

## Goal
Port/adapt test suite RKAS import dari repo referensi sira-rkas ke proyek target `SmartRKAS` (tanpa `ProfilSekolah`/`sekolah_id`/`RkasItemObserver`), tambah feature test `RkasController` + `ImportRkasController`, dan jadikan PHPStan level 6 + full suite hijau.

## Summary
- 9 file test ditulis/diadaptasi; 114 test lulus (328 assertions), PHPStan level 6: 0 error.
- Ditemukan **bug produksi nyata**: `ImportLog::create` di controller tidak mengisi `baris_berhasil`/`baris_gagal` → NULL, dan `increment()` pada NULL menghasilkan NULL (`NULL + 1 = NULL` di MySQL & SQLite) → job selalu membaca "0 baris" dan menandai import gagal.

## Changes
- `app/Models/ImportLog.php` — tambah `protected $attributes = ['baris_berhasil' => 0, 'baris_gagal' => 0]`.
- `tests/Feature/RKAS/RkasControllerTest.php` — `test_destroy_all_returns_error_when_no_match` tidak membuat item (fallback tahun aktif di `RkasController::destroyAll`).
- `tests/Feature/RkasItemSelect2Test.php` — `findResult()` bandingkan id sebagai string (UUID).
- `tests/Feature/Import/ImportRkasControllerTest.php` — hapus blok debug `fwrite(STDERR)`.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (114 tests, 328 assertions)`.

---

# Sesi 05 Agu 2026 — M4 Fitur Laporan (BKU, Rekap Realisasi, Tribulan, SIPLAH) + Export Async

## Goal
Port modul Laporan dari referensi sira-rkas: 4 laporan (BKU, rekap rekening, rekap kuartal, rekap SIPLAH) dengan view PDF + web-preview + export Excel async, disesuaikan ke target (PK UUID, tanpa `sekolah_id`/admin-kecamatan/`ProfilSekolah`, identitas sekolah via `PengaturanSekolah::get()`).

## Summary
- 37 test baru (15 `LaporanTest`, 9 `ExportTest`, 5 `GenerateExportJobTest`, 8 test factory dll) → total **172 tests (468 assertions)**, PHPStan level 6: 0 error.
- PHPStan menemukan properti dinamis yang dipakai di controller/export (`sisa_bulan`, `nama`, `m0..m2`, `jenis_belanja`, `total`, `siplah`, `non_siplah`, `belum_diisi`, `persen_*`) → diselesaikan dengan `@property` di model (bukan `@phpstan-ignore`).

## Changes
- **Export async**: migration `create_export_jobs_table`, model `ExportJob` (+ `exportJobs()` HasMany di User), factory, job `GenerateExportJob` (sync queue), controller `ExportController` (route `exports/{exportJob}/download|status`, cek kepemilikan user).
- **Export class**: `RekapRekeningExport` (manual `leftJoinSub`, alias `tb`), `RekapKuartalExport` (FromArray + column widths), `RekapSiplahExport` (tanpa `withoutGlobalScope('sekolah')`).
- `LaporanController` — `index`, `bku`, `rekapRekening`, `rekapKuartal`, `rekapSiplah` (+PDF), 4× Web, 4× ExportExcel; helpers private (resolve periode/tahun anggaran, data BKU/rekap/SIPLAH/kuartal). Branch cetak=pdf **tidak** melempar `$bulan` → view PDF pakai `@unless(request('cetak')=='pdf')` untuk tombol print.
- **Views `resources/views/laporan/`** (10 file) — card grid index, sidebar ikon per laporan (green/blue/amber/violet), `data-table` tfoot dark, tombol cetak di luar tabel.
- Routes: `laporan.index`, `laporan.bku` + preview/export-excel, `laporan.rekap-*`, `exports.*` di grup auth.
- `app/Models/RkasItem.php` + `TransaksiBku.php` — tambah `@property` untuk atribut dinamis.
- `resources/views/laporan/rekap-rekening-kuartal.blade.php` TIDAK dibuat (referensi punya admin-kecamatan; target tidak).

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (172 tests, 468 assertions)`.

---

# Sesi 05 Agu 2026 — M5 Dashboard Lengkap (Tabel Item RKAS Dinamis)

## Goal
Lengkapi dashboard dengan tabel item RKAS dinamis (rencana/realisasi/sisa + volume + status badge + pagination 50) yang sebelumnya `$rkasItems = collect()`, diadaptasi dari referensi sira-rkas (tanpa `withoutGlobalScope('sekolah')`).

## Summary
- 6 test baru → total **178 tests (490 assertions)**, PHPStan level 6: 0 error.

## Changes
- `app/Http/Controllers/DashboardController.php` — tambah query item dinamis: `(clone $baseQuery)->with(['program','kodeRekening.jenisBelanja','transaksiBkus'(filter jenis pengeluaran+bulan),'bulanRencana'(filter bulan)])->orderBy('no_urut')->paginate(50)`. Per-item: `dynamic_rencana` (rencana bulan terpilih atau `jumlah`), `dynamic_realisasi` (sum transaksi pengeluaran), `dynamic_sisa`, `persentase`, `dynamic_*_volume` (= nominal ÷ tarif). **Bug potensial**: init fallback `$rkasItems = collect()` harus diletakkan SEBELUM blok `if ($tahunAnggaranAktif)` — jika sesudahnya akan menimpa hasil pagination.
- `app/Models/RkasItem.php` — tambah `@property float $dynamic_rencana|$dynamic_realisasi|$dynamic_sisa|$dynamic_rencana_volume|$dynamic_realisasi_volume|$dynamic_sisa_volume`.
- `resources/views/dashboard.blade.php` — card "Detail Rencana &amp; Realisasi per Item" setelah card filter: kolom Uraian(+no urut), Program, Kode Rekening(+badge jenis belanja), Rencana(+volume), Realisasi(+volume), Sisa, Status badge (Over Budget/Hampir Habis/Belum Realisasi/Normal), `$rkasItems->links()`, empty-state.
- `tests/Feature/Dashboard/DashboardTest.php` — 6 test: guest redirect, kartu statistik, isi tabel item, filter bulan (rencana per-item), badge Over Budget, pagination 55 item. **Catatan**: 55 item via factory default membuat 55 JenisBelanja unik → Faker `unique()` overflow; test pakai 1 Program + 1 KodeRekening bersama.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (178 tests, 490 assertions)`.

---

# Sesi 05 Agu 2026 — M6 Ops: Scheduling Cleanup + Hapus Akun

## Goal
Port command `audit:clean` + jadwal backup/cleanup dari referensi sira-rkas ke `routes/console.php` target, dan lengkapi hapus akun (route + view `delete-user-form`), karena `ProfileController::destroy` sudah ada.

## Summary
- 6 test baru (2 `AuditCleanCommandTest`, 4 `ProfileDeleteTest`) → total **184 tests (507 assertions)**, PHPStan level 6: 0 error.

## Changes
- `app/Console/Commands/CleanAuditLog.php` — signature `audit:clean {days=90}`, hapus `AuditLog::where('created_at','<',cutoff)->delete()`. Auto-registered (default discovery `app/Console/Commands`).
- `routes/console.php` — jadwal: `backup:clean` 01:00 harian, `backup:run` 01:30 harian, `audit:clean 90` Minggu 02:00, hapus `failed_jobs` >30 hari Minggu 03:00 (`Schedule::call` + `DB::table`), `kwitansi:clean 2` bulanan 04:00. `php artisan schedule:list` → 5 entri OK.
- `routes/web.php` — `Route::delete('/profile', [ProfileController::class,'destroy'])->name('profile.destroy')`.
- `resources/views/profile/partials/delete-user-form.blade.php` — diadaptasi dari referensi (pakai `x-modal`, `x-input-label/x-text-input/x-input-error`, `btn-danger`/`btn-secondary`; target sudah load Alpine via `app.js`).
- `resources/views/profile/edit.blade.php` — tambah include `delete-user-form`.
- `tests/Feature/Console/AuditCleanCommandTest.php` — old vs recent log, default 90 hari.
- `tests/Feature/Auth/ProfileDeleteTest.php` — hapus dengan password benar/salah (`assertSessionHasErrorsIn('userDeletion','password')`), guest redirect, page edit menampilkan form.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (184 tests, 507 assertions)`.

---

# Sesi 05 Agu 2026 — M7 Index Kinerja DB

## Goal
Port migration index kinerja DB dari referensi sira-rkas, disesuaikan ke skema target (tanpa `sekolah_id`), untuk mempercepat query dashboard/laporan/cleanup yang sering dijalankan.

## Summary
- 5 test baru (`DatabaseIndexTest`) → total **189 tests (513 assertions)**, PHPStan level 6: 0 error. `migrate:fresh` (testing) OK.

## Changes
- `database/migrations/2026_08_05_000016_add_performance_indexes.php` — `transaksi_bku`:
  - `transaksi_bku_item_jenis_bulan_idx` (`rkas_item_id`, `jenis`, `bulan`) — query per-item realisasi dashboard/laporan.
  - `transaksi_bku_jenis_bulan_idx` (`jenis`, `bulan`) — agregat BKU/rekap.
  - `rkas_item` TIDAK ditambah (index `[tahun_anggaran_id, no_urut]` sudah ada di create migration).
- `database/migrations/2026_08_05_000017_add_missing_indexes.php`:
  - `kwitansi_transaksi_bku_idx` (`transaksi_bku_id`).
  - `import_log_tahun_bulan_idx` (`tahun_anggaran_id`, `bulan`) — status import dashboard.
  - `audit_log_created_at_idx` (`created_at`) — mempercepat `audit:clean` (`where created_at < cutoff`).
- `tests/Feature/Database/DatabaseIndexTest.php` — cek keberadaan index via `Schema::hasIndex` (incl. unique `rkas_item_bulan`).

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (189 tests, 513 assertions)`.

---

# Sesi 05 Agu 2026 — M8 Command Reset Password

## Goal
Port command reset password dari referensi sira-rkas untuk operasional (user lupa password tanpa mail server).

## Summary
- 4 test baru → total **193 tests (529 assertions)**, PHPStan level 6: 0 error.

## Changes
- `app/Console/Commands/ResetUserPassword.php` — signature `user:reset-password {user : ID (int) atau email user} {password? : Password baru (kosong = generate acak)}`. Argumen dicari via email dulu, lalu `id` — user id adalah **integer** (`$table->id()` di `0001_01_01_000000_create_users_table.php`), BUKAN UUID; gunakan `ctype_digit`, jangan `Str::isUuid`. Tanpa argumen `password` → `Str::password(12)` ditampilkan via `$this->warn`.
- `tests/Feature/Console/ResetUserPasswordCommandTest.php` — 4 test (reset via email, via id, generate acak, user tidak ditemukan).

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (193 tests, 529 assertions)`.

---

# Sesi 05 Agu 2026 — M9 Security Review

## Goal
Audit keamanan dasar: XSS tersimpan, validasi upload, CSRF, mass assignment, authz export.

## Summary
- 3 test baru (`SecurityTest`) → total **196 tests (536 assertions)**, PHPStan level 6: 0 error.

## Changes
- **XSS flash**: `{!! session('success') !!}` → `{{ session('success') }}` di `resources/views/rkas/index.blade.php`, `resources/views/transaksi-bku/create.blade.php`, `resources/views/transaksi-bku/edit.blade.php` (flash `success` dari `ImportRkasController` berisi nama file user).
- **Batas ukuran upload**: tambah `|max:5120` ke validasi import `MasterProgramController` dan `MasterKodeRekeningController` (sebelumnya hanya `mimes:xlsx,xls,csv`). `ImportRkasController` sudah punya batas 5MB.
- `tests/Feature/Security/SecurityTest.php` — escaping flash (`assertSee('<script>...')` escaped / `assertDontSee(..., false)` raw; hati-hati `assertSee` meng-escape argumen sendiri) + tolak file >5MB untuk kedua master import.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (196 tests, 536 assertions)`.

---

# Sesi 05 Agu 2026 — M10 Notifikasi Telegram

## Goal
Port notifikasi Telegram dari referensi: job async + listener event backup Spatie + log channel custom, agar error/backup terpantau via chat Telegram.

## Summary
- 6 test baru (`TelegramNotificationTest`) → total **202 tests (542 assertions)**, PHPStan level 6: 0 error.

## Changes
- `app/Jobs/SendTelegramNotificationJob.php` — `ShouldQueue`, `tries=3`, `backoff=[2,10]`, cache lock `telegram-notification` 5 dtk (rate limit), POST `https://api.telegram.org/bot{token}/sendMessage` (HTML), baca `config('logging.telegram_bot_token')`/`telegram_chat_id`; kosong → skip.
- `app/Listeners/NotifyBackupTelegram.php` — `handle(object $event)` + `match(true)` untuk 6 event Spatie Backup (success/failed/cleanup/healthy/unhealthy) → dispatch job INFO/ERROR/WARNING.
- `app/Logging/TelegramLogHandler.php` — `Monolog\Handler\AbstractProcessingHandler`, hanya level ≥ Error (`Level::Error->includes`), sanitasi key sensitif (password/token/db_password → `[REDACTED]`), extra berisi `url` + `user_email` (via `$user instanceof User`, bukan akses properti dinamis).
- `app/Providers/AppServiceProvider.php` — `Event::listen` 6 event backup ke `NotifyBackupTelegram`.
- `config/logging.php` — channel `telegram` (level `LOG_TELEGRAM_LEVEL` default error) + key `telegram_bot_token`/`telegram_chat_id` (env `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`).
- **Catatan test**: jangan fire event backup via `event()` langsung (spatie `EventHandler` bawaan ikut jalan dan butuh notifiable); panggil `(new NotifyBackupTelegram)->handle($event)` + `Queue::fake()` (pola sama dgn referensi `NotifyBackupTelegramTest`).

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (202 tests, 542 assertions)`.

---

# Sesi 05 Agu 2026 — M11 Auth Breeze Lengkap (Register + Lupa/Reset Password)

## Goal
Port register + forgot-password + reset-password dari referensi ke target. Verifikasi email TIDAK ada di target (skema hanya `email_verified_at` nullable). Catatan: fitur reset via email butuh mail server — untuk deployment desktop (Tauri + `SMARTRKAS_DATA_DIR`) lebih praktis pakai `user:reset-password` (M8).

## Summary
- 7 test baru (3 `RegistrationTest`, 4 `PasswordResetTest`) → total **209 tests (548 assertions)**, PHPStan level 6: 0 error.

## Changes
- `routes/auth.php` — tambah grup `guest`: `register` (GET/POST), `forgot-password` (GET `password.request` / POST `password.email`), `reset-password/{token}` (GET `password.reset` / POST `password.store`).
- `app/Http/Controllers/Auth/RegisteredUserController.php` — validasi name/email unique/password confirmed (`Rules\Password::defaults()`), `Auth::login`, redirect dashboard. User baru `is_active` default `true`.
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` — `Password::sendResetLink`; `store` pakai `$status == Password::RESET_LINK_SENT`.
- `app/Http/Controllers/Auth/NewPasswordController.php` — `Password::reset` + `forceFill` (password hash baru + `remember_token`), `event(new PasswordReset)`, redirect login dengan `status`.
- Views `resources/views/auth/register.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php` — pola sama dgn login (`form-label`/`form-input`/`btn-primary`/`alert-success`); reset-password baca `$request->route('token')`.
- `resources/views/auth/login.blade.php` — tambah link "Lupa password?" (`@if (Route::has('password.request'))`).
- `tests/Feature/Auth/RegistrationTest.php` + `PasswordResetTest.php` — pakai `Notification::fake()` + `ResetPassword` notification + `Notification::assertSentTo`.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (209 tests, 548 assertions)`.

---

# Sesi 05 Agu 2026 — Branding: Icon SmartRKAS

## Goal
Manfaatkan `icon smartrkas.png` (500×500, di root project) sebagai identitas aplikasi: favicon web, logo login (guest layout), logo sidebar, dan icon desktop Tauri.

## Changes
- `public/icons/smartrkas.png` — salinan ikon (dipakai web/Tauri webview; `frontendDist` = `../public`, jadi path `/icons/smartrkas.png`).
- `resources/views/layouts/app.blade.php` + `guest.blade.php` — tambah `<link rel="icon" type="image/png" href="/icons/smartrkas.png">`.
- `resources/views/layouts/guest.blade.php` — ganti placeholder teks "SR" (panel kiri `guest-logo` + header mobile) dengan `<img src="/icons/smartrkas.png">` (`object-contain`).
- `resources/views/layouts/navigation.blade.php` — `sidebar-logo-icon` berisi teks "SR" → `<img class="w-full h-full object-contain">`.
- **Tauri**: `npm run tauri -- icon "icon smartrkas.png"` regenerasi semua `src-tauri/icons/*` (32/64/128/128@2x, icon.ico, icon.icns, Square*, android/ios). Salinan ikon ditaruh di `src-tauri/app-icon.png` + `src-tauri/icons/source.png` agar `tauri icon` (tanpa argumen) memakainya lagi.

## Test Status
- Full suite tetap hijau: `OK (209 tests, 548 assertions)`. Tidak ada perubahan logika app.

---

# Sesi 05 Agu 2026 — M12 Backup Desktop + UI Backup & Riwayat Aktivitas

## Goal
Selesaikan 3 item pasca M11: (1) backup terjadwal di mode desktop Tauri, (2) halaman Backup (UI) + halaman Riwayat Aktivitas (viewer AuditLog), (3) verifikasi `tauri build` (nsis/msi) + dokumentasi. Manajemen pengguna & role DIBATALKAN — konfirmasi user: **1 user per sekolah**.

## Summary
- 12 test baru (7 `BackupPageTest`, 5 `AuditLogPageTest`) → total **221 tests (580 assertions)**, PHPStan level 6: 0 error. `npm run tauri -- build` sukses (nsis + msi).
- **Temuan**: scheduler `routes/console.php` tidak berjalan di desktop (Tauri hanya spawn `artisan serve`); `src-tauri/php/` kosong (bundling PHP manual); `README.md` masih default Laravel.

## Changes
- `src-tauri/src/lib.rs` — `struct PhpServer(Mutex<Option<Vec<Child>>>)`; spawn `["artisan","schedule:work"]` (wait=false) bersama `artisan serve`; `on_window_event` (CloseRequested) kill+wait semua children lalu `app.exit(0)`.
- `app/Http/Controllers/BackupController.php` — konstruktor baca `config('backup.backup.name','laravel-backup')` (env `APP_NAME`) sbg dir di `Storage::disk('local')`; `index()` list `.zip` (name/path/size/mtime) sort mtime desc; `run()` = `Artisan::call('backup:run')` try/catch → flash success/error; `download(string $file)` validasi `basename($file)===$file` + `str_ends_with('.zip')` → 404, lalu `Storage::disk('local')->download`.
- `app/Http/Controllers/AuditLogController.php` — `AuditLog::with('user')->latest()`; filter `tabel` (opsi dari distinct) + `q` (tabel/aksi/user name/email); `paginate(50)->withQueryString()`; `$tabels` distinct ordered.
- `routes/web.php` (grup auth): GET `pengaturan/backup` (`pengaturan.backup.index`), POST `pengaturan/backup/now` (`pengaturan.backup.now`), GET `pengaturan/backup/download/{file}` (`pengaturan.backup.download`), GET `pengaturan/riwayat-aktivitas` (`pengaturan.audit.index`).
- `resources/views/pengaturan/backup.blade.php` — kartu statistik (jumlah/total ukuran/backup terakhir via `\Carbon\Carbon::createFromTimestamp`), form POST "Backup Sekarang", tabel zip + tombol Unduh, empty-state.
- `resources/views/pengaturan/audit-log.blade.php` — filter GET (select `tabel` + `q` + reset), tabel Waktu/User/Jenis (`Str::headline($log->tabel)`)/Aksi (badge)/Detail (summary `data_baru ?? data_lama`), paginasi.
- `resources/css/app.css` — tambah `.alert-info` (sky). **Catatan**: badge hanya `badge-green/red/orange/yellow/blue/purple/gray` — peta aksi di audit view memakai `badge-green/blue/red/yellow` + `badge-gray` default, dan pakai `<span class="badge badge-...">`.
- `resources/views/layouts/navigation.blade.php` — link sidebar Pengaturan: Profil Sekolah, **Backup & Pemulihan**, **Riwayat Aktivitas**.
- `README.md` — ganti konten default Laravel dgn dokumentasi lengkap (mode web/desktop, env, jadwal scheduler, CLI, testing, build Tauri + langkah bundle PHP).
- Tests: `tests/Feature/Backup/BackupPageTest.php` (guest redirect, list, empty-state, run via `Artisan::spy()` — **`Artisan::fake()` tidak ada di kernel ini**, dan `->with()` pada `LegacyMockInterface` bikin PHPStan error → cukup `shouldHaveReceived('call')`), download valid, traversal (`..%2F..%2F.env` → 404), file tak dikenal); `tests/Feature/Audit/AuditLogPageTest.php` (guest redirect, list, filter `tabel`, search user, empty-state).

## Catatan
- `Storage::fake('local')` mengarahkan `Storage::disk('local')` (root `storage/app`) ke dir temp → daftar zip + download bisa diuji tanpa file nyata.
- Route `download/{file}` tidak bisa menerima `/` dalam parameter → traversal dibatasi `basename` check.
- Backup restore = unduh file `.zip` (tanpa restore dari UI, sesuai keputusan user).
- `src-tauri/php/` kosong → installer tidak membundle PHP; untuk rilis final salin folder PHP (pdo_sqlite, sqlite3, mbstring, zip) ke `src-tauri/php/` sebelum build (resource `php/` di `tauri.conf.json`).
- Build desktop terverifikasi: `SmartRKAS_0.1.0_x64-setup.exe` (nsis) + `SmartRKAS_0.1.0_x64_en-US.msi` (msi) di `src-tauri/target/release/bundle/`.

## Test Status
- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (221 tests, 580 assertions)`.

---

# Sesi 05 Agu 2026 — M12b Bundle PHP ke Desktop + Fix Serve Child

## Goal
Bundle PHP 8.2 (XAMPP) ke `src-tauri/php/` agar installer desktop mandiri (tanpa PHP di PATH). Ditemukan 2 bug tersembunyi pada mode desktop selama verifikasi.

## Summary
- `src-tauri/php/` kini berisi PHP bundle ~73MB (gitignore `src-tauri/.gitignore` → TIDAK ikut git). Build ulang sukses: NSIS 57.6MB, MSI 86.3MB.
- **Bug 1 (env child server)**: `Illuminate\Support\php_binary()` memakai `PhpExecutableFinder->find(false)` yang mencari `$dirs = [PHP_BINDIR, 'C:\xampp\php\']` dulu baru PATH → PATH-prepend tidak cukup; tapi PHP_BINDIR php bundle = dir bundle, jadi child otomatis memakai php bundle. Yang PENTING: mode `artisan serve` default (reload) memfilter env child ke passthroughVariables saja → **`SMARTRKAS_DATA_DIR`/`DB_DATABASE` dari Rust TIDAK sampai ke server web** → di instalasi Program Files (read-only) DB salah/error. Perbaikan: tambah `--no-reload` (env full dilewatkan; terverifikasi: set `DB_DATABASE` palsu → halaman 500, artinya terpropagasi).
- **Bug 2 (bind gagal)**: `artisan serve` default (reload) dengan php bundle gagal bind `Failed to listen (reason: ?)`; `--no-reload` menyelesaikan (repro terisolasi Symfony Process berhasil; masalah hanya saat mode reload). Dengan `--no-reload` serve berjalan normal.

## Changes
- `src-tauri/src/lib.rs` — `php_dir()` + `prepend_php_to_path()` (PATH di-prepend dir php bundle) dipakai di `run_php()` & spawn serve; spawn `serve` tambah argumen **`--no-reload`**.
- `src-tauri/php/` — PHP 8.2.12 ZTS (dari `C:\xampp\php`) versi kurasi ~73MB: php.exe, php8ts.dll, DLL dependency (libsqlite3, libssl/libcrypto, libsodium, ICU, nghttp2, glib/gmodule, libsasl, libssh2), license.txt, `ext/` (curl, fileinfo, gd, intl, mbstring, openssl, pdo_sqlite, sqlite3, zip, opcache), dan `php.ini` khusus (extension_dir relatif `ext`, `zend_extension = php_opcache.dll` resolve relatif ke extension_dir, upload_max_filesize 6M / post_max_size 12M / memory_limit 512M, timezone Asia/Jakarta, display_errors Off). TIDAK menyertakan pear/pci/php-cgi/phpdbg/XAMPP ini.
- `README.md` — sudah memuat langkah bundle PHP; instruksi tetap valid.

## Catatan
- Verifikasi bundle php: `.\src-tauri\php\php.exe artisan about` boot OK; `-S` direct OK; serve `--no-reload` + request `/login` OK. Jangan hapus `php.ini` bundle (tanpa ini framework gagal boot: `Class "ZipArchive" not found` di `config/backup.php:133`).
- `.env` ter-bundle di resource dir (read-only saat installed) → DB desktop WAJIB lewat env `DB_DATABASE`/`SMARTRKAS_DATA_DIR` yang di-set Rust; kini benar-benar sampai ke child server berkat `--no-reload`.
- Rebuild installer: `npm run build` lalu `npm run tauri -- build`. PHP bundle tidak perlu di-commit (gitignore).

## Test Status
- Tidak ada perubahan kode PHP/aplikasi → full suite tetap `OK (221 tests, 580 assertions)`, PHPStan clean. Hanya `src-tauri/src/lib.rs` + build artifact.
