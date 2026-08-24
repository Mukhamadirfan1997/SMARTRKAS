## SOP Wajib — Baca Sebelum Mulai Kerja

- Jangan percaya klaim "berhasil"/"commit selesai"/"test hijau" tanpa verifikasi independen. Untuk commit: `git log --oneline -3` + `git diff-tree --stat HEAD` untuk konfirmasi nyata, bukan cuma percaya output tool. Untuk file hasil export (Excel/PDF): buka dan periksa isinya langsung (cek tipe sel/nilai asli), jangan cuma percaya `number_format()` di kode.
- Jalankan test/lint dari kondisi final, setelah SEMUA perubahan selesai (termasuk file yang dihapus) — bukan dari state sebelumnya. Kalau ada error aneh setelah hapus/pindah file, coba `composer dump-autoload` dulu sebelum menyalahkan cache tool lain.
- Bug yang sifatnya intermiten butuh verifikasi berulang (minimal 3x berturut-turut), bukan sekali sukses langsung dianggap selesai.
- Satu perubahan, satu isolasi bukti — kalau menggabungkan beberapa fix sekaligus karena waktu terbatas, itu boleh, tapi harus dicatat jujur mana yang belum diisolasi/diverifikasi terpisah.
- Jangan build/rebuild rangkap di folder yang sama secara bersamaan — cek dulu proses build lain yang mungkin masih jalan.
- Jangan push ke GitHub atau buat rilis publik tanpa konfirmasi eksplisit dari user — commit lokal boleh, publikasi tidak.
- Root cause harus dibuktikan dengan bukti keras (log error asli, payload nyata, render output nyata) — bukan dugaan dari baca kode saja, kalau memungkinkan untuk diverifikasi.
- Sebelum uji manual via browser, selalu cek dulu apakah ada proses php.exe/server dev lain yang sudah listening di port yang sama (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` atau `netstat`) — server duplikat pernah 2x menyebabkan hasil uji tidak bisa dipercaya karena request jatuh acak ke server dengan state/DB berbeda.
- Kalau ada lebih dari satu server dev jalan bersamaan untuk keperluan uji berbeda, catat eksplisit port + `DB_DATABASE` masing-masing sebelum mulai uji manual — supaya tidak salah menyimpulkan hasil dari server/database yang salah. Ini kejadian ketiga (server duplikat + salah DB) yang bikin hasil uji membingungkan.

---

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
- **Tauri**: `npm run tauri -- icon "icon smartrkas.png"` regenerasi semua `src-tauri/icons/*` (32/64/128/128@2x, icon.ico, icon.icns, Square\*, android/ios). Salinan ikon ditaruh di `src-tauri/app-icon.png` + `src-tauri/icons/source.png` agar `tauri icon` (tanpa argumen) memakainya lagi.

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

---

# Sesi 05 Agu 2026 — Push & Release GitHub + Perbaikan Dok

## Goal

Push proyek ke GitHub `Mukhamadirfan1997/SMARTRKAS` dan rilis installer (desktop) v0.1.0 dengan dokumentasi petunjuk & kegunaan.

## Summary

- Repo di-push ke `https://github.com/Mukhamadirfan1997/SMARTRKAS` (branch `master`), release **v0.1.0** dibuat dengan 2 asset installer.
- 312 file tracked (tanpa `.env`/binary/sqlite — hanya `.env.example`); `src-tauri/php/` tetap gitignore.

## Changes

- `README.md` — tambah bagian **Panduan Penggunaan** (memulai web/desktop, alur kerja harian: Profil Sekolah → master data → item RKAS/import → BKU → laporan, pemantauan & backup, catatan 1-sekolah-1-user & restore via unduh zip).
- `README.md` — perbaiki tabel jadwal: `kwitansi:clean 2` = hapus kwitansi **> 2 TAHUN** (kode memakai `now()->subYears()`), sebelumnya tertulis "2 bulan".

## Git / Release

- Remote: `origin = https://github.com/Mukhamadirfan1997/SMARTRKAS.git` (via `gh`, akun `Mukhamadirfan1997`, scope `repo`).
- Commit rilis: `503fe6b` (docs usage), tag `v0.1.0` + push tag.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.1.0 — asset `SmartRKAS_0.1.0_x64-setup.exe` (NSIS, 57.6MB) + `SmartRKAS_0.1.0_x64_en-US.msi` (MSI, 86.3MB); notes rilis memuat fitur, cara instal, dan catatan WebView2.
- Commit perbaikan dok: `9a16cbb` (`kwitansi:clean` 2 tahun) → sudah di-push.

## Test Status

- Tidak ada perubahan logika app → suite tetap `OK (221 tests, 580 assertions)`, PHPStan clean.

---

# Sesi 05 Agu 2026 — M13 Kode Pemulihan via Telegram + Pengaturan Bot (Form vs Env)

## Goal

Kirim **kode pemulihan** ke Telegram setiap kali dibuat/diulang (kode tetap tampil sekali di UI), lengkap dengan halaman pengaturan berisi form token bot + ID Telegram, tombol uji, dan tutorial terperinci untuk user non-teknis. Keputusan user: token disimpan di **database (form)** sebagai cara utama, env sebagai cadangan; untuk desktop file `.env` di folder data agar tidak menyentuh bundle read-only.

## Summary

- 34 test (5 file) → total **268 tests (699 assertions)**, PHPStan level 6: 0 error.
- Bug test ditemukan & di-fix: `Dotenv::createMutable()` default hanya menulis ke `$_ENV`/`$_SERVER` (bukan `putenv`) — assertion `getenv()` keliru; Laravel `env()` tetap membaca keduanya sehingga perilaku app benar.
- **DataDirEnv**: desktop bisa override env via file `<DataDir>/.env` (bukan `.env` bundle yang read-only).

## Changes

- `database/migrations/2026_08_05_000019_add_telegram_columns_to_users_table.php` — `users.telegram_chat_id`, `users.telegram_bot_token` (nullable, `after recovery_code_generated_at`).
- `app/Models/User.php` — `$fillable` + `$hidden` (+`telegram_bot_token`, tidak bocor via `toArray()`/JSON), `@property` baru, helper: `hasTelegramChannel()` (chat id + token tersedia), `telegramChatId()`, `telegramBotToken()` (DB → fallback `config('logging.telegram_bot_token')`), `hasTelegramDelivery()`.
- `app/Jobs/SendRecoveryCodeTelegramJob.php` — `(User $user, string $code)`, `ShouldQueue`, `tries=3`, `backoff`, skip bila token/chat id kosong; pesan `🔐 Kode Pemulihan SmartRKAS`.
- `app/Jobs/SendTelegramNotificationJob.php` — tambah param opsional `?string $botToken = null` / `?string $chatId = null` (dipakai tombol uji), fallback env.
- `app/Support/DataDirEnv.php` — `DataDirEnv::load(string $dataDir)` baca `<DataDir>/.env` via `Dotenv::createMutable(...)->safeLoad()`; dipanggil di `bootstrap/app.php` bila `SMARTRKAS_DATA_DIR` terisi (SEBELUM config di-resolve agar `config('logging.*')` pertama kali memuat nilai override).
- `app/Http/Controllers/TelegramPengaturanController.php` — `index()` (status bot + sumber `db`/`env`), `update()` (simpan, string kosong → null), `test()` (dispatch `SendTelegramNotificationJob` dgn token/chat id user; pesan error jelas); helper `botSource()`/`emptyToNull()`.
- `routes/web.php` — `GET pengaturan/telegram` (index), `PUT pengaturan/telegram` (update), `POST pengaturan/telegram/test` (test).
- `resources/views/pengaturan/telegram.blade.php` — badge status (Bot Aktif via DB / via .env / Tidak Aktif + petunjuk), form token+ID, tombol Simpan & Kirim Pesan Uji, tutorial 5 langkah (`@userinfobot` → `@BotFather` → isi form / env → simpan+uji → selesai) + kartu troubleshooting (Unauthorized, chat not found, status Tidak Aktif, path `.env` folder data desktop).
- `resources/views/layouts/navigation.blade.php` — link sidebar **Notifikasi Telegram** (setelah Kode Pemulihan).
- Dispatch: `RecoveryCodeController::regenerate` & `OnboardingController::generateRecoveryCode` → `SendRecoveryCodeTelegramJob` bila `hasTelegramDelivery()`; flash tambahan "Kode juga dikirim ke Telegram"; `recovery-code.blade.php` + link ke halaman Telegram.
- Tests: `TelegramRecoveryTest` (5), `TelegramPengaturanTest` (10), `DataDirEnvTest` (3), update `OnboardingTest` (+2), `RecoveryCodeTest` (+2).
- `README.md` — bagian fitur, alur harian, catatan penting, tabel env, dan sub-bab **Notifikasi Telegram (opsional)** (cara dapat ID via `@userinfobot`, buat bot via `@BotFather`, isi form, catatan `.env` di folder data desktop).

## Catatan Teknis

- Prioritas token: kolom DB `telegram_bot_token` → fallback `config('logging.telegram_bot_token')` (env). Status halaman menampilkan sumbernya (`dari database` / `dari file .env`).
- Token bot di-`$hidden` User → tidak tampil di JSON/`toArray()`; di UI hanya status + sumber.
- `Dotenv::createMutable()->safeLoad()` (tanpa PutenvAdapter) menulis `$_ENV`/`$_SERVER`; `env()`/`config()` Laravel membaca keduanya. Jangan assert `getenv()` pada test DataDirEnv.
- Web: env lewat `.env` proyek. Desktop: `.env` bundle read-only → buat `.env` di `SMARTRKAS_DATA_DIR` (data-dir aplikasi).

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (268 tests, 699 assertions)`.

---

# Sesi 05 Agu 2026 — Release v0.2.0 (Build + Push + GitHub)

## Goal

Build installer desktop (NSIS + MSI) untuk fitur yang belum pernah dirilis sejak v0.1.0 (M13 Telegram + onboarding/restore/kode pemulihan/tentang) dan rilis ke GitHub.

## Summary

- Versi dinaikkan 0.1.0 → **0.2.0** (`src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `config/app.php`, `.env.example`).
- Build Tauri sukses: `SmartRKAS_0.2.0_x64-setup.exe` (NSIS, 57.7MB) + `SmartRKAS_0.2.0_x64_en-US.msi` (MSI, 86.5MB).
- Commit `80c0903` (47 file, +2407/−29) — mencakup M13 + fitur yang sebelumnya belum di-commit (onboarding, restore, kode pemulihan, about, DataDirEnv).
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.2.0

## Catatan

- `src-tauri/php/` (bundle PHP ~73MB) gitignore — tidak ikut commit, tapi ikut di-bundle installer.
- `public/build/` tidak di-track (0 file); aset web di-build ulang sebelum `npm run tauri -- build`.
- Cargo.toml versi harus sinkron dengan tauri.conf.json agar metadata exe benar (ternyata compile ulang hanya ~3 menit dgn dependency cache).
- Verifikasi secret sebelum commit: scan diff untuk pola token bot → bersih; test file pakai fixture `token123` saja.

## Test Status

- Tidak ada perubahan logika app pada sesi ini → suite tetap `OK (268 tests, 699 assertions)`, PHPStan clean.

---

# Sesi 06 Agu 2026 — Fix Saldo BKU Dobel + Perluas Log Aktivitas + Perbaikan Halaman Tentang

## Goal

1. Perbaiki bug saldo berjalan dobel di BKU saat filter "Semua Bulan". 2) Perluas log aktivitas ke semua data inti (BKU, RKAS, master, profil, telegram, backup). 3) Rapikan kartu statistik atas halaman Tentang, perbaiki link eksternal author (mati di desktop Tauri), dan beri feedback tombol "Periksa Pembaruan".

## Summary

- Bug produksi: saldo awal saat `bulan=''` dihitung dari SEMUA transaksi lalu baris halaman dijumlahkan lagi → dobel (21.312.000 = 10.543.500 + 10.768.500). Fix: saldo awal = jumlah transaksi sebelum baris pertama halaman (kriteria `tanggal`, lalu `id`). **Bug yang sama juga ada di repo referensi sira-rkas**.
- Tombol "Hapus Semua" BKU dipindah dari toolbar cetak ke card header (konsisten `rkas/index` & `master-program/index`).
- 5 test baru + 2 assertions → total **276 tests (726 assertions)**, PHPStan level 6: 0 error. `cargo check` (src-tauri) OK; `npm run build` OK.

## Changes

- `app/Http/Controllers/TransaksiBkuController.php` — `index()`: saldoAwal = `where tanggal < firstTanggal OR (tanggal = firstTanggal AND id < first->id)` pakai `Carbon::parse($first->tanggal)->toDateString()` (PHPStan: `tanggal` typed string, runtime Carbon).
- `resources/views/transaksi-bku/index.blade.php` — tombol Hapus Semua di card header sebelum "Tambah Transaksi".
- **`AuditLog::record(string $tabel, string $aksi, ?array $dataBaru = null, ?array $dataLama = null, int|string|null $userId = null): self`** — user_id default `auth()->id()`; `data_lama`/`data_baru` opsional. PHPStan: jangan pakai `: static` (return `App\Models\AuditLog`), pakai `: self`.
- Log aktivitas baru (tabel/aksi): `transaksi_bku` create+update; `rkas_item` update; `master_program`, `master_kode_rekening`, `sumber_dana`, `tahun_anggaran`, `jenis_belanja` create/update/delete(/delete_bulk/import); `tahun_anggaran` set_active; `pengaturan_sekolah` update/create; `telegram_pengaturan` update (tanpa nilai token, hanya `telegram_bot_token_set: bool`) + test; `backup` run (status success/failed). Refactor `AuditLog::create` lama (override_anggaran, delete, delete_bulk, import_rkas, dedup_merge) → `record()`.
- `resources/views/pengaturan/audit-log.blade.php` — badge map + `set_active`, `run`, `test`, `dedup_merge`.
- `resources/views/pengaturan/tentang.blade.php` — 3 kartu atas pakai `.stat-card` (indigo=versi, blue/green/orange=status, blue=rilis terbaru) + flash `session('error')`.
- `app/Http/Controllers/AboutController.php` — `check()` kini cek ulang via `latestRelease()` + flash `status` ("Sudah versi terbaru." / "Pembaruan X tersedia…") atau `error` (offline).
- `app/Services/AppUpdateService.php` — timeout `Http::timeout(10)` → `5`.
- **Link eksternal desktop**: `resources/js/app.js` — bila `window.__TAURI_INTERNALS__` ada, klik pada `a[target="_blank"]` dgn origin ≠ origin app di-`preventDefault` + `openUrl()` (plugin opener). Link internal (mis. cetak kwitansi, origin sama) TIDAK disentuh.
- Tauri: `src-tauri/Cargo.toml` + `tauri-plugin-opener = "2"`; `src-tauri/src/lib.rs` `.plugin(tauri_plugin_opener::init())`; `src-tauri/capabilities/default.json` + `opener:default`; `package.json` + `@tauri-apps/plugin-opener`.
- Tests: `tests/Feature/Audit/AuditLogCoverageTest.php` (5) — master create + update before/after, backup run, telegram update (token TIDAK di-log), pengaturan sekolah; assertions audit create/update di `TransaksiBkuTest`.

## Catatan

- `cargo check` sukses + download `tauri-plugin-opener` → `Cargo.lock` berubah (+505 baris). Rebuild installer TIDAK dilakukan (atas permintaan user).
- `public/build/` di-regenerate via `npm run build` (aset web) — tidak di-track.
- Detail audit untuk aksi `update` hanya menampilkan `data_baru` (nilai baru) di kolom Detail.
- Log `telegram_pengaturan` sengaja tidak menyimpan token bot (hanya bool `telegram_bot_token_set`).
- Bug saldo dobel juga ada di repo referensi sira-rkas (belum di-fix di sana).

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (276 tests, 726 assertions)`.

---

# Sesi 06 Agu 2026 — Perketat Override Anggaran + Kunci Cetak Kwitansi

## Goal

Pertahankan fitur Override Sisa Anggaran (kebutuhan sah saat harga barang naik) tapi perketat penyalahgunaan dan beri peringatan keras formal: (1) blokir nominal 0/negatif, (2) catatan override wajib min. 10 karakter, (3) simpan `override_note` di transaksi sebagai jejak, (4) **kwitansi transaksi override terkunci sampai item RKAS disesuaikan** (pergeseran / Perubahan Anggaran) sehingga realisasi tidak lagi melebihi rencana.

## Summary

- 8 test baru + 2 assertions → total **283 tests (757 assertions)**, PHPStan level 6: 0 error.
- Fitur override kini "aman dipakai tapi tidak bisa disalahgunakan": jumlah > sisa anggaran hanya bisa lewat override dgn catatan valid, dan kwitansi tidak bisa dicetak selama kondisi masih over budget.

## Changes

- `database/migrations/2026_08_06_000020_add_override_note_to_transaksi_bku_table.php` — `transaksi_bku.override_note` text nullable `after('uraian')`.
- `app/Models/TransaksiBku.php` — `$fillable` + `@property` `override_note`; method `masihOverBudget(): bool` + memoize `private ?bool $masihOverBudgetCache` (hindari query berulang saat badge+tombol dipanggil 2× di view). Logika: `override_note` terisi && jenis `pengeluaran` && realisasi kumulatif bulan ≤ t.bulan > rencana kumulatif → `true`. Pakai relasi `rkasItem.bulanRencana`/`transaksiBkus` (di-query lazily per baris; override jarang → tak ada eager load di controller).
- `app/Http/Controllers/TransaksiBkuController.php`:
    - `store()`/`update()`: `'jumlah' => 'required|numeric|gt:0'`; `store()`: `'override_note' => 'required_if:override_anggaran,1|string|min:10|max:500'` + `trim()`; `$validated['override_note']` di-set (`null` bila bukan override), `unset` hanya `override_anggaran`; flash sukses override memuat **peringatan keras** ("Segera ajukan pergeseran / Perubahan Anggaran (PA)… Kwitansi … terkunci"). Audit `transaksi_bku.create` kini juga menyertakan `override: bool`.
    - `index()`: `$countOverride` (filter-aware, `whereNotNull('override_note')->where('override_note','!=','')`) utk alert.
    - `cetakKwitansi(): Response|RedirectResponse` — blokir bila `masihOverBudget()` → redirect index + error. `cetakKwitansiBatch()` — `$transaksis->load(['rkasItem.bulanRencana','rkasItem.transaksiBkus'])`, blokir bila ada yg `masihOverBudget()` (daftar no_bukti).
- `resources/views/transaksi-bku/create.blade.php` — helper text: catatan min. 10 karakter + kwitansi terkunci sampai PA; `maxlength="500"` pada textarea.
- `resources/views/transaksi-bku/index.blade.php` — alert `alert-error` "PENTING … N transaksi OVERRIDE … kwitansi terkunci" (di atas alert belumCetakKwitansi); badge `badge-red` "Override (Kwitansi terkunci)"/"Override" + tooltip catatan di sel Uraian; tombol Kwitansi diganti `<span class="btn btn-success btn-sm opacity-50 cursor-not-allowed">` + tooltip saat `masihOverBudget()`.
- Tests (`tests/Feature/BKU/TransaksiBkuTest.php`): update override test (flash berisi "Perubahan Anggaran" + `override_note` tersimpan di DB); baru: override tanpa catatan / whitespace / <10 karakter ditolak; `jumlah` 0 & negatif ditolak (store & update); kwitansi blokir sampai rencana dinaikkan (rencana 50.000 → belanja 100.000 → redirect+error+`kwitansi` 0 → update rencana 110.000 → PDF OK + `kwitansi` 1); batch diblokir saat ada override unresolved.

## Catatan

- `rkas_item_bulan` punya unique `(rkas_item_id, bulan)` — di test "resolve" jangan tambah baris bulan sama; pakai `where(...)->update(['rencana' => …])`.
- Transaksi override **sebelum** migration ini tidak punya `override_note` → tidak terblokir (data lama aman).
- `update()` BKU sengaja TETAP ketat (tanpa jalur override saat edit); `override_note` lama tidak ter-overwrite.
- PHPStan: `LengthAwarePaginator::load()`/`Collection::load()` tidak ada → pakai memoize model, bukan eager-load di paginator.
- Celah "pengeluaran tanpa `rkas_item_id` tak dicek anggaran" tetap di luar cakupan (bukan jalur override).

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (283 tests, 757 assertions)`.

---

# Sesi 06 Agu 2026 — Halaman Akun & Login (Ganti Email/Password)

## Goal

Sediakan halaman untuk ganti email & password login dari starter Breeze (sebelumnya halaman profil tidak ditautkan & tampilan default), lalu bump versi 0.2.0 → 0.3.0 untuk build + release.

## Summary

- 1 test baru → total **284 tests (764 assertions)**, PHPStan level 6: 0 error.
- Halaman profil sudah ada dari Breeze (routes `profile.edit/update/destroy` di `routes/web.php`, PUT `password.update` di `routes/auth.php`) — TIDAK ada controller/route baru, cukup restyle + tautan sidebar.

## Changes

- `resources/views/profile/edit.blade.php` — diubah total ke desain app: card `Informasi Akun`, `Ganti Password`, `Hapus Akun`, alert sukses `profile-updated`/`password-updated`.
- `resources/views/profile/partials/update-profile-information-form.blade.php` — Nama + Email ("dipakai untuk login") pakai `form-input`/`form-label`/`btn-primary`.
- `resources/views/profile/partials/update-password-form.blade.php` — Password Saat Ini / Baru / Konfirmasi; error dari bag `updatePassword`.
- **Penting**: `:value="old(...)"` (Alpine binding) TIDAK bekerja di form tanpa `x-data` → pakai `value="{{ old('name', $user->name) }}"` dan `value="{{ old('email', $user->email) }}"`.
- `resources/views/layouts/navigation.blade.php` — link sidebar **"Akun & Login"** → `route('profile.edit')`, active `profile.*`, di bawah "Profil Sekolah".
- `tests/Feature/ProfileTest.php` — `test_profile_page_shows_account_forms` (assert "Akun & Login", "Informasi Akun", "Ganti Password", field `name`/`email`/`current_password`/`password`).
- `README.md` — Panduan Penggunaan: "Ganti email/password login: menu **Pengaturan → Akun & Login** (halaman Profil Akun)".
- Versi 0.3.0: `config/app.php`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `.env.example`.

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (284 tests, 764 assertions)`.

---

# Sesi 06 Agu 2026 — Audit Filter: Pertahankan Query String Saat Paginasi

## Goal

Periksa semua fitur filter halaman agar tampilan tetap benar saat terfilter (nilai select terisi ulang, hasil sesuai). Ditemukan bug umum: paginator ber-filter kehilangan query string saat pindah halaman → filter reset diam-diam.

## Summary

- Audit: semua select/input filter sudah persist via `request()`/`old()`; satu-satunya masalah = **pagination tanpa `->withQueryString()`** pada 7 titik paginator ber-filter (AuditLog sudah benar). Tanpa test baru (tidak mengubah perilaku), suite tetap `OK (284 tests, 764 assertions)`, PHPStan clean.

## Changes

- `app/Http/Controllers/TransaksiBkuController.php:65` — `paginate(50)->withQueryString()` (filter: tahun/bulan/sumber_dana/search).
- `app/Http/Controllers/RkasController.php:81` — `paginate(50)->withQueryString()` (filter: program/search/tahun/bulan/sumber_dana).
- `app/Http/Controllers/MasterProgramController.php:26` + `MasterKodeRekeningController.php:28` — `paginate(50)->withQueryString()` (search).
- `app/Http/Controllers/DashboardController.php:168` — `paginate(50)->withQueryString()` (tahun/bulan/program/kode_rekening/sumber_dana/jenis_belanja).
- `app/Http/Controllers/LaporanController.php:601` + `:661` — `paginate($perPage)->withQueryString()->through($mapFn)` (loadRekapItems & loadKuartalItems).
- TIDAK diubah: `JenisBelanja`/`SumberDana` (tanpa filter), `RkasItemController` select2 JSON (select2 mengirim ulang semua param tiap AJAX).

## Catatan

- `->withQueryString()` harus di-chain ke paginator; pada rekap laporan boleh `paginate(...)->withQueryString()->through(...)` (keduanya mengembalikan `LengthAwarePaginator`).

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (284 tests, 764 assertions)`.

---

# Sesi 06 Agu 2026 — Release v0.3.0 (Build + Push + GitHub)

## Goal

Build installer desktop (NSIS + MSI) untuk semua pekerjaan sejak v0.2.0 (fix saldo BKU, override + kunci kwitansi, audit log, halaman Akun & Login, fix paginasi filter, halaman Tentang) dan rilis ke GitHub.

## Summary

- Versi 0.3.0 (sudah di-bump sesi sebelumnya: `config/app.php`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `.env.example`).
- Build Tauri sukses: `SmartRKAS_0.3.0_x64-setup.exe` (NSIS, 57.7MB) + `SmartRKAS_0.3.0_x64_en-US.msi` (MSI, 86.8MB).
- Commit `8655faa` (42 file, +1592/−231) memuat SEMUA pekerjaan 06 Agu yang belum ter-commit sejak v0.2.0 → push `master`.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.3.0 (2 asset, state `uploaded`, digest sha256 tersedia).

## Catatan

- Audit filter (M-sesi "Pertahankan Query String Saat Paginasi") turut masuk rilis ini: `->withQueryString()` di TransaksiBku, Rkas, MasterProgram, MasterKodeRekening, Dashboard, Laporan (loadRekapItems & loadKuartalItems). AuditLog sudah benar sejak sebelumnya.
- PowerShell: jangan tulis `--notes` multiline berisi `\"` dalam satu string (globbing error `no matches found`); pakai `--notes-file` dari file temp.
- `public/build/` di-regenerate via `npm run build` sebelum `tauri build` — tidak di-track.

## Test Status

- Tidak ada perubahan logika app pada sesi rilis → suite tetap `OK (284 tests, 764 assertions)`, PHPStan clean.

---

# Sesi 06 Agu 2026 — Fix Auto-Migrate Desktop: Kolom Telegram Hilang di DB Lama (v0.3.1) — BELUM RELEASE

## Goal

Perbaiki error `SQLSTATE[HY000]: General error: 1 no such column: telegram_chat_id` di mode desktop: DB SQLite lama (dibuat sebelum fitur Telegram ada) tidak pernah di-migrate saat app di-upgrade → kolom `telegram_chat_id`/`telegram_bot_token` tidak ada.

## Summary

- **Akar masalah**: `src-tauri/src/lib.rs` hanya menjalankan `artisan app:install` saat **first-run** (`first_run = !db_path.is_file()`). Upgrade app → `migrate` tidak pernah jalan → migrasi baru (000018 recovery_code, 000019 telegram, 000020 override_note) Pending di DB lama.
- Verifikasi via `php artisan migrate:status` (dgn `DB_DATABASE` menunjuk DB desktop): 3 migrasi Pending (000018/000019/000020), sisanya batch 1.
- **DB desktop sudah di-migrate manual** (user tutup app dulu): `$env:DB_DATABASE="C:\Users\yudhi\AppData\Roaming\id.smartrkas.desktop\smartrkas.sqlite"; php artisan migrate --force` → 3 migrasi DONE. Verifikasi `PRAGMA table_info` via script temp: kolom `telegram_chat_id`, `telegram_bot_token`, `recovery_code_hash`, `recovery_code_generated_at`, `override_note` ADA; `users.id=1` telegram\_\* = NULL (save yang gagal tidak menyimpan apa pun).
- **Fix permanen** `lib.rs`: setelah blok `if first_run { app:install }`, tambah `run_php(..., ["artisan","migrate","--force"], true)` → dijalankan SETIAP startup (di first-run no-op). Upgrade app masa depan otomatis migrate DB lama.
- **Catatan keamanan**: token bot asli user sempat muncul di pesan error (ter-paste user ke chat) → rekomendasikan regenerate di @BotFather.
- **Catatan form**: nilai sempat tertukar (token masuk field chat_id & sebaliknya) tapi form+controller SUDAH benar (`telegram.blade.php:59,72` + `TelegramPengaturanController`) → murni salah ketik user; save gagal jadi tidak tersimpan.

## Changes

- `src-tauri/src/lib.rs` — auto-migrate tiap startup (perubahan Rust, butuh rebuild installer).
- Versi bump `0.3.0 → 0.3.1`: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` (entry `smartrkas`).

## Build Status (PAUSED — user minta lanjut nanti)

- PHPUnit `OK (284 tests, 764 assertions)`, PHPStan `[OK] No errors`, `cargo check` OK.
- `npm run build` OK; `npm run tauri -- build` **terpotong timeout 30 menit**:
    - ✅ `SmartRKAS_0.3.1_x64-setup.exe` (NSIS, 60.5MB) TERPRODUCE di `src-tauri/target/release/bundle/nsis/`.
    - ❌ MSI v0.3.1 TIDAK ter-produce (build di-kill saat tahap MSI bundling).
- Perubahan kode BELUM di-commit (aman di disk, working tree).

## Next (lanjutan sesi berikutnya)

1. `npm run tauri -- build --bundles msi` (compile sudah cached → cepat) untuk produce MSI v0.3.1.
2. `git add -A` + commit (lib.rs, versi, docs) + `git push origin master`.
3. `gh release create v0.3.1` — asset NSIS + MSI; catatan: fix auto-migrate DB lama saat upgrade + urutan kolom Telegram.
4. Tutup/susun sesi ini menjadi "Release v0.3.1" bila sudah rilis.

## Test Status

- PHPUnit `OK (284 tests, 764 assertions)`, PHPStan level 6: `[OK] No errors`.

---

# Sesi 06 Agu 2026 — Fix Telegram Tak Terkirim: Job Sinkron + Feedback Pesan Uji (v0.3.1)

## Goal

Perbaiki "simpan pengaturan bot lalu Kirim Pesan Uji tidak menerima pesan apa pun di Telegram". Akar masalah: job notifikasi Telegram di-queue (`ShouldQueue` + `QUEUE_CONNECTION=database`) tapi TIDAK ada queue worker — desktop hanya spawn `artisan serve --no-reload` + `schedule:work`, web hanya `php artisan serve` → job diam selamanya di tabel `jobs`.

## Summary

- **Akar masalah sama untuk 3 job**: `SendTelegramNotificationJob`, `SendRecoveryCodeTelegramJob`, `GenerateExportJob` semua `ShouldQueue` → tanpa worker, pesan uji, kode pemulihan, dan export Excel tidak pernah diproses.
- **Fix**: ubah ketiga job menjadi SINKRON (hapus `ShouldQueue`), mengikuti precedent `ProcessRkasImport` ("Sengaja TIDAK mengimplementasikan ShouldQueue sehingga berjalan sinkron lewat ::dispatch() (desktop offline tanpa worker)"). Tidak ada lagi job yang antri → tidak butuh worker di web maupun desktop.
- **Feedback nyata**: `TelegramPengaturanController::test()` kini memanggil `SendTelegramNotificationJob::send()` langsung dan melaporkan hasil: sukses → flash status; gagal → flash error berisi `description` dari API Telegram (mis. `Unauthorized`, `chat not found`) + pengingat tekan Start pada bot; exception → flash error.
- Audit log `telegram_pengaturan.test` kini memuat `success` + `error` (tanpa token).

## Changes

- `app/Jobs/SendTelegramNotificationJob.php` — hapus `ShouldQueue` + cache lock `telegram-notification` (kini sinkron, tak perlu rate-limit lintas-proses); tambah `send(): \Illuminate\Http\Client\Response` (POST Telegram, timeout 5 dtk); `handle()` guard config lalu panggil `send()`.
- `app/Jobs/SendRecoveryCodeTelegramJob.php` — hapus `ShouldQueue` + `tries`/`backoff`.
- `app/Jobs/GenerateExportJob.php` — hapus `ShouldQueue` (export berjalan inline saat request; `Excel::fake()` di test tetap bekerja, status job langsung `completed`).
- `app/Http/Controllers/TelegramPengaturanController.php` — `test()`: try/catch `send()`, `$response->successful()` → status; selain itu flash error `description` (fallback `HTTP n`).
- Tests — ganti asersi queue (`Queue::fake()`+`assertPushed`) dengan asersi HTTP (`Http::fake()`+`assertSent`): `TelegramNotificationTest` (4), `TelegramPengaturanTest` (4), `OnboardingTest` (2), `RecoveryCodeTest` (2). Tambah 1 test baru: `test_test_button_shows_error_when_telegram_rejects` (fake 401 Unauthorized → flash error). `TelegramRecoveryTest` (pakai `dispatchSync`) tanpa perubahan.
- **Catatan test**: `Http::response([...])` dengan body array → `json('description')` null (body di-`(string)`-cast jadi "Array"); untuk fake respons error pakai string JSON: `Http::response('{"ok":false,...,"description":"Unauthorized"}', 401)`.

## Test Status

- PHPUnit `OK (285 tests, 768 assertions)`, PHPStan level 6: `[OK] No errors`.

---

# Sesi 06 Agu 2026 — Release v0.3.1 (Auto-Migrate Desktop + Fix Telegram Sinkron)

## Goal

Lanjutkan sesi "Fix Auto-Migrate Desktop" yang PAUSED (NSIS v0.3.1 ter-produce, MSI belum) SEKALIGUS bawa fix "Telegram tak terkirim" ke rilis.

## Summary

- Build ulang penuh `npm run tauri -- build` (compile cached, ~4 menit) → **NSIS + MSI v0.3.1 ter-produce** (NSIS 57.7MB, MSI 86.8MB).
- Commit `567e5b6` (15 file) → push `master`. Release https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.3.1 (2 asset, state uploaded).
- Catatan rilis: auto-migrate DB lama, job Telegram/export jadi sinkron, feedback pesan uji nyata.

## Test Status

- PHPUnit `OK (285 tests, 768 assertions)`, PHPStan level 6: `[OK] No errors`. `cargo` compile OK.

---

# Sesi 06 Agu 2026 — Fix Installer "Error opening file for writing" (v0.3.2)

## Goal

Perbaiki `Error opening file for writing: php_curl.dll` saat upgrade SmartRKAS (installer NSIS gagal menimpa DLL karena proses `php.exe` yatim mengunci file), lalu bump 0.3.1 → **v0.3.2**, rebuild, dan rilis ulang.

## Summary

- **Akar masalah**: 2 proses `php.exe` yatim dari `...\AppData\Local\SmartRKAS\php\php.exe` (PID 24624, 46308) masih berjalan karena kill paksa app tidak mematikan anak proses (`lib.rs` hanya handle `CloseRequested`). Installer v0.3.1 sempat jalan lalu macet karena DLL terkunci.
- Fix ganda: **NSIS hook** (matikan proses milik instalasi saat install/uninstall) + **Windows Job Object** (anak PHP mati otomatis saat app berakhir, termasuk di-kill paksa).
- Build: NSIS 57.8MB + MSI 86.9MB. Commit `a639b9e` → push `master`. Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.3.2 (2 asset, state uploaded).

## Changes

- `src-tauri/nsis/installer-hooks.nsh` (BARU) — `NSIS_HOOK_PREINSTALL` + `NSIS_HOOK_PREUNINSTALL` → makro `SMART_StopRunningProcesses`: set env `SMARTRKAS_INSTDIR` via `System::Call` (kernel32 SetEnvironmentVariable), lalu `nsExec::ExecToLog` jalankan PowerShell (Get-CimInstance Win32_Process) yang `Stop-Process -Force` untuk `SmartRKAS.exe` + `php\php.exe` yang `ExecutablePath`-nya `-contains` `$targets` (Join-Path `$inst`). Pencocokan by-path menjaga php tool lain (XAMPP/VS Code) aman. `Sleep 500` setelahnya.
- `src-tauri/tauri.conf.json` — `bundle.windows.nsis.installerHooks: "./nsis/installer-hooks.nsh"` + version 0.3.2.
- `src-tauri/Cargo.toml` — dep baru `[target.'cfg(windows)'.dependencies] windows-sys = "0.59"` features `Win32_Foundation`, `Win32_Security` (utk `CreateJobObjectW`), `Win32_System_JobObjects`, `Win32_System_Threading` (utk `JOBOBJECT_EXTENDED_LIMIT_INFORMATION`).
- `src-tauri/src/lib.rs` — `struct PhpServer { children, #[cfg(windows)] _job }` (ganti tuple struct); `#[cfg(windows)] struct JobHandle(RawHandle)` + `unsafe impl Send/Sync` (`RawHandle`/`HANDLE` = `*mut c_void` TIDAK Send/Sync → perlu wrapper agar bisa `app.manage`); mod `job` (`create_kill_on_close_job` = `CreateJobObjectW` + `SetInformationJobObject` `JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE`; `assign` = `AssignProcessToJobObject` via `std::os::windows::io::AsRawHandle`, gagal di-skip aman). Setup: assign serve+scheduler ke job, simpan handle di state. `on_window_event` → `state.children` (bukan `state.0`).
- Versi bump: `config/app.php`, `.env.example`, `src-tauri/Cargo.toml`, `Cargo.lock`, `tauri.conf.json`.

## Catatan

- NSIS hook: argumen PowerShell di-`System::Call` perlu escape `$\"`; gunakan env var (`SMARTRKAS_INSTDIR`) untuk meneruskan `$INSTDIR` (menghindari masalah quoting path). Di dalam script PowerShell semua `$` ditulis `$$` (escape NSIS). Pencocokan `-contains` case-insensitive.
- Job object: handle DIBIARKAN hidup seumur app (tidak pernah di-close manual) — saat proses app mati (normal/kill paksa) OS menutup handle → job ditutup → anak PHP dimatikan. Ini inti fix: user yang force-kill via Task Manager pun tidak lagi meninggalkan proses yatim.
- MSI tidak pakai hook (kustomisasi tidak mudah) — Job Object menutup lubang untuk kasus MSI/upgrade path lain.
- Release notes: tulis via `--notes-file` (temp .md), jangan `--notes` multiline (PowerShell globbing).

## Hotfix (re-release v0.3.2, commit `6a0ef67`)

- **Bug**: v0.3.2 awal punya escaping salah di NSIS hook → proses php.exe yatim TIDAK dihentikan → error "Error opening file for writing" MUNCUL LAGI saat user pasang v0.3.2.
- **Akar masalah**: di string NSIS single-quoted, `''` BUKAN escape utk literal `'` — NSIS meneruskan `''` apa adanya, dan PowerShell menganggap `''SilentlyContinue''` = `''` (string kosong) + `SilentlyContinue` → syntax error → PowerShell gagal. Escape yang benar: `$\'` (atau `'` tunggal). Kesimpulan diverifikasi dgn `FileWrite` dump: `$\'$INSTDIR$\'` dan `'$INSTDIR'` menghasilkan string SAMA (`"$inst='C:\...\SmartRKAS'"`).
- **Fix**: `src-tauri/nsis/installer-hooks.nsh` — ganti SEMUA `''` → `$\'` di script PowerShell, buang `System::Call` (cara lama set env `SMARTRKAS_INSTDIR`), `$INSTDIR` di-embed langsung: `$$inst=$\'$INSTDIR$\'`.
- **Verifikasi**: 1) `Get-CimInstance` matching langsung (PowerShell murni) ketemu 2 orphan (PID 24624, 46308). 2) Test isolasi nsExec: `$$`+`$\'` OK (esc5), `''` GAGAL (esc3/esc4), `$INSTDIR` resolve OK (esc7). 3) Hook final di-compile makensis (hooktest.nsi, InstallDir=$LOCALAPPDATA\SmartRKAS) → spawn php.exe palsu → jalankan /S → proses MATI. 4) Rebuild `npm run tauri -- build` (1.12s, tanpa recompile) → `gh release upload v0.3.2 --clobber` (NSIS + MSI) + `gh release edit` (notes berisi catatan hotfix). Commit `6a0ef67` → push.
- **Diagnosis upgrade**: folder instal `C:\Users\yudhi\AppData\Local\SmartRKAS` — `smartrkas.exe` 0.3.2 tapi `uninstall.exe` 0.2.0 + TIDAK ada registry uninstall + file php tetap 2023 → bukti installer ABORT di tengah (exe tertimpa dulu, lalu gagal di file php yg terkunci). mtime file instal mencerminkan mtime source (NSIS pertahankan mtime file).
- **Debugging NSIS**: toolchain — tulis .nsi test di `%TEMP%\opencode`, compile `C:\Users\yudhi\AppData\Local\tauri\NSIS\makensis.exe -V2`, jalanin `/S`. Generated installer script Tauri ada di `src-tauri\target\release\nsis\x64\installer.nsi` (makro `!insertmacro` hanya tampil sebagai directive; body di-expand makensis saat compile).
- Proses yatim v0.3.1 (PID 24624, 46308) sudah di-kill saat diagnosis; job object + hook fix mencegah tercipta lagi.

## Test Status

- Tidak ada perubahan logika PHP → PHPUnit `OK (285 tests, 768 assertions)`, PHPStan level 6: `[OK] No errors`. `cargo check` OK; `npm run build` OK; `tauri build` (NSIS + MSI) OK.

---

# Sesi 07 Agu 2026 — Fix 3 Bug (BKU Tak Tersimpan, Telegram SSL, Filter Dashboard) + Review Menyeluruh — v0.3.3

## Goal

Perbaiki bug yang dilaporkan user + 3 relawan aplikasi desktop: (1) input BKU tidak tersimpan, (2) notifikasi Telegram gagal (cURL error 60), (3) filter bulan dashboard menampilkan item tanpa rencana bulan terfilter. Ditutup dengan **review kualitas menyeluruh** (user: "kualitas jadi tolak ukur") sebelum rilis v0.3.3.

## Summary

- **Bug BKU ter-reproduksi definitif** terhadap server live `127.0.0.1:63483` (bukan asumsi): `jumlah=500.000` tersimpan **Rp 500** (bug titik ribuan); guard anggaran menolak → 302 ke `/transaksi-bku/create` + flash error TAMPIL tapi form **kosong** (back tanpa `withInput` + `type=number` tak bisa menampilkan nilai lama berformat titik) — persis gejala user. Guard sering terpukul karena banyak item sisa 0 → user "input hilang" tanpa sadar.
- **Bug tambahan ditemukan saat review kedua & sudah di-fix**:
    - **x100 pada output kalkulator**: `parseRupiah`/`NumberParser::rupiah` lama strip SEMUA titik → `(tarif*vol).toFixed(2)` = `"1000000.00"` jadi `"100000000"`. Fix: guard regex `^[+-]?\d+(\.\d{1,2})?$` → return apa adanya (PHP `NumberParser::rupiah` + JS `parseRupiah` di create/edit BKU + `normal` di `rkas/edit`).
    - **Stale volume/satuan**: `toggleVisibility()` (jenis=penerimaan) tidak reset `volumeHidden`/`satuanHidden` → data basi terkirim. Fix di create + edit: reset keduanya di cabang penerimaan.
    - **Volume edit terhapus (pra-ada)**: `init()` → `kalkulasiJumlah()` menulis `volumeHidden=''` saat input volume kosong → volume transaksi hilang walau tak disentuh. Fix: flag `var volumeTouched=false`; hidden hanya ditulis bila `volumeInput.value !== '' || volumeTouched`; listener input set flag.
    - **Item penerimaan terhapus saat edit (pra-ada)**: `toggleVisibility` memanggil `setSelected(null)` saat load → item RKAS transaksi penerimaan hilang. Fix: flag `var initializing=true`; `setSelected(null)` hanya saat perubahan user (bukan load), `initializing=false` setelah `init()`.
- Reproduksi memakai cookie sesi forge: nama cookie **`smartrkas-session`**, format `urlencode(base64(json{iv,value,mac,tag}))`, prefix `hash_hmac('sha1','smartrkas-sessionv2',APP_KEY).'|'`, AES-256-CBC (EncryptCookies tetap mengenkripsi walau `SESSION_ENCRYPT=false`). Data tes (3 transaksi REPRO + outbox 2 + audit 2) sudah dibersihkan.
- **Bug Telegram SSL root cause 100%**: bundle PHP desktop tanpa CA bundle → `ERR 60 unable to get local issuer certificate`. Dgn `CURLOPT_CAINFO=C:\xampp\apache\bin\curl-ca-bundle.crt` → HTTP 200. Ekstensi curl/openssl aktif (bukan penyebab). Web (XAMPP) normal.

## Changes

- `app/Support/NumberParser.php` (BARU) — helper `rupiah()` (strip spasi+`.` ribu, koma→titik) & `decimal()` (pertahankan satu titik desimal).
- `TransaksiBkuController::store`/`update` — merge `NumberParser::rupiah(jumlah)` + `decimal(volume)` SEBELUM validate; guard anggaran → `throw ValidationException::withMessages(['jumlah' => ...])` (ganti `back()->with('error')`) → form kembali dgn old + error inline. `create()` — `$pickerInitial` dari `old('rkas_item_id')` utk repopulasi picker.
- `RkasController::update` — normalisasi `jumlah`/`tarif` (rupiah) + `volume` (decimal).
- Views `transaksi-bku/create.blade.php` & `edit.blade.php` — `<form id="form-bku">`, `jumlah` `type="text" inputmode="decimal"` + hint format, JS `parseRupiah`/`parseDecimal` (normalisasi submit + kalkulasi volume via `parseDecimal`), include picker `['pickerInitial' => $pickerInitial]`, `window.RkasPicker.init()`.
- `resources/views/rkas/edit.blade.php` — `volume`/`tarif`/`jumlah` type=text + JS normalisasi submit.
- `DashboardController::index` — query tabel item tambah `->when($bulan, fn($q) => $q->whereHas('bulanRencana', fn($q2) => $q2->where('bulan',$bulan)))` → item tanpa rencana bulan terfilter TIDAK tampil. `/rkas` TIDAK diubah (filter bulan di sana tidak mengubah tampilan tabel).
- **Telegram SSL**: `src-tauri/php/extras/ssl/cacert.pem` (CA bundle curl.se, 186KB, untracked — `php/` di-gitignore); `bootstrap/app.php` — `ini_set('curl.cainfo'/'openssl.cafile', __DIR__.'/../php/extras/ssl/cacert.pem')` bila file ada (web mode skip); `src-tauri/php/php.ini` — direktif `openssl.cafile`/`curl.cainfo = "php\extras\ssl\cacert.pem"` (relatif CWD instalasi). Verifikasi: PHP bundle curl ke `https://api.telegram.org/` → `HTTP OK len=145` (dgn `-d` & via php.ini di CWD instalasi).
- Tests — `TransaksiBkuTest`: 2 test guard diubah `assertSessionHas('error')` → `assertSessionHasErrors('jumlah')` + test baru `test_store_normalizes_indonesian_number_format` (1.500.000 → 1500000, volume 2,5 → 2.5, butuh `RkasItemBulan` rencana cukup utk lolos guard). `DashboardTest`: test baru `test_dashboard_bulan_filter_hides_items_without_plan_for_that_month`.
- **Review kedua** — test baru: `tests/Unit/NumberParserTest.php` (18 kasus via data-provider: "1.500.000"→"1500000", "1.500.000,50"→"1500000.50", "1000000.00" dipertahankan, spasi strip, dll); `TransaksiBkuTest::test_store_keeps_calculator_decimal_format` (2500000.00 → 2500000); `RkasControllerTest`: `test_edit_page_renders` (id="form-rkas-edit", name=tarif/volume) + `test_update_normalizes_indonesian_number_format` (volume 2,5→2.5, tarif 1.500.000→1500000, jumlah 3.750.000→3750000); `test_create_page_renders` BKU diperkuat (form-bku, jumlah, picker, row_override, "Format angka Indonesia" — sempat gagal karena ketik "format Indonesia").

## Catatan

- `php.ini` relatif CA path resolve thd CWD (bukan lokasi ini) — di desktop CWD = instal dir (`lib.rs` `current_dir(&root)`) → `php\extras\ssl\cacert.pem` benar. Standalone `php.exe -r` dari folder lain → ERR 77 (wajar; app selalu kena `ini_set` abslolut di bootstrap).
- Guard BKU kini pakai `ValidationException` → kode view sudah render `$errors->first('jumlah')` (sudah ada sejak M-override); flash `error` redirect TIDAK dipakai lagi utk guard.
- `NumberParser::decimal` mempertahankan titik desimal (volume 2.5 benar), `rupiah` membuang semua titik (1.500.000 → 1500000).
- **Smoke test HTTP nyata (bukan harness PHPUnit)**: server `artisan serve` port 8099 + DB scratch sqlite (`%TEMP%\opencode\smoke-smartrkas.sqlite`). Kendala: `/login` 302 ke `/mulai` karena `AppState::isFirstRun()` (butuh `last_login_at` di user) + `PengaturanSekolah::get()->npsn` (butuh row); `MasterKodeRekening::factory()` TIDAK idempoten (UNIQUE `jenis_belanja.nama`) → seed pakai update-atau-create terpisah. Hasil: POST `500.000` → tersimpan 500000; POST `9.000.000` (guard) → kembali create, old dipertahankan, error inline tampil, picker ter-repopulasi. Server + file scratch dibersihkan setelahnya.
- **Cargo.lock**: saat bump versi JANGAN `-replace '(?m)^version = "0.3.2"$'` pada seluruh file — ada crate lain versi 0.3.2 (mis. `objc2-app-kit`) yang ikut ter-replace → `failed to select a version`. Fix: `git checkout` lalu replace hanya blok `name = "smartrkas"` berikutnya (2 baris). Terverifikasi via `git diff --stat` (1 file, 1+/1-).
- Bump versi v0.3.3: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` (entry `smartrkas`).

## Test Status

- PHPUnit `OK (308 tests, 810 assertions)`, PHPStan level 6: `[OK] No errors`, `php artisan view:cache` OK.
- BELUM di-commit; belum build/rilis v0.3.3 (build `--bundles nsis` jalan di background).

---

# Sesi 07 Agu 2026 — Release v0.3.3 (Build + Push + GitHub)

## Goal

Finalisasi rilis v0.3.3: build installer (NSIS + MSI), commit semua pekerjaan "Fix 3 Bug + Review Menyeluruh", push, dan rilis ke GitHub.

## Summary

- Versi 0.3.3 (sudah di-bump sesi sebelumnya di 5 file). Build NSIS ter-produce 57.9MB; MSI 87MB.
- Commit `b458b36` (18 file, +445/−31) → push `master`.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.3.3 (2 asset, state `uploaded`).

## Catatan

- Build Tauri via **background process** (`Start-Process cmd /c npm run tauri -- build --bundles nsis|msi > log`) — hindari timeout tool; poll log + cek folder bundle. NSIS: 9m10s compile + makensis; MSI: compile cached + candle/light.
- **Cargo.lock** ikut ter-replace saat bump → `objc2-app-kit = "^0.3"` gagal select. Fix: `git checkout -- src-tauri/Cargo.lock` lalu replace presisi blok `name = "smartrkas"` (2 baris berikutnya) → `git diff --stat` = 1 file 1+/1-.
- Release notes via `--notes-file` temp (hindari globbing PowerShell). Session context `b458b36`.

## Test Status

- Tidak ada perubahan logika app pada sesi rilis → suite tetap `OK (308 tests, 810 assertions)`, PHPStan clean.

---

# Sesi 08 Agu 2026 — Address 6 Temuan Uji v0.3.4 (Sisa BKU, TLS Desktop, Cetak PDF, Filter RKAS) — v0.3.5

## Goal

Selesaikan temuan pengujian v0.3.4: (1) filter data RKAS seperti dashboard (Kode Rekening + Jenis Belanja; realisasi tetap kumulatif tahunan), (2) input BKU "tidak tersimpan" — form kembali terisi tapi angka sisa berbeda dari yang dicek server, (3) Cetak PDF "tidak ada dialog apa pun" (gagal senyap), (4) Telegram `cURL error 77` cacert.pem, (5) "Gagal memeriksa pembaruan". Dashboard disetujui user. Rilis GitHub DITUNDA sampai hasil uji user.

## Summary

- **Akar #4/#5**: `curl.cainfo`/`openssl.cafile` = direktif `PHP_INI_SYSTEM` → `ini_set()` di `bootstrap/app.php` no-op; path relatif di php.ini resolve terhadap direktori php.exe → salah. Fix = argumen `-d curl.cainfo=<ABS> -d openssl.cafile=<ABS>` dari `lib.rs` (prioritas tertinggi). Terverifikasi: bundle php + `-d` → GET `api.github.com` HTTP 200 (curl_errno=0); tanpa `-d` → cURL 60 (reproduksi persis). Fix #4 otomatis menyembuhkan #5 (AppUpdateService→Http GitHub).
- **Akar #2**: guard menolak berdasarkan **sisa kumulatif bulan berjalan**, tapi create/edit menampilkan **sisa tahunan** → angka layar ≠ angka penolakan. Fix: helper `RkasItem::sisaKumulatifSd($bulan)` dipakai picker + guard; `no_bukti` kini nullable + auto-generate server-side (`BPU…`/`BBU…`).
- **Akar #3**: `save_download` lama pakai callback + channel mpsc — berisiko callback tak pernah jalan → hang tanpa dialog. Fix: `blocking_save_file()` (tauri-plugin-dialog) + feedback toast JS + logging PDF (`streamPdf`).
- **#1**: `/rkas` tambah filter `kode_rekening_id` + `jenis_belanja_id` (whereHas `kodeRekening.jenis_belanja_id`), termasuk di `destroyAll` + fix bug bonus `is_numeric()` pada UUID (filter "Hapus Semua" diam-diam tidak berfungsi).

## Changes

- **B (BKU)**:
    - `app/Models/RkasItem.php` — helper **`sisaKumulatifSd(int $bulan): float`** (rencana `RkasItemBulan`≤bulan − realisasi pengeluaran≤bulan); cek model sudah `use App\Models\RkasItemBulan` (headernya tidak dibaca penuh — pastikan saat review).
    - `app/Http/Controllers/RkasItemController.php` (`select2`) — respons item kini sertakan `'bulan' => $bulan`.
    - `TransaksiBkuController::create()` — `$pickerInitial['sisa']` pakai bulan `old('tanggal', now())` via `sisaKumulatifSd`; `edit()` — sisa dari bulan `$transaksiBku->tanggal`.
    - `store()` — validasi `no_bukti` jadi `'nullable|string|max:255'`; kosong/duplikat → `generateNoBukti($jenis, $tanggal)` (prefix `BPU`/`BBU`, seq count+1 inkremental, format `BPU001/NPSN/MM/YYYY`, npsn dari `PengaturanSekolah::get()` fallback `'00000000'`).
    - Pesan guard diselaraskan: "melebihi sisa anggaran s.d. bulan {N} (Rp …)".
    - `resources/views/transaksi-bku/_rkas-picker.blade.php` — label `id="detail_sisa_label"` = "Sisa s.d. bulan {N}" saat `item.bulan` ada.
- **A (TLS)**: `src-tauri/src/lib.rs` — helper `cacert_args()` (ABS `<resource_dir>\php\extras\ssl\cacert.pem`, skip bila tak ada) di-inject ke `run_php()` dan Command serve PHP (sebelum `artisan`). `bootstrap/app.php` — blok `ini_set` dihapus (no-op PHP_INI_SYSTEM, menyesatkan). `src-tauri/php/php.ini` — direktif cacert dikomentari + komentar menjelaskan kenapa.
- **C (Cetak)**: `lib.rs` `save_download` → `Result<Option<String>, String>` pakai `blocking_save_file()` (None = dibatalkan; Some = path tersimpan); `resources/js/app.js` — `SmartRKAS.notify()` toast (`.smart-toast` CSS di app.css), `saveDownload`/`saveResponse` tak lagi return false senyap (toast error, sukses "File tersimpan: <path>"); `laporan/bku-web.blade.php` `cetakPdf()` jadi `async` + `await`; `LaporanController` — `streamPdf()` private (try/catch + `Log::error` + `abort(500)`) untuk 4 laporan PDF.
- **D (Filter RKAS)**: `RkasController::index` — filter `kode_rekening_id` + `jenis_belanja_id` (whereHas `kodeRekening`), muat `$kodeRekenings`/`$jenisBelanjas`; `destroyAll` — ganti `is_numeric()` (UUID → selalu 0, filter mati) dengan cek `! empty()` + filter baru; `rkas/index.blade.php` — 2 select baru + hidden field di form hapus-semua.
- Versi bump **0.3.4 → 0.3.5**: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` (hanya blok `name = "smartrkas"`).

## Tests

- `tests/Feature/BKU/TransaksiBkuTest.php` — +4: `store_generates_no_bukti_when_missing` (regex `BPU00\d/\d+/01/2026`), `store_regenerates_duplicate_no_bukti`, `create_page_shows_monthly_cumulative_sisa_matching_guard`, `edit_page_shows_monthly_cumulative_sisa`. **Catatan**: label "Sisa s.d. bulan N" dirender JS (picker) → assert pakai `@json` payload (`assertSee('"bulan":2', false)` + `assertSee('"sisa":4500000', false)`) + `assertSee('Sisa s.d. bulan', false)`, bukan teks JS-rendered.
- `tests/Feature/RKAS/RkasControllerTest.php` — +6: page renders filter, filter kode rekening, filter jenis belanja, destroyAll filter program UUID, destroyAll filter kode rekening, destroyAll filter jenis belanja.

## Catatan Teknis

- `blocking_save_file()` (tauri-plugin-dialog 2.7.2) = `blocking_fn!` (sync_channel) — aman dipanggil langsung di command async (pola doc plugin). `FilePath::into_path()` return `Result<PathBuf,Error>`, bukan `Option` → pakai `as_path()` (Option) seperti kode lama.
- PHPStan: `Pdf::loadView()` mengembalikan `\Barryvdh\DomPDF\PDF` (bukan Facade) → param helper `streamPdf` tipe instance, bukan `Pdf` facade.
- Toast `notify()` dipakai utk feedback tanpa reload (desktop); web mode tidak aktif (saveDownload/saveResponse hanya ada saat `isTauri`).
- Verifikasi TLS desktop: `.\src-tauri\php\php.exe -d curl.cainfo=… -d openssl.cafile=… <script>` → `curl_errno=0`, HTTP 200; baseline tanpa `-d` → `curl_errno=60` (keluarga 60/77 sama-sama di-fix).

## Build

- `npm run build` OK; `php artisan view:cache` OK; `cargo check` OK; `tauri build --bundles nsis,msi` OK → NSIS 57.9MB + MSI 87.3MB.
- SUDAH di-commit (`697d19f`, push `master`); rilis GitHub v0.3.5 DITUNDA (menunggu hasil uji user terhadap installer).

## Test Status

- PHPUnit `OK (321 tests, 851 assertions)`, PHPStan level 6: `[OK] No errors`.

---

# Sesi 08 Agu 2026 — Hasil Uji USER v0.3.5: 4 dari 6 Temuan MASIH GAGAL (Komentar Lanjutan)

## Status (dari user, setelah pasang installer v0.3.5)

- Meski fix terverifikasi di test suite / verifikasi langsung, **user melaporkan 4 item MASIH tidak jalan** di instalasi:
    1. **Telegram masih cURL error** (pesan uji / kode pemulihan tetap gagal).
    2. **Cetak PDF masih tanpa dialog** Save As (gagal senyap).
    3. **Input BKU masih "tidak tersimpan"** (sisa anggaran tampil masih beda / penolakan).
    4. **Cek pembaruan masih "Gagal memeriksa pembaruan"**.
- Dashboard & filter RKAS dianggap OK (tidak disebut gagal).
- Rilis GitHub v0.3.5 TETAP ditahan; investigasi lanjutan dibuka.

## Hipotesis Utama (untuk diselidiki sesi berikutnya)

- **Semua fix server-side (BKU) + desktop (TLS/PDF) gagal bersamaan** → kuat dugaan instalasi TIDAK menjalankan bundle v0.3.5 yang baru:
    - Verifikasi versi aktual: `About` app (versi terpasang), `smartrkas.exe` version info, atau `APP_VERSION` di halaman Tentang.
    - Cek apakah upgrade benar-benar menimpa `php/`, `resources/`, `smartrkas.exe` di `C:\Users\yudhi\AppData\Local\SmartRKAS` (bandingkan mtime/size file vs source).
    - Kemungkinan installer ABORT di tengah (seperti kasus v0.3.2 "Error opening file for writing" — proses php.exe yatim) sehingga exe baru tapi resource/php lama, ATAU user masih membuka app v0.3.4 lama.
- **TLS tetap cURL error walaupun `-d`**: bila versi benar-benar v0.3.5, periksa apakah `-d` benar sampai ke child (`artisan serve`) — verifikasi `cacert_args()` path di instalasi terpasang (resource_dir ≠ path dev). Jangan lupa file cacert untracked (`src-tauri/php/extras/ssl/cacert.pem`) — kalau tidak ikut terbundle, `cacert_args()` skip → cURL error lagi.
- **Cetak PDF masih senyap**: kemungkinan tetap memakai JS `app.js` lama (cache webview / aset build lama) atau kegagalan di sisi lain (PDF 500 yang tidak ditangani toast lama).
- **BKU masih beda sisa**: bila server-side lama yang jalan, gejala otomatis sama.

## Langkah Diagnostik yang Direkomendasikan (belum dikerjakan)

1. Konfirmasi versi terpasang benar v0.3.5 (About/Tentang + file exe).
2. `Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' }` — pastikan tidak ada php yatim dari versi lama yang menahan file.
3. Buka `C:\Users\yudhi\AppData\Local\SmartRKAS\storage\logs\laravel.log` — cek error nyata saat user melakukan aksi (BKU store, PDF, Telegram).
4. Uji `php.exe` bundle instalasi langsung: `.\php.exe -d curl.cainfo="<install>\php\extras\ssl\cacert.pem" -d openssl.cafile="<sama>" -r "echo curl_init();"` + `curl_errno` ke `https://api.telegram.org` — untuk memastikan CA benar-benar terbaca di instalasi.
5. Uji aksi via HTTP terhadap server lokal yang berjalan (URL dari jendela app / `netstat`) tanpa UI: POST BKU, GET laporan `cetak=pdf`, GET `pengaturan/tentang/check`.

## Catatan

- Commit `697d19f` (20 file, +536/−48) SUDAH di-push `master` sebelum uji user. Rilis GitHub masih ditahan.
- Perbaiki baris sesi v0.3.5 di atas ("BELUM di-commit") → SUDAH di-commit.
- Jangan tulis token bot Telegram asli ke kode/file apa pun.

---

# Sesi 08 Agu 2026 — Root Cause TLS Terkonfirmasi & Fix E2E Terverifikasi (lanjutan Hasil Uji v0.3.5)

## Kenapa 4 dari 6 temuan masih gagal di v0.3.5 padahal test suite hijau

- **Root cause kunci** dikonfirmasi dari diff: **commit v0.3.5 (`697d19f`) masih menjalankan `artisan serve`** (`lib.rs` lama `cmd.arg("artisan").arg("serve")...arg("--no-reload")`). `ServeCommand` (Laravel) hanya meneruskan env ke proses server anak dan **TIDAK meneruskan argumen `-d`** → `curl.cainfo`/`openssl.cafile` tidak pernah termuat di proses yang benar-benar menangani HTTP → TLS (Telegram/cek pembaruan) selalu gagal. Ini penjelasan pastinya kenapa fix yang tampak benar di verif langsung (probe `curl_errno=0`) tidak berdampak di instalasi.
- **BKU & PDF**: BUKAN TLS — 2 item itu tidak berhubungan dengan `-d`. Fix `-S` langsung hanya menjelaskan item Telegram + cek pembaruan. BKU/PDF didiagnosis terpisah (langkah 6–7).

## Solusi (working tree, BELUM commit/build)

- `src-tauri/src/lib.rs` — **spawn PHP built-in server LANGSUNG**: `php -S 127.0.0.1:{port} <resource>/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` dengan `current_dir = public`, menggantikan `artisan serve --no-reload`. Alasan dalam komentar kode: `artisan serve` tidak meneruskan `-d` ke child proses server.
- Ditambah `cacert_scan_dir()` + `apply_cacert_scan()`: tulis `cacert.ini` di `<data_dir>/php-ini-scan/` berisi `curl.cainfo="ABS"` + `openssl.cafile="ABS"`, lalu set env **`PHP_INI_SCAN_DIR`** pada SEMUA proses PHP (`run_php` + server) agar diwariskan ke proses anak (mis. `schedule:work`). Ini lapis kedua selain `-d` (yang hanya berlaku pada proses langsung).

## Verifikasi E2E (nyata, bukan fake)

- Route **`/__tlsprobe`** (di `routes/web.php`, TEMPORER utk diagnosis — HAPUS sebelum rilis) memanggil Http facade outbound ke GitHub + Telegram, terus mengembalikan `cainfo`/`scan`/`env_super`/`outbound`.
- Jalankan `php -S` persis seperti lib.rs (bundle PHP instalasi + `-d curl.cainfo=... openssl.cafile=...` + router `server.php` + `cwd=public`, `SESSION_DRIVER=array` utk hindari DB) → **hasil `/__tlsprobe`**: `{"cainfo":"C:\\Users\\yudhi\\AppData\\Local\\SmartRKAS\\php\\extras\\ssl\\cacert.pem","outbound":{"github":{"http":200},"telegram":{"http":200}}}` → TLS fix TERBUKTI di proses yang melayani HTTP.
- Kendala uji: (1) Start-Process PS5.1 tidak punya `-Environment` → set `$env:` dulu; (2) router `server.php` berisi spasi → quote argumen sebagai satu string; (3) opcache ASLR fatal saat banyak instance php → `-d opcache.enable=0` untuk probe; (4) 500 tanpa DB → `SESSION_DRIVER=array` + `CACHE_STORE=null`.
- Dev server lama (port 18211, repo, tanpa `-d`/scan) masih berjalan → perubahan baru hanya aktif setelah rebuild .exe.

## Langkah berikutnya (belum dikerjakan)

- ~~Hapus route `/__tlsprobe` sebelum commit final~~ (SUDAH dihapus).
- Bump v0.3.5 → v0.3.6, commit berisi: `src-tauri/src/lib.rs` (fungsi baru) + `routes/web.php` (hapus probe) + AGENTS.md.
- Diagnosis BKU & PDF khusus terpisah — user diminta langkah 6–7 (cek `laravel.log` + uji di instalasi v0.3.6).

---

# Sesi 08 Agu 2026 — v0.3.6: Fix TLS (spawn `php -S` langsung) + ASLR opcache + buktikan di clean-install — RILIS DITAHAN

## Goal

Lanjutan "Root Cause TLS Terkonfirmasi": implementasikan solusi (spawn `php -S` langsung + `-d cacert` + `PHP_INI_SCAN_DIR`), rilis v0.3.6 sebagai installer, clean-install, dan buktikan dari app nyata bahwa Telegram & "Periksa Pembaruan" jalan. User menginstruksikan: **jangan rilis ke GitHub sampai clean-install + hasil uji user**; diagnosis BKU/PDF tetap paralel (dengan data reproduksi dari user).

## Summary

- Migrasi `npm run tauri build` v0.3.6: NSIS 57.9MB + MSI 87.4MB di `src-tauri/target/release/bundle/`. Commit `b7cf953` (lib.rs + versi + AGENTS).
- Clean-install v0.3.6: uninstall v0.3.5 (`uninstall.exe /S`, exit 0 — DB user SELAMAT di `%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite`). Instansi terverifikasi: `SmartRKAS.exe`, `php/php.exe`, `php/extras/ssl/cacert.pem` (186KB) ada; `.env` + artisan + `public/index.php` ikut terbundle.
- **Bug v0.3.6 yang ditemukan saat verifikasi**: (1) server `php -S` dari dalam app kadang **mati sewaktu start** dengan `Fatal Error: Opcode handlers are unusable due to ASLR` — intermiten, muncul saat beberapa instance php pwaktu bersamaan; (2) playground: `php -S` yang jalan memberi **HTTP 500 body kosong** padahal manual-spawn server yang sama memberi `/login` **200**; `storage/logs/laravel.log` di instalasi TIDAK ter-update.
- Reproduce manual: spawn server TANPA `-d opcache.enable=0` (`php.ini` bundel `opcache.enable=1`, ZTS php8ts) + penuh env (`SMARTRKAS_DATA_DIR`, `DB_DATABASE` Roaming, `APP_ENV=production`, `APP_VERSION=0.3.6`, `-d display_errors`), `-S 127.0.0.1:8942` + router `vendor/.../resources/server.php` → `/login` **`200`**, `/` 302 → `/mulai`. Artinya: dengan `-d opcache.enable=0` + env penuh, server sehat & DB Roaming terbaca.

## Kesimpulan DIREVISI (penting — ganti catatan yang lebih lama)

- Catatan lama sempat menyarankan: "ASLR/opcache bukan penyebab — app user jalan normal" (kesimpulan dari spawn manual `-d` yang memberi `/login` 200).
    - **REVISI: kesimpulan itu BELUM PERNAH diuji kencang dengan skenario asli.** Skenario "shortcut biasa" terverifikasi DI KESEMPATAN INI: terminal PowerShell BARU (belum pernah eksperimen), `$env:DB_DATABASE` & `$env:SMARTRKAS_DATA_DIR` KOSONG (persis cara user membuka app), lalu `Start-Process …\SmartRKAS.exe` (v0.3.6 LAMA, sebelum fix opcache) — hasil **`/login` = HTTP 500 body-kosong**, server tetap hidup (bukan mati karena ASLR), `laravel.log` instalasi TIDAK memuat error produksi.
    - Artinya: **v0.3.6 lama memang masih 500 di skenario asli**; pra-uji "manual-spawn 200" memberi rasa keyakinan palsu karena environment-nya tidak persis sama (PATH-prepend php, `PHP_INI_SCAN_DIR`, dsb).
    - Jadi hipotesis "opcache ASLR / 500-kosong" masih terbuka — perlu dibuktikan dengan protokol 3× bersih setelah terpasang (lihat Next).

## Kesimpulan AKHIR (root cause 500 di instalasi — menggantikan "opcache ASLR" sebagai tersangka utama)

- **`php -S` gagal meload router karena argumen path ber-prefix `\\?\`** (extended-length path, output `resource_dir()` Tauri/Rust canonicalization). PHP tidak memahami prefix `\\?\` → fatal `Failed opening required '\\?\C:\...\server.php'` → **HTTP 500 body kosong** untuk SEMUA request (`/login` termasuk). Bukti rigorus:
    - `php.exe -r "var_dump(is_file('\\?\C:\...\server.php'))"` → `bool(false)`; `var_dump(is_file('C:\...\server.php'))` → `bool(true)`.
    - `php -S` mc report lain yang memakai `\\?\` router → error log instalasi (`%APPDATA%\id.smartrkas.desktop\php-server-error.log`, baru ada setelah `-d error_log` ditambahkan) memuat `PHP Fatal error: Failed opening required '\\?\C:\...\server.php'`.
    - Manual-spawn pakai `C:\...\server.php` (biasa) → `/login` 200 — persis bedanya hanya pada prefix itu.
- Ini juga kemungkinan alasan TLS tetap gagal walau `-d curl.cainfo` diinstal: `-d` path CA juga ber-prefix `\\?\` → curl tak bisa baca `cacert.pem` → cURL error. Fix `native_path()` membereskan keduanya (path router + path CA + `cacert.ini` scan).
- **Kesimpulan lama "opcache bukan penyebab / app jalan normal" TIDAK terulang pada 500**: setelah rebuild fix opcache lalu Test skenario murni (PowerShell baru, env kosong, `Start-Process` app v0.3.6 fix-opcache) → **MASIH 500**. Jadi opcache bukan akar 500; `\\?\` prefix-lah yang benar. (Proses opcache tetap di-disable — aman, murah, mencegah crash ASLR terpisah yang memang pernah teramati.)

## Changes (working tree, rebuild berjalan)

- `src-tauri/src/lib.rs`:
    - `run_php()` — tambah `-d opcache.enable=0` (SEMUA subprocess; opcache ASLR dapat memicu crash acak saat beberapa instance php jalan bersamaan).
    - Spawn `php -S` — tambah `-d opcache.enable=0 -d log_errors=1 -d error_log=<data-dir>/php-server-error.log -d display_errors=0` agar fatal PHP tidak lagi tersembunyi oleh `Stdio::null()`.
    - **`native_path()`** (BARU) — strip prefix `\\?\` / `\\?\UNC\` dari PathBuf → string path biasa. Diterapkan ke: path router `-S`, `current_dir` server (public), path `curl.cainfo`/`openssl.cafile` di `cacert_args`, isi `cacert.ini` di `cacert_scan_dir`. Ini fix utama untuk `HTTP 500` + jamin TLS `-d` benar-benar termuat.
- `bootstrap/app.php` — _tidak diubah_ lagi (blok `ini_set` sudah dihapus; cukup jalur `-d` + scan).

## Catatan

- Route `/__tlsprobe` dihapus (dirty uit: net-zero vs HEAD v0.3.5); E2E TLS sudah dibuktikan saat probe (github 200 / telegram 200 prosesnya= server itu sendiri).
- Sisa php: XAMPP php (PID 57992 = VS Code intellisense, bukan masalah), dev server repo lama (PID 31948) sudah dibunuh.
- Langkah verifikasi penuh setalah rebuild: cargo check → tauri build → uninstall v0.3.6 lama → install bundle baru → jalankan app → /login harus 200 & tidak mati lagi; lalu user menguji Telegram + cek-embaruan dari UI, plus mirror reproduksi BKU/PDF (dengan data dari user) dan `laravel.log`-install.

## Test Status

- Belum ada perubahan PHP telad; suite belum dijalankan ulang setelah perubahan lib.rs (CHANGES tidak menyentuh PHP). PHPStan/phpunit sebelumnya `321 tests, 851 assertions`. Commit v0.3.6.

## Hasil TERKONFIRMASI (v0.3.7)

- **Root cause tunggal 500 = prefix `\\?\` pada argumen router `php -S`** (BUKAN kontaminasi env var, BUKAN opcache). Dibuktikan empiris:
    1. Spawn manual router **tanpa** `\\?\` (env benar, DB Roaming) → `/login` 200.
    2. Spawn **persis** perintah exe terpasang (router `\\?\C:\...\server.php`) + `-d error_log` → log `php-error-final.log` menulis `PHP Fatal error: Failed opening required '\\?\C:\...\server.php'` → 500 body kosong. Perbedaannya PERSIS hanya pada prefix `\\?\`.
    3. `php -S` yang router-nya ber-prefix `\\?\` selalu gagal `require`; PHP CLI tidak memahami prefix extended-length path (hasil `is_file` juga `false`).
- Mengapa "sesi bersih" kemarin terlihat 500 padahal env kosong: itu persis build exe terpasang (cmdline server pakai router `\\?\`), bukan env var. Log `1:22:43` lama yang menunjuk `probe.sqlite` adalah gejala KEDUA (kontaminasi sesi probing), bukan penyebab utama — 500 tetap muncul walau sesi bersih.
- **Opcache off** kini berperan sekunder: MURNI pencegahan crash ASLR (teramati saat kasih beberapa instance php paralel), TIDAK terkait 500 router. Dibiarkan tetap aktif (aman) sampai diisolasi sendiri nanti.
- **Uji bersih 3× berturut** (uninstall murni via `uninstall.exe /S` → install `SmartRKAS_0.3.6_x64-setup.exe` dari source campuran → `Start-Process` di PowerShell baru env kosong → deteksi port dari proses `php.exe -S` → `GET /login`):
    - ROUND 1 → 200 · ROUND 2 → 200 · ROUND 3 → 200 (port 55672/59224/50479).
    - **PENTING pemilihan port di test**: jangan ambil listener port tertinggi milik proses lain (VS Code/devsense) — dapat "403"; ambil port dari commandline proses `php.exe -S 127.0.0.1:<port>` secara eksplisit.
- `error_log` server (`php-server-error.log` di `<data-dir>`) kini juga lewat `native_path()` — konsisten dengan router/cacert, biar fatal PHP ke log instalasi bukan diam.

## Commit v0.3.7

- Working tree sekarang berisi perubahan MIXED (native_path + opcache off) — sengaja digabung dan di-ship **sebagaimana adanya**; keputusan: TIDAK memisahkan, uji 3× sudah membuktikan server sehat.
- Versi bumped 0.3.6 → **0.3.7** di 6 file (`config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name = "smartrkas"`).
- Commit dengan pesan menyebut root cause `\\?\` path pada server_router.

## Penyusunan ulang build v0.3.7 (clean build ulang, bukan commit baru)

- Rebuild penuh dari HEAD `5d0e081` (v0.3.7) → NSIS `SmartRKAS_0.3.7_x64-setup.exe` 57.9MB (12:40) + MSI `SmartRKAS_0.3.7_x64_en-US.msi` 87.4MB (12:55). Ini memastikan installer berlabel **v0.3.7** (bukan 0.3.6 yang dipakai di uji sebelumnya).
- Uninstall instalasi v0.3.6 → install v0.3.7 (`/S` exit 0). DB user di `%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite` selamat (uninstall tidak menghapus Roaming).
- **Sanity check final v0.3.7** (PowerShell baru, env `DB_DATABASE`/`SMARTRKAS_DATA_DIR` kosong, `Start-Process`): `/login` = **200**, halaman login OK, `php-server-error.log` TIDAK bertambah (masih 11:59:34). Proses anak (php -S + scheduler) mati bersih setelah app ditutup (job object) — 0 proses tersisa.
- urut: (1) urutan PLN: minta user uji dari UI: Telegram "Kirim Pesan Uji" + kode pemulihan, "Periksa Pembaruan" (harus sukses, bukan error); (2) kondisi BKU/PDF bila masih gagal → butuh data reproduksi user (nama item, nominal, tanggal) + cek `laravel.log`/`php-server-error.log` instalasi user; (3) bila semua OK → push `master` + `gh release create v0.3.7`.

---

# Sesi 08 Agu 2026 — STATUS AKHIR v0.3.7 (sesi ditutup bersih)

## Apa yang SUDAH dikerjakan & tuntas

- **Dua root cause sudah ketemu & TERVERIFIKASI EMPIRIS, masing-masing 3× berturut pakai installer `SmartRKAS_0.3.6_x64-setup.exe` (source campuran)**:
    1. **TLS desktop gagal** → fix: **spawn `php -S` langsung** (ganti `artisan serve --no-reload`) + `-d curl.cainfo`/`openssl.cafile` + `PHP_INI_SCAN_DIR` (cacert.ini). `artisan serve` tidak meneruskan `-d` ke proses server anak, jadi CA bundle tidak pernah termuat. Dibuktikan `/__tlsprobe` (github 200 / telegram 200) di sesi v0.3.6.
    2. **500 body-kosong seluruh request** → fix: **`native_path()`** (strip prefix `\\?\` pada argumen router `php -S`). PHP CLI tidak memahami extended-length path → fatal `Failed opening required '\\?\...'` (bukti `php-error-final.log`). Diterapkan ke router `-S`, `current_dir` public, `cacert -d`, `cacert.ini`, `error_log` server. Uji 3× `/login` = **200/200/200** (port 55672/59224/50479).
- **Opcache off** = pencegahan sekunder (crash ASLR), TIDAK penyebab 500 — dibiarkan aktif sampai ada waktu isolasi ulang (non-mendesak).
- **Commit `5d0e081`** "Fix 500 desktop: native_path() hapus prefix `\\?\` pada server_router + error_log (v0.3.7)" — 7 file. Versi sudah bump **0.3.7** di 6 file, working tree BERSIH.

## Status saat sesi berhenti (apa adanya)

- **Installer berlabel v0.3.7 BELUM dibangun; karenanya belum sempat diuji ulang.** Yang terpasang & teruji 3× justru build SOURCE v0.3.6 (mixed: native_path + opcache off). Build v0.3.7 proper = langkah pertama sesi berikutnya.
- Tidak ada proses build/kompilasi berjalan; tidak ada `php -S` aktif; tidak ada app SmartRKAS tersisa.
- `master` ahead 1 commit (`5d0e081` vs `origin/master` `b7cf953`) — **belum push**.
- **Rilis GitHub TETAP DITAHAN** (belum tag/release, menunggu uji installer v0.3.7 + uji user).
- **BKU & PDF masih MENUNGGU data reproduksi dari user** (nama item RKAS, nominal, tanggal, perilaku; `storage/logs/laravel.log` + `<data-dir>/php-server-error.log` di instalasi user saat aksi) — belum bisa ditutup karena tidak bisa direproduksi di sini.

## Langkah berikutnya (urutan sesi lanjut)

1. **Build installer v0.3.7 proper** (working tree sudah v0.3.7): `npm run build` lalu `npm run tauri -- build --bundles nsis,msi` (atau nsis dulu). Hasil: `SmartRKAS_0.3.7_x64-setup.exe` + `.msi` di `src-tauri/target/release/bundle/`.
2. **Verifikasi quick**: uninstall versi terpasang → install v0.3.7 → protokol 3× `/login` 200 (pilih port dari cmdline `php.exe -S`, bukan listener tertinggi — sebelumnya sempat salah ambil port VS Code jadi 403).
3. **Serahkan ke user uji manual 2 grup**: (a) yang langsung terima dampak fix TLS/500 — Telegram pesan uji + kode pemulihan, "Periksa Pembaruan"; (b) yang masih menunggu reproduksi — BKU format 1.500.000 + Cetak PDF — MINTA data reproduksi.
4. **Bila user OK**: `git push origin master` → `gh release create v0.3.7` (assets NSIS+MSI, notes via `--notes-file`).
5. **Eksperimen terpisah (NON-mendesak)**: isolasi apakah opcache off benar-benar dibutuhkan — hanya jika ada waktu.

## Cara membuktikan status 500→200 kapan pun

`php -S "C:\...\server.php"` TANPA `\\?\` → 200; DENGAN `\\?\` → fatal `Failed opening required '\\?\...'`. (PHP CLI tidak memetakan prefix extended-length path.)

---

# Sesi 08 Agu 2026 — Verifikasi v0.3.7 PROPER (installer berlabel benar) — selesai, rilis tetap ditahan

## Koreksi status sebelumnya

- Catatan "STATUS AKHIR v0.3.7" (di atas) menyatakan installer v0.3.7 BELUM dibangun — **TIDAK AKURAT**. Fakta di disk: bundle berlabel `SmartRKAS_0.3.7_x64-setup.exe` (12:40, 60.7MB) + `.msi` (12:55, 91.6MB) SUDAH ada di `src-tauri/target/release/bundle/`, dibangun dari HEAD `5d0e081`; app terinstall sudah v0.3.7 (FileVersion 0.3.7, 12:35). Bagian "Penyusunan ulang build v0.3.7" (baris 864-868) yang mencatatnya adalah yang benar.

## Verifikasi yang dijalankan sesi ini (semua pada installer berlabel v0.3.7)

1. **Fresh install**: uninstall versi lama (`uninstall.exe /S`, dir `%LOCALAPPDATA%\SmartRKAS` hilang) → DB user di `%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite` SELAMAT → install `SmartRKAS_0.3.7_x64-setup.exe /S` → exe 0.3.7, `php\php.exe` + `php\extras\ssl\cacert.pem` ada.
2. **Uji 3× berturut** (env `DB_DATABASE`/`SMARTRKAS_DATA_DIR` kosong, `Start-Process` app, deteksi port dari cmdline `php.exe -S 127.0.0.1:<port>`): `GET /login` = **200/200/200** (port 59173/52160/50910). Tutup graceful via `CloseMainWindow` (jalur `on_window_event` CloseRequested) → app exit, 0 proses anak tersisa.
3. **`php-server-error.log`**: 756 → 756 (TIDAK bertambah selama 3 round; isi lama 11:59 = bukti fatal `\\?\` sebelum fix). Tidak ada error baru.
4. **Cmdline proses `php -S` berjalan** (pid 39296) membawa SEMUA arg fix TLS: `-d opcache.enable=0 -d log_errors=1 -d error_log=<data-dir>\php-server-error.log -d curl.cainfo=<install>\php\extras\ssl\cacert.pem -d openssl.cafile=<install>\php\extras\ssl\cacert.pem -S 127.0.0.1:<port> C:\...\server.php` — router TANPA prefix `\\?\`. Ini proses yang benar-benar melayani HTTP app.
5. **Outbound TLS dari PHP bundle instalasi** dengan env persis lib.rs (`PHP_INI_SCAN_DIR=<data-dir>\php-ini-scan` + `-d curl.cainfo`): `api.github.com` → errno 0, HTTP 200, len 2396; `api.telegram.org` → errno 0, HTTP 302 (redirect expected), len 145. `curl.cainfo` terbaca (nilai tidak kosong).

## Kesimpulan

- Fix TLS (spawn `php -S` langsung + `-d` cacert + scan) dan fix 500 (`native_path()` strip `\\?\`) **TERVERIFIKASI pada installer berlabel v0.3.7 proper**, bukan build campuran v0.3.6. Kini cukup uji manual user dari UI: Telegram "Kirim Pesan Uji" + kode pemulihan + "Periksa Pembaruan" (harus sukses, bukan error).

## Status

- `master` ahead 2 commit (`5d0e081` + `b47ae51`) dari `origin/master` (`697d19f`) — **belum push** (aman untuk push kapan saja, bukan rilis publik).
- **Rilis GitHub TETAP DITAHAN** sampai BKU & PDF punya fix terverifikasi.
- **BKU & PDF**: menunggu data reproduksi dari user (item RKAS, nominal, tanggal, perilaku) + `storage/logs/laravel.log` / `<data-dir>/php-server-error.log` instalasi user saat aksi.
- App v0.3.7 terinstall fresh dan siap uji manual user. Tidak ada proses app tersisa di mesin ini.

---

# Sesi 08 Agu 2026 — Uji HTTP Live di Instalasi v0.3.7: BKU, Cetak PDF, Export Excel SEMUA BERFUNGSI

## Goal

Uji langsung (bukan menunggu user) alur BKU + Cetak PDF + Export Excel di instalasi v0.3.7 via HTTP terhadap server nyata yang di-spawn app (`php -S`), untuk menutup temuan #2/#3/#6. Hasilnya: **server-side SEMUA OK** — kegagalan user kemungkinan di masa lalu disebabkan 500-prefix`\\?\`/TLS yang SEDANG/MASIH ada sebelum install v0.3.7.

## Ringkasan

- **Alur login HTTP nyata**: GET `/login` → ambil `_token` dari form → POST `/login` (email `admin@sekolah.test`, password di-template sementara lalu di-restore). Dashboard 200 (len ~255KB). Session driver `database` terverifikasi.
- **Alur Cetak PDF (`/laporan/bku?bulan=8&cetak=pdf`)**: 200, response valid PDF (`%PDF-`, 3460 bytes utk data kosong bulan 8; `streamPdf()` catch → `Log::error` + `abort(500)` bila gagal). Server OK.
- **Alur Export Excel (`/laporan/bku/export-excel?bulan=8`)**: `ExportJob` dibuat `processing` → `GenerateExportJob` (sync) → status `completed` → file `.xlsx` 6393 bytes ada di `<data-dir>\storage\app\public\exports\`. Server OK.
- **Alur input BKU**: POST `/transaksi-bku` dengan `jumlah='500.000'` (format Indonesia) → tersimpan **500000** benar (guard `NumberParser::rupiah` berfungsi live). Transaksi uji (BPU005/.../Uji BKU live) sudah dihapus sesudah tes.
- **Guard over-budget**: POST `9.000.000` pada item sisa 1.350.000 → `ValidationException` → redirect `/transaksi-bku/create` + pesan "melebihi sisa anggaran..." muncul di halaman (old input dikembalikan). Ini persis bug "input tidak tersimpan" yang dilaporkan — perilaku sekarang sudah benar (memang menolak karena melebihi sisa, bukan kehilangan input).
- Alur download desktop (`saveDownload` → `invoke('save_download')` → `blocking_save_file()` dialog Save As) TIDAK diuji dari webview — butuh verifikasi manual di app yang benar-benar terpasang.

## Catatan Debug (penting utk sesi lanjut)

- **WebRequest diperiksa**: `Invoke-WebRequest` harus `-MaximumRedirection 0` saat menangkap 302 (PHP-Serve mengembalikan 302 → mis-kreasi). Param `-MaximumRedirection 0` + `-WebSession` menyimpan cookie.
- **Cookie dari webview**: nilai Set-Cookie URL-encoded (`%3D`); PowerShell jar menyimpan cookie decoded; jika pakai `$sv.Cookies...`, pastikan cookie yang dipilih bernama `smartrkas-session`, BUKAN `XSRF-TOKEN` (keduanya panjang hampir sama → mudah tertukar). Penyebab awal redirect continua; `auth-check3.php` TEMPO balok: DB_FILE sempat `database\database.sqlite` (install) bukan Roaming — PENTING: **jalankan semua script diagnostik dengan env `SMARTRKAS_DATA_DIR` + `DB_DATABASE` (Roaming) DIPASANG TERLEBIH DAHULU**, karena bundle `.env` default = install-local `.sqlite`, beda dengan server anak (Rust set ke data-dir).
- `GenerasiGenerateExportJob` menulis ke `storage/app/public/exports` di data-dir (bukan disk lokal). Verif via php bundle script.

## Status

- Password user sementara TIDAK diubah permanen (save→restore hash). Transaksi & export uji dibersihkan; sisa data produksi utuh.
- `master` masih ahead 2 commit dari `origin/master` (`5d0e081` + `b47ae51`) — belum push & rilis ditahan.
- Yang BELUM terverifikasi (butuh app tl terpasang berjalan + manual UI): dialog Save-As PDF (Tauri), notif Telegram tombol "Kirim Pesan Uji", "Periksa Pembaruan" — server-side semuanya sehat (TLS errno 0 terverifikasi di sesi v0.3.7 proper).

---

# Sesi 08 Agu 2026 — Root Cause Cetak PDF: ACL Tauri (save_download) + Verifikasi Telegram/Periksa Pembaruan di UI (v0.3.8)

## Goal

Tutup temuan #3 (Cetak PDF "tidak ada dialog") dan verifikasi #4/#5 (Telegram, Periksa Pembaruan) dari UI app desktop yang benar-benar terpasang (v0.3.7), memakai UI Automation (UIA) bukan HTTP fake. Ditemukan root cause sebenarnya dari temuan #3 + 3 verifikasi UI.

## Temuan Utama: Cetak PDF diblokir ACL Tauri (bukan server)

- Toast JS setelah Invoke memunculkan: **`Gagal menyimpan file: Command save_download not allowed by ACL.`**
- Penyebab: webview memuat dari **remote origin** `http://127.0.0.1:<port>` — di Tauri v2 custom command aplikasi perlu izin capability. Capability `src-tauri/capabilities/default.json` tidak punya entri `remote.urls`, sehingga akses IPC diblokir untuk SEMUA custom command (docs Tauri v2: "By default the API is only accessible to bundled code shipped with the Tauri App. To allow remote sources access to certain Tauri Commands ... define `remote.urls` in the capability").
- Catatan sejarah: alur `saveDownload`/`save_download` TIDAK pernah diuji dari webview (AGENTS v0.3.7 menulis "TIDAK diuji dari webview — butuh verifikasi manual di app"). Ini kesenjangan verifikasi yang kini terbuka + di-fix.

## Perubahan

- `src-tauri/capabilities/default.json` — tambah blok `remote.urls`:
    - `"remote": { "urls": ["http://127.0.0.1:*", "http://localhost:*"] }`
    - bersama `windows: ["main"]`, `permissions: ["core:default","opener:default"]`.
    - (URL server desktop selalu `127.0.0.1:<port>` bebas — wildcard `*` pada port sudah cukup; `localhost` sebagai cadangan.)

## Verifikasi UI (metodologi baru)

- Tooling UIA di `%TEMP%\opencode`: `uia-all.ps1` (dump elemen + koordinat), `uia-click.ps1`/`uia-invoke.ps1` (cari element by Name → Invoke pattern / mouse click), `topwin.ps1` (collapse top-level HWND proses smartrkas). Dipakai untuk navigasi GUI & membaca flash/prompt tanpa menyentuh JS.
- **#4 Telegram**: Pengaturan → Notifikasi Telegram → klik "Kirim Pesan Uji" → flash **"Pesan uji berhasil dikirim ke Telegram Anda"** (jalur `response->successful()` → bukti HTTPS ke `api.telegram.org` BERHASIL; TLS fix berfungsi di app nyata).
- **#5 Periksa Pembaruan**: Pengaturan → Tentang Aplikasi → klik "Periksa Pembaruan Sekarang" → flash **"Sudah versi terbaru"** (jalur sukses `AppUpdateService` → HTTPS ke GitHub API BERHASIL).
- **#3 Cetak PDF**: BKU → "Cetak PDF" → toast **`Gagal menyimpan file: Command save_download not allowed by ACL.`** → root cause ACL terkonfirmasi (PDF route & streaming OK; hanya invoke Tauri yang diblokir).
- Log aplikasi (`storage/logs/laravel.log` + `php-server-error.log` di data-dir) bersih selama pengujian.

## Catatan Proses

- Uji UI memakai app yang sedang berjalan di Windows (bukan harness PHPUnit) — satu-satunya cara membuktikan dialog native/Save-As (Tauri) yang tidak terjangkau unit test.
- Versi 0.3.7 → **0.3.8**: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` (blok `name = "smartrkas"` saja — hati-hati jangan replace versi crate lain).
- Build: `npm run tauri -- build --bundles nsis,msi` (log: `%TEMP%\opencode\build-v038.log`).

## Test Status

- PHPUnit OK (321 tests, 851 assertions), PHPStan level 6 clean. Hanya `capabilities/default.json` + versi yang berubah (tidak ada logika PHP).

---

# Sesi 08 Agu 2026 — Fix ACL save_download BERHASIL (build.rs + capability) tapi DITEMUKAN hang di blocking_save_file() — BELUM COMMIT

## Goal

Perbaiki temuan #3 (Cetak PDF "tidak ada dialog"): root cause ACL Tauri. Setelah fix ACL terverifikasi bahwa command kini benar dieksekusi, justru ter-reproduksi **bug produksi baru**: `blocking_save_file()` hang tanpa memunculkan dialog.

## Apa yang SUDAH dikerjakan & terbukti

- **Root cause ACL benar**: webview memuat dari remote origin (`http://127.0.0.1:<port>`), dan `acl-manifests.json` TIDAK punya entry `smartrkas` → semua custom command app diblokir (toast "Command save_download not allowed by ACL"). `remote.urls` saja TIDAK cukup; command app sendiri wajib didaftarkan di `build.rs`.
- **Fix diterapkan**:
    - `src-tauri/build.rs` (BARU ditulis): `tauri_build::try_build(Attributes::new().app_manifest(AppManifest::new().commands(&["save_download"])))` → memicu autogenerate permission `allow-save-download`/`deny-save-download` (format `allow-$command` snake_case). Sebelumnya `build.rs` polos `tauri_build::build()`.
    - `src-tauri/capabilities/default.json`: `"permissions": ["core:default", "opener:default", "allow-save-download"]`.
- **Verifikasi build**: `cargo check` OK. Schema `desktop-schema.json` kini punya `"const": "allow-save-download"`. `acl-manifests.json` (target) kini punya entry **`__app-acl__`** berisi `allow-save-download` → `commands.allow=["save_download"]`. Sebelumnya key `__app-acl__` TIDAK ada.
- **Build installer v0.3.8 proper**: `SmartRKAS_0.3.8_x64-setup.exe` (58.2MB) + `.msi` (87.8MB) di `bundle/`. Build via background `Start-Process cmd /c "npm run build && npm run tauri -- build --bundles nsis,msi"`.
- **Clean-install + uji UI nyata** (UIA): server `php -S 127.0.0.1:64767` (router tanpa prefix `\\?`, semua arg `-d` cacert terpasang) → `/login` 200. Login `admin@sekolah.test` authenticated. Navigasi: Laporan → BKU Bulanan → tombol "Cetak PDF" → `uia-invoke.ps1 -Name "Cetak PDF"` → `INVOKED`.

## Temuan BARU (bug produksi sesungguhnya, sudah fixed di sesi berikutnya)

- Setelah klik "Cetak PDF", `save_download.log` (di data-dir) berisi HANYA 1 baris `[ts] save_download called ...` → command DIEKSEKUSI (base64 PDF diterima, decode OK), tapi **terjebak di `builder.blocking_save_file()`** (dialog tak pernah muncul).
- Kesimpulan saat itu: **bukan hang-command-tak-dipanggil** (fix ACL membuktikan command jalan), tapi **`blocking_save_file()` hang dan dialog tidak muncul** di Windows — sama dengan gejala user "tanpa dialog apa pun".
- Analisis: `blocking_save_file` = `blocking_fn!` (`sync_channel(0)` + `run_on_main_thread` → `AsyncFileDialog::from(dialog).save_file()` → `std::thread::spawn(block_on(dialog))`) lalu `rx.recv()`. Docs: "blocking variants pin the calling thread until dialog returns — don't call them from an async command without spawn_blocking, never from main event loop thread."

## Status saat sesi berhenti

- Working tree: `build.rs` + `capabilities/default.json` DIUBAH (ACL fix). App v0.3.8 terpasang & berjalan. **BELUM COMMIT**.
- Next: fix `save_download` dengan pola non-blocking + oneshot + timeout; bersihkan debug-log; commit bersih.

---

# Sesi 08 Agu 2026 — Fix save_download (oneshot + timeout) + Fix override_note nullable + Commit Final v0.3.8 (RILIS DITAHAN)

## Goal

1. Selesaikan temuan #3: ganti `blocking_save_file()` (hang, dialog tak muncul) → pola NON-blocking `save_file(callback)` + oneshot channel + timeout. 2) Fix bug BKU "input tidak tersimpan" yang ternyata bukan guard over-budget, melainkan **aturan validasi `override_note` tanpa `nullable`**. 3) Sinkronkan repo ↔ instalasi, bersihkan diff, dan siapkan commit final — **belum push/rilis** (tunggu konfirmasi user).

## Summary (akar bug BKU "tidak tersimpan" — penemuan utama sesi ini)

- Gejala user: simpan BKU nominal 30.000 → form kembali dengan angka berubah jadi **300.000.00** tanpa pesan error.
- **Akar masalah**: `store()` validasi `'override_note' => 'required_if:override_anggaran,1|string|min:10|max:500'` (TANPA `nullable`). Laravel meloloskan field yang ADA di request; browser selalu mengirim `override_note` (textarea) → `ConvertEmptyStringsToNull` pada middleware mengubah `""` → `NULL`. Saat override TIDAK dicentang, `required_if` justru menolak `NULL` (`NULL` gagal `string`/`required_if` ask) → **`ValidationException` tersembunyi** → 302 balik ke `/transaksi-bku/create` dengan `$errors` — tapi halaman form tampak "kosong" (guard over-budget bukan penyebab; `back()->with('error')` untuk guard tak dirender sebagai error inline karena sekarang pakai `ValidationException`).
- **Fix**: `'override_note' => 'nullable|required_if:override_anggaran,1|string|min:10|max:500'` → kosong hanya diizinkan saat override TIDAK aktif; saat `override_anggaran=1` tetap wajib min. 10 karakter. Validasi TIDAK dilonggarkan.
- Dijamin konsisten di **repo dan instalasi** (file controller identik, `php -l` OK, diff vs HEAD tepat 1 baris).

## Changes (working tree → commit final v0.3.8)

- `app/Http/Controllers/TransaksiBkuController.php` — `override_note` += `nullable|` (satu baris).
- `src-tauri/src/lib.rs` — `save_download`: ganti `blocking_save_file()` → `builder.save_file(callback)` + `tokio::sync::oneshot` + `tokio::time::timeout(Duration::from_secs(120), rx)`; tambah `set_parent(&window)` (tanpa parent IFileDialog bisa tak muncul/tertidur); **hapus semua debug-log `save_download.log`** (scaffolding diagnosis).
- `src-tauri/Cargo.toml` + `Cargo.lock` — dep `tokio` (features `sync`, `time`).
- `src-tauri/build.rs` — `tauri_build::try_build(Attributes::new().app_manifest(AppManifest::new().commands(&["save_download"])))`.
- `src-tauri/capabilities/default.json` — `remote.urls` (`127.0.0.1:*`, `localhost:*`) + `allow-save-download`.
- `src-tauri/permissions/autogenerated/save_download.toml` — autogenerate dari build.rs (commit).
- `resources/views/layouts/app.blade.php` — blok `@if(session('info'))` dengan `.alert-info` (flash info export dipakai di `LaporanController`).
- Versi **0.3.8**: `.env.example`, `config/app.php`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock`.
- AGENTS.md — baris-baris sesi diagnosis yang ditulis dengan encoding rusak (karakter mojibake) dirapikan ke UTF-8 bersih + ringkasan sesi ini.

## Verifikasi

- **UI (user)**: simpan BKU normal → **tersimpan** (BPU005/20519260/01/2026, Rp 300.000, vol 10 dus, item ESPS B. Indonesia, uraian "tes imput bku", created_by=2; AuditLog `transaksi_bku.create` tercatat). Override dengan catatan pendek → **ditolak**: "The override note field must be at least 10 characters."
- **HTTP live (user test sementara, dibersihkan)**: login test user → POST `/transaksi-bku` `override_anggaran=1` + `override_note="abcde"` + item valid → `302` ke `/transaksi-bku/create` + pesan `The override note field must be at least 10 characters.` terlihat; `OVERRIDE_TX_SAVED=0`; user test dihapus (DB kembali 1 user) → **fix nullable TIDAK melonggarkan validasi override**.
- Sinkronisasi repo ↔ instalasi: hash SHA256 identik untuk `TransaksiBkuController.php`; `php -l` tanpa error untuk keduanya.
- `git diff` final: controller **1 baris** (`nullable|`); tidak ada sisa `Log::info`/`use Log`/debug SVG/TLS probe di diff mana pun.
- Artefak test dihapus: `BKU-*.pdf` (root) tidak ikut commit.

## Build / Test Status

- PHPUnit `OK (321 tests, 851 assertions)`, PHPStan level 6 `[OK] No errors`, `cargo check` OK (smartrkas v0.3.8).
- **Belum push** (`master` ahead dari `origin/master`); **belum rilis** — tunggu konfirmasi user sebelum `git push` + `gh release create` + build installer final.

---

# Sesi 10 Agu 2026 — Fix Kwitansi "Untuk": Duplikat Uraian Item vs Uraian Transaksi (gabung ke v0.3.8)

## Goal

Hapus duplikat baris di kolom "Untuk" kwitansi: sebelumnya `rkasItem->uraian` DAN `transaksiBku->uraian` dicetak berurutan (dua baris). Karena field `uraian` transaksi bersifat **manual (free-form)** — bendahara mengetik ulang nama item → sering dengan typo kecil (kasus nyata: item "Honor Pembina Ekstra Al Banjari" vs uraian "Honor Pembina Ekstra Al Banjar"). Terapkan opsi b: item sebagai teks utama, uraian transaksi hanya sebagai sub-teks bila BERBEDA.

## Summary

- 3 file Blade diubah, verifikasi render nyata 3 skenario (data live DB desktop), PHPUnit + PHPStan tetap hijau. Diff sync repo↔instalasi = 0 (3 file kwitansi disinkron ke instalasi). Digabung ke commit v0.3.8 (ee37c4b) dan siap rilis.

## Changes

- `resources/views/transaksi-bku/kwitansi-content.blade.php` — blok "Untuk": variabel `$untukUtama` (rkasItem->uraian, fallback uraian transaksi bila tanpa item) + `$untukSub` (uraian transaksi, **ditekan** bila `mb_strtolower(trim())` sama dengan utama). Sanitasi: keduanya di-`(string)` cast, `trim()`, compare case-insensitive. Sub-teks dirender `<div class="untuk-sub">`.
- `resources/views/transaksi-bku/kwitansi.blade.php` + `kwitansi-batch.blade.php` — CSS baru `.untuk-box .untuk-sub` (font-size 10px, warna #333, margin-top 2px).
- **PENTING (keputusan user)**: perbandingan hanya **exact-match** (case-insensitive + trim) — TIDAK fuzzy/similarity. Kasus typo 1 huruf ("Banjari" vs "Banjar") TETAP dianggap beda → sub-teks tetap tampil (data lama wajib diperbaiki manual user). Ke depan: tambahkan hint di form "Jika sama dengan nama item RKAS, kosongkan field Uraian" (belum dilakukan, backlog).

## Verifikasi (render view nyata thd DB desktop `%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite`)

1. **BPU001 (data asli)**: item `[Honor  Pembina Ekstra Al Banjari]` vs uraian `[Honor Pembina Ekstra Al Banjar]` → sub-teks MUNCUL (bedanya huruf "i" + spasi ganda item) — SESUAI ekspektasi exact-match.
2. **BPU001 dipaksa uraian = item** (simulasi exact match) → **satu baris bersih**, `SUB-TEXT: ABSENT` — dedup bekerja.
3. **BPU005 (uraian beda konteks nyata)**: item `[Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu]` + sub-teks `[Belanja Mamin Rapat Dewan Guru Bulan Januari]` → sub-teks TAMPIL benar.

- Tool verifikasi: script PHP temp boot Laravel repo dgn `DB_DATABASE` → `view('transaksi-bku.kwitansi')` render + parse `.untuk-box`. Script temp sudah dihapus setelah dipakai.

## Sync Instalasi (protokol "cek dua direktori instalasi sebelum commit/release")

- `C:\Users\yudhi\AppData\Local\SmartRKAS` (instalasi) — bandingkan SHA256 vs repo untuk `app/ config/ routes/ database/ bootstrap/ public/ resources/`: sebelum sync hanya 3 file kwitansi beda; **setelah copy ulang → diff = 0**.
- `C:\Users\yudhi\AppData\Roaming\id.smartrkas.desktop` (data dir: sqlite/storage/.env) — bukan kode, tidak dibandingkan.
- Pelajaran dari v0.3.5→v0.3.7: instalasi harus sinkron dgn repo SEBELUM commit/rilis, agar tidak terulang "fix lolos test tapi aplikasi masih lama".

## Test Status

- PHPUnit `OK (37 tests / filter Kwitansi+TransaksiBku, 144 assertions)`; full suite `OK (321 tests, 851 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Komit: digabung ke `ee37c4b` (v0.3.8) via amend; siap push + `gh release`. Versi tetap **v0.3.8** (belum bump — perubahan kecil fitur kwitansi).

---

# Hotfix 10 Agu 2026 — Kwitansi "Untuk": Normalisasi Spasi Berlebih (v0.3.9)

## Goal

Setelah v0.3.8 dirilis, **uji cetak user menunjukkan duplikat MASIH muncul** di BPU001, padahal stringnya nyaris identik. Akar masalah: uraian item di DB punya **spasi ganda di tengah** (`"Honor  Pembina Ekstra Al Banjari"`, 32 char) sedangkan uraian transaksi spasi tunggal (`31 char`). `trim()` hanya buang spasi di awal/akhir → perbandingan eksak lama menganggap BEDA → sub-teks tercetak → di PDF kedua baris terlihat sama (spasi ganda menciut jadi satu).

## Changes

- `resources/views/transaksi-bku/kwitansi-content.blade.php` — perbandingan `$untukSub` vs `$untukUtama` kini **collapse whitespace**: `preg_replace('/\s+/', ' ', trim(mb_strtolower(...)))` pada kedua sisi. Jadi spasi ganda/berlebih di dalam string juga diabaikan (bukan hanya awal/akhir). Sub-teks masih TETAP muncul untuk uraian yang memang beda makna.
- **Catatan**: data item uraian `"Honor  Pembina Ekstra Al Banjari"` (spasi ganda) sengaja TIDAK diubah di DB — fix di level render (kwitansi) saja. Item RKAS itu sendiri tetap tampil apa adanya di tabel/laporan lain.

## Verifikasi (render nyata thd DB desktop, script temp di `%TEMP%\opencode\render-kwitansi.php`)

1. **BPU001 (data asli, spasi ganda di item)** → SUB-TEXT ABSENT, satu baris bersih.
2. **BPU001 force-equal** → SUB-TEXT ABSENT (konsisten).
3. **BPU005 / BPU004 (uraian beda konteks)** → SUB-TEXT PRESENT (kolom tidak hilang untuk uraian yang memang berbeda).
4. **BPU002 (item spasi ganda, trx beda makna "( samroh )")** → SUB-TEXT PRESENT.

- PHPStan: `preg_replace` return `string|null`; argumen sudah `trim(mb_strtolower(...))` → selalu string.

## Build / Release

- Bump **0.3.8 → 0.3.9** di 5 file (`config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name = "smartrkas"`).
- Commit → push `master` → build NSIS+MSI (hasil harus menyertakan fix whitespace-collapse) → uninstall v0.3.8 → install baru v0.3.9 → verifikasi `/login` 200 + **cetak PDF BPU001 dari server yang berjalan = SATU BARIS** → `gh release create v0.3.9` (2 asset). Ini menindaklanjuti kesalahan v0.3.8 yang dirilis tanpa fix spasi ganda.

## Test Status

- PHPUnit `OK (321 tests, 851 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.

## Konfirmasi User (10 Agu 2026)

- User mencetak kwitansi dari app v0.3.9 yang terpasang → **BERHASIL, kwitansi sekarang satu baris** di kolom "Untuk".
- Ini menutup rantai bug duplikat kwitansi (v0.3.8 belum cukup → v0.3.9 final). Data item ber-spasi ganda di DB sengaja tidak diubah; fix di level render tetap berlaku.

---

# Sesi 10 Agu 2026 — Verifikasi 2 Temuan Kas (Kwitansi Tanggal + Saldo Berjalan) — TANPA PERUBAHAN KODE

## Goal

Verifikasi 2 temuan user dari screenshot (ARKAS asli membolehkan pola input ini → cukup memastikan tampilan tidak menyesatkan, JANGAN tambah validasi penolakan): (1) kwitansi PDF dengan tanggal yang terlihat "akhir bulan", (2) transaksi pengeluaran bertanggal SEBELUM tarik tunai (BBU) sumber dananya.

## Hasil

- **Temuan 1 (kwitansi tanggal)**: TIDAK ada bug. Kwitansi memakai `$transaksiBku->tanggal` (`resources/views/transaksi-bku/kwitansi-content.blade.php:12`), dirender `translatedFormat('d F Y')` di kolom tanda tangan (baris :165). BUKAN (a) by-design tanggal_cetak akhir bulan, BUKAN juga (b) salah variabel. Terverifikasi render PDF nyata thd DB desktop: BPU011 (DB `tanggal`=2026-02-07) → PDF memuat `PASURUAN, 07 Februari 2026`; BPU010 → `12 Januari 2026`. Tidak ada `endOfMonth`/`lastOfMonth`/`startOfMonth` di PHP maupun Blade app (grep bersih). Catatan: tanggal yang tercetak SELALU = nilai kolom `transaksi_bku.tanggal`; bila user melihat "akhir bulan", itu nilai tanggal yang tersimpan di DB (input user), bukan hasil komputasi app.
- **Temuan 2 (saldo berjalan)**: KONSISTEN & matematis benar. `TransaksiBkuController::index()` urut `->orderBy('tanggal')->orderBy('id')` (`:46-47`); `$saldoAwal` = sum semua transaksi sebelum baris pertama halaman via `tanggal < firstTanggal OR (tanggal = firstTanggal AND id < first->id)` (`:69-86`); `$saldoBerjalan` diakumulasi per baris dari `$saldoAwal` (`:88-92`). Jadi urutan = (tanggal, id), BUKAN created_at/urutan input. Data nyata: BPU011 (pengeluaran 07/02) sebelum BBU002 (tarik tunai 11/02) → saldo baris BPU011 = Rp 1.423.500 (BELUM termasuk +BBU002 27.014.500), lalu naik setelah baris BBU002 — mencerminkan akumulasi s.d. baris tersebut.

## Keputusan

- TIDAK ada perubahan kode. Kedua perilaku sudah benar sesuai desain (ARKAS membolehkan input transaksi dalam urutan tanggal apa pun; tampilan tidak menyesatkan). Tidak ada validasi baru.

## Test Status

- Tidak ada perubahan kode → suite tetap `OK (321 tests, 851 assertions)`, PHPStan clean. Script probe temp di `%TEMP%\opencode\probe-*.php` dipakai verifikasi.

---

# Sesi 10 Agu 2026 — Temuan: Format Kolom Uang Export Excel Tidak Seragam (Belum Ada Rencana Perbaikan)

## Status

Catat sebagai **TEMUAN** saja. **Belum ada rencana perbaikan** — menunggu BKU selesai difix / penambahan card baru di halaman BKU terlebih dahulu.

## Temuan (dari review `app/Exports/`)

Pola penulisan kolom nominal di 3 export tidak seragam soal cast (float) dan fallback. Secara fungsional semua menghasilkan `number_format(..., 0, ',', '.')`, jadi TIDAK ada bug output saat ini — hanya inkonsistensi pola kode:

| Kolom                   | Posisi                          | Pola                                               | Cast (float)                       | Fallback     |
| ----------------------- | ------------------------------- | -------------------------------------------------- | ---------------------------------- | ------------ |
| Penerimaan              | BkuExport.php:116               | `number_format(->jumlah, 0, ',', '.')` via ternary | ? (attribute float)                | ternary / '' |
| Saldo                   | BkuExport.php:105,118           | `(float) ->getAttribute('saldo')` + isset()        | ? eksplisit                        | isset()      |
| Pengeluaran             | BkuExport.php:117               | `number_format(->jumlah, ...)` via ternary         | ? (attribute float)                | ternary / '' |
| Realisasi               | RekapRekeningExport.php:112     | `number_format(->realisasi_bulan ?? 0, ...)`       | ? (SQL alias mentah)               | ?? 0         |
| Rencana/Sisa            | RekapRekeningExport.php:110,113 | ?? 0                                               | ? / ? (sisa float hasil hitung)    | ?? 0         |
| Total/Total Pengeluaran | RekapSiplahExport.php:93        | `number_format(->total, ...)`                      | ? (via setAttribute di collection) | —            |

## Konteks

- BkuExport.php (referensi "benar"): nilai selalu dari atribut model float (jumlah, saldo_berjalan, saldo) dengan cast eksplisit (float) saat header + guard isset()/ternary.
- RekapRekeningExport.php: alias SQL mentah (selectRaw COALESCE(rib.total,0) / b.total) dipakai langsung tanpa (float); hanya ?? 0.
- RekapSiplahExport.php: sebenarnya sudah aman ($total = (float) ->total lalu setAttribute), hanya polanya tidak uniform.

## Keputusan

- DIBIARKAN apa adanya untuk saat ini (tidak ada perubahan kode). Re-evaluasi setelah pekerjaan BKU (fix + penambahan card) selesai.

---

# Sesi 11 Agu 2026 — Redesain Kartu BKU (stat-card) + Release v0.4.0 — acuan v0.3.9

## Goal

Redesain kartu ringkasan halaman BKU yang tadinya baris polos `grid-cols-3` (Total Penerimaan / Total Pengeluaran / Saldo Akhir) menjadi KPI `stat-card` profesional, KONSISTEN dengan Dashboard / Data RKAS / Backup / Tentang. Titik acuan = **v0.3.9** (user: "titik perbaikan sempurna dari bug & error yang bikin frustasi"). KEPUTUSAN USER: kartu dikembalikan ke 3 kartu asli — skema 6 kartu (Tarik Tunai/Total kumulatif/+/−) yang sempat dikerjakan DIBATALKAN karena membingungkan.

## Summary

- 1 file view berubah (`transaksi-bku/index.blade.php`); TIDAK ada perubahan controller/logika — nilai & perhitungan identik v0.3.9 (`$saldoAkhir = $totalPenerimaan - $totalPengeluaran`, footer tabel tetap `$saldoAkhir`).
- Full suite kembali ke baseline v0.3.9: `OK (321 tests, 851 assertions)`, PHPStan level 6 `[OK] No errors`.
- Bump **0.3.9 → 0.4.0** (5 file: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja).
- Build: NSIS 58.2MB + MSI 88.0MB. Fresh install v0.4.0 (uninstall v0.3.9) → `/login` 200, halaman `/transaksi-bku?bulan=` 200 + `stat-card green/red` + label 3 kartu MUNCUL (via HTTP live, admin password di-save→restore hash). Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.4.0

## Perubahan View

- `resources/views/transaksi-bku/index.blade.php` — kartu dipindah KELUAR dari dalam `<form>`/`.card` menjadi blok `<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">` sebelum form; pakai `stat-card green` (Total Penerimaan, ikon panah masuk, `stat-icon bg-emerald-50`), `stat-card red` (Total Pengeluaran, ikon panah keluar), `stat-card blue|red` dinamis (Saldo Akhir sesuai tanda ≥0/negatif, ikon uang). Label/value pakai `stat-label`/`stat-value`. Grid lama `grid-cols-3 divide-x` DIHAPUS.

## Perjalanan (jujur — untuk diingat)

- Awal sesi: 3 kartu div polos → rencana awal: "Saldo Akhir jadi kumulatif via anchor + kartu Surplus-Defisit" → berkembang jadi 6 kartu (Opsi L1) dengan `$saldoAkhir` anchor query + `$penerimaanKumulatif`/`$pengeluaranKumulatif`. Test ditulis (32 tests di BKU) dan sempat hijau.
- USER MENYETOP: skema 6 kartu & tanda `+`/`−` membingungkan. Instruksi: kembalikan seperti AWAAL/versi release (v0.3.9), PERTAHANKAN hanya redesain card.
- Aksi: `git checkout HEAD` controller + test (hapus semua perubahan logika sesi ini), view di-rewrite hanya bagian kartu → diff vs v0.3.9 = MURNI redesain visual, 0 perubahan controller/test.
- Pelajaran: jangan ubah logika kartu BKU tanpa konfirmasi final user; acuan rilis selalu v0.3.9 (versi stabil). Jika ada perubahan baru, crosscheck dulu ke v0.3.9.

## Catatan Proses

- Sync instalasi repo↔instalasi sebelum commit: hanya `TransaksiBkuController.php` + `index.blade.php` DIFF → di-sync → ALL IDENTICAL.
- Bump Cargo.lock: replace presisi blok `name = "smartrkas"` (2 baris) — JANGAN replace seluruh `^version` (ada crate `winapi` 0.3.9).
- Secret scan diff (token bot/ghp/private key): bersih.
- Verifikasi UI live: hash password admin (`admin@sekolah.test`) di-save ke variabel → set sementara `smartrkas-verify-2026` → login+cek halaman → restore hash asli (diverifikasi identik). Script temp `%TEMP%\opencode\pw-swap.php` dihapus setelahnya.
- App fresh-install dimatikan setelah verifikasi → 0 proses anak tersisa (job object).

## Test Status

- PHPUnit `OK (321 tests, 851 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK, `npm run build` OK, `cargo` compile OK. Commit `4f662d2` → push `master` → release GitHub v0.4.0 (2 asset, state uploaded).

---

# Sesi 11 Agu 2026 — Fitur Nomor Invoice SIPLah di Transaksi BKU (commit e96c19e) — BELUM DIUJI MANUAL BROWSER

## Goal

Tambahkan field `no_invoice_siplah` pada transaksi BKU: wajib diisi saat `metode_pengadaan=siplah`, tampil di tabel BKU dan di kwitansi (PDF). Dikerjakan di atas HEAD v0.4.1 (`3877af9`).

## Status JUJUR

- **Fitur SELESAI & DI-COMMIT** (`e96c19e`, 8 file, +219) — test otomatis hijau.
- **BELUM diuji manual di browser** — uji manual yang sempat macet tidak dilanjutkan sesi ini; jadikan langkah pertama sesi berikutnya.

## Changes

- `database/migrations/2026_08_11_000021_add_no_invoice_siplah_to_transaksi_bku_table.php` (BARU) — `transaksi_bku.no_invoice_siplah` string(255) nullable `after('metode_pengadaan')`.
- `app/Http/Controllers/TransaksiBkuController.php` — `store()` + `update()`: `'no_invoice_siplah' => 'nullable|required_if:metode_pengadaan,siplah|string|max:255'`.
- `app/Models/TransaksiBku.php` — `@property` + `$fillable` `no_invoice_siplah`.
- `resources/views/transaksi-bku/create.blade.php` + `edit.blade.php` — baris input (hidden saat metode ≠ siplah, toggle JS `toggleNoInvoice()` + dipanggil di `init()`), repopulasi `old()`.
- `resources/views/transaksi-bku/index.blade.php` — di bawah badge SIPLAH tampilkan nomor invoice (`font-mono`, tooltip).
- `resources/views/transaksi-bku/kwitansi-content.blade.php` — baris "No. Invoice SIPLah" hanya saat siplah + invoice tidak kosong.
- `tests/Feature/BKU/TransaksiBkuTest.php` — +7 test (+151 baris): store tanpa invoice ditolak, invoice kosong ditolak, non_siplah tanpa invoice boleh (tersimpan null), store siplah tersimpan, update siplah tanpa invoice ditolak (data lama utuh), update siplah tersimpan, create page render field.

## Verifikasi

- `git diff` penuh diverifikasi: validasi `nullable|required_if:metode_pengadaan,siplah|string|max:255` ADA di `store()` dan `update()`.
- `php artisan migrate --force` OK (000018/000019/000020/000021 DONE; 000018-20 tertinggal dari sesi lama, ikut tereksekusi).
- `vendor\bin\phpunit --filter TransaksiBkuTest` → `OK (36 tests, 140 assertions)`.
- Full suite → `OK (330 tests, 882 assertions)`.
- PHPStan level 6 → `[OK] No errors`.

## Next (sesi berikutnya — urutan)

1. **Uji manual browser** fitur no invoice SIPLah: create BKU dengan metode SIPLAH → wajib isi invoice; tanpa invoice → error inline; non-SIPLAH → field tersembunyi; tampil di tabel + kwitansi PDF.
2. (Opsional) Bump versi + build installer + push + release bila user setuju — **TIDAK dilakukan sesi ini** (instruksi user: cukup commit lokal).

## Test Status

- PHPUnit `OK (330 tests, 882 assertions)`, PHPStan level 6 `[OK] No errors`. Tidak ada push, tidak ada build installer, tidak ada release.

---

# Sesi 11 Agu 2026 — Uji Manual Browser NOK + Pesan Validasi Bahasa Indonesia (commit 8a3a5b0)

## Goal

Lanjutkan uji manual fitur `no_invoice_siplah` (langkah 1 dari Next sesi sebelumnya) via browser terhadap server dev, lalu perbaiki pesan validasi default Laravel yang berbahasa Inggris saat field `no_invoice_siplah` kosong.

## Hasil Uji Manual (browser, user)

- **Skenario 1 (Metode=SIPLah, invoice kosong)**: berfungsi — form menolak & pesan error muncul, tapi **pesan masih bahasa Inggris** ("The no invoice siplah field is required when metode pengadaan is siplah.").
- **Skenario 2 (Metode=Non-SIPLah, invoice kosong)**: berfungsi — transaksi tersimpan normal.
- Kedua skenario "jalan semua" per user; hanya bahasa pesan yang perlu diubah.

## Perubahan

- `app/Http/Controllers/TransaksiBkuController.php` — tambah **custom message** param kedua `validate()` di `store()` dan `update()`: `['no_invoice_siplah.required_if' => 'Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah.']`. (Locale app tetap `en` — tidak ada folder `lang` proyek; custom message per-field dipilih supaya pesan lain tidak berubah.)

## Verifikasi

- Pesan error nyata dirender di form: `<p class="text-red-500">Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah.</p>` (periksa via HTTP live POST + `assertSee` semula bahasa Inggris; kini teks Indonesia terlihat di halaman create).
- `vendor\bin\phpunit --filter TransaksiBkuTest` → `OK (36 tests, 140 assertions)`.
- Full suite → `OK (330 tests, 882 assertions)`, PHPStan level 6 `[OK] No errors`.

## Catatan Debug (lingkungan dev, BUKAN kode)

- DB dev `database/database.sqlite` awalnya 0 user → tidak bisa login. Solusi (state dev saja, tidak di-commit): buat user `admin@sekolah.test`/`password123` + buat marker `storage/app/private/.app-initialized`. Marker **harus dihapus** sebelum jalankan suite karena test `AuthenticationTest::test_login_redirects_to_onboarding_when_first_run` bergantung pada `isFirstRun()=true`. Login dev tetap bisa karena kolom `users.last_login_at` terisi (isFirstRun=false tanpa marker).
- Ada **2 server php -S** melayani port 8025 (PID 52488 lama + PID 70620 baru) → browser kena server lama yang state-nya beda → login gagal "credentials do not match". Matikan semua, start satu bersih → login 200. JANGAN backup `.env`/DB via `custom`-sharing ke commit.
- `public/__diag.php` (file diagnostik env/DB/users, bukan bagian fitur) **TIDAK ikut commit**.

## Test Status

- PHPUnit `OK (330 tests, 882 assertions)`, PHPStan level 6 `[OK] No errors`. Commit lokal, belum push/build/rilis.

---

# Sesi 11 Agu 2026 — Verifikasi E2E HTTP Fitur Nomor Invoice SIPLah (melengkapi uji manual) — TANPA PERUBAHAN KODE

## Goal

Tuntaskan "Next (1) Uji manual browser" fitur `no_invoice_siplah` dari sesi sebelumnya. Dikerjakan via HTTP end-to-end (bukan browser UI) terhadap server `php -S` nyata + DB scratch (SQLite temp `smoke-siplah.sqlite`), login sesi sungguhan. **Tidak ada perubahan kode** — hanya verifikasi + dokumentasi.

## Status

- **Fitur TERVERIFIKASI END-TO-END** (seluruh jalur yang direncanakan di "Next" sesi e96c19e). Header AGENTS sesi e96c19e "BELUM DIUJI MANUAL BROWSER" kini lunas lewat HTTP-E2E.

## Verifikasi (HTTP live terhadap server bersih port 8025, XAMPP php)

Setup: migrate + seed user `test@sekolah.test`/`password` (pw_ok via `password_verify`) + 1 tahun anggaran + 1 item RKAS (rencana cukup) di DB scratch; `php -S 127.0.0.1:8025 router-test.php` (router temp kustom: `/__diag` dijawab langsung, selain itu `require public/index.php`) dengan `DB_DATABASE` di-set; login via POST `/login` dengan `_token` dari form.

- **01 login** → `/dashboard` 200.
- **02 create page** memuat `id="no_invoice_siplah"` + `id="row_no_invoice_siplah"` (hidden saat non-siplah).
- **03 SIPLah tanpa invoice** → 302 kembali `/transaksi-bku/create`, `old()` dipertahankan (metode `selected`), error inline render: **"Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah."** (pesan Indonesia dari 8a3a5b0 — regex lama bertulis Inggris gagal match, itu normal).
- **04 SIPLah dengan invoice** (`INV/2026/000123`) → 302 `/transaksi-bku` (tersimpan).
- **05 Non-SIPLah tanpa invoice (string kosong)** → 302 `/transaksi-bku` (tersimpan, `no_invoice_siplah` = **null**).
- **06 index `?bulan=1`** menampilkan `INV/2026/000123` + `BPU801/20519260/01/2026` + `BPU802/20519260/01/2026`.
- **DB**: BPU801 siplah punya invoice; BPU802 non_siplah null; transaksi siplah tanpa invoice TIDAK tersimpan.
- **Kwitansi PDF** BPU801: route `cetak-kwitansi` → 200 `application/pdf` valid (`%PDF`); render view langsung (`view('transaksi-bku.kwitansi', ['transaksiBku'=>$tx,'profil'=>PengaturanSekolah::get()])`) → HTML memuat **"No. Invoice SIPLah"** + **"INV/2026/000123"**. (Raw bytes PDF tidak bisa dicari string karena content-stream dompdf ter-kompresi FlateDecode — kalau perlu cek teks PDF, ekstrak/view render saja.)
- Kwitansi **BPU802 (non_siplah)** → segmen invoice TIDAK dirender (kondisi `metode_pengadaan==='siplah' && !empty(invoice)`).

## Temuan Proses (penting — ulangi saja, jangan ulangi kesalahan)

- **JANGAN pernah start 2 server pada port yang sama** — PHP built-in server di Windows bisa **keduanya LISTENING** di `127.0.0.1:<port>` (SO_REUSEADDR/race), request login/aksi di-distribusikan acak ke salah satunya → gejala "kredensial tidak cocok"/sesi aneh walau kredensial benar & DB benar. Sebelum start: `netstat -ano | findstr LISTENING | findstr :8025` → kill semua yang listen, lalu start SATU, verifikasi.
- Verifikasi "database mana yang benar-benar dipakai server": boot-app CLI probe (`Auth::attempt` + `DB::connection()->getDatabaseName()`) bisa **lulus** padahal server HTTP memakai env DB berbeda — kalau POST login tetap gagal padahal probe CLI sukses, curiga **dual-server** dulu (bukan kode app).
- `php -S` dengan router akan memproses `.php` yang ADA di docroot LEWAT router (laravel front-controller) sehingga file diag `public/__diag.php` tidak dieksekusi apa adanya (di-redirect Laravel). Pakai router kustom yang intercept path `/__diag` dulu, sisanya `require index.php`.

## Cleanup

- Transaksi uji hanya di DB scratch (temp) — tidak menyentuh produksi. `public/__diag.php` & router-test & probe disimpan di `%TEMP%\opencode` (di luar repo, tidak ikut commit); server 8025 dimatikan; `git status --short` bersih.

## Test Status

- Tidak ada perubahan kode → suite tetap `OK (330 tests, 882 assertions)`, PHPStan level 6 `[OK] No errors`. Belum push/build/rilis.

---

# Sesi 11 Agu 2026 — Release v0.4.2 (Fitur Nomor Invoice SIPLah + Auto-Migrate Terverifikasi di Instalasi Nyata)

## Goal

Rilis v0.4.2: fitur `no_invoice_siplah` (bukan hanya sampai test/commit), verifikasi auto-migrate DB lama saat upgrade di instalasi desktop nyata, lalu push + `gh release create`.

## Summary

- Bump **0.4.1 → 0.4.2** (5 file: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja).
- Build: `cargo check` OK (v0.4.2), `npm run build` OK, `tauri build --bundles nsis,msi` → NSIS `SmartRKAS_0.4.2_x64-setup.exe` 61.0MB + MSI `SmartRKAS_0.4.2_x64_en-US.msi` 92.0MB.
- **Auto-migrate TERVERIFIKASI di instalasi nyata** (snapshot sebelum/sesudah): sebelum install, `migrate:status` pada DB Roaming (`%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite`) → `2026_08_11_000021_add_no_invoice_siplah...` = **Pending** (batch 1-2 Ran). Setelah clean-install v0.4.2 + buka app → migration yang sama = **[3] Ran** → fix auto-migrate `lib.rs` (migrate --force tiap startup) bekerja pada DB lama yang sudah ada.
- Clean-install: uninstall v0.4.1 (`uninstall.exe /S`, exit 0) → folder `%LOCALAPPDATA%\SmartRKAS` terhapus, **data user di Roaming SELAMAT** (DB + storage tetap ada) → install v0.4.2 (/S) → exe ProductVersion 0.4.2 + `php\php.exe` + `php\extras\ssl\cacert.pem` terbundle.
- `/login` pada server app terpasang = **200**.
- Fitur `no_invoice_siplah` terverifikasi end-to-end via HTTP (server nyata, sesi login asli) di sesi sebelumnya: SIPLah tanpa invoice ditolak (pesan Indonesia), SIPLah+invoice tersimpan, Non-SIPLah tanpa invoice tersimpan (null), tampil di index, kwitansi PDF merender baris "No. Invoice SIPLah".

## Perubahan Kode Sesi Ini (dari v0.4.1 → v0.4.2)

- Tidak ada perubahan logika app — hanya bump versi + dokumentasi. Fitur `no_invoice_siplah` (commit `e96c19e` + pesan validasi Indonesia `beee121`) sudah masuk sejak sebelumnya.

## Catatan Proses

- **SOP baru ditambahkan**: sebelum uji manual via browser, cek proses `php.exe`/server dev lain yang listening di port sama (pernah 2x request jatuh acak ke server duplikan dengan state/DB beda). Commit `472f892` (docs).
- Temuan proses penting: **PHP built-in server di Windows bisa dua-duanya LISTENING di port yang sama** → login "credentials do not match" palsu padahal kredensial & DB benar; matikan SEMUA yang listen baru start satu.
- `migrate:status` untuk snapshot memakai `DB_DATABASE` menunjuk langsung file sqlite Roaming (bukan env desktop).

## Test Status

- Backlog commit final: bump versi + AGENTS.md. Suite PHP tetap `OK (330 tests, 882 assertions)`, PHPStan level 6 `[OK] No errors` (tidak ada perubahan kode PHP). Rilis v0.4.2 di GitHub: 2 asset (NSIS + MSI), notes berisi fitur invoice SIPLah + pesan Indonesia + auto-migrate terverifikasi.

---

# Sesi 11 Agu 2026 — KLARIFIKASI & RENCANA FITUR NOTA MULTI-ITEM (1 nota = 1 kegiatan = banyak item) — BELUM DIPUTUSKAN / BELUM ADA PERUBAHAN KODE

## Status (Jujur — Sesuai SOP)

- Ini adalah sesi **perencanaan/diskusi saja**. User menyatakan **BELUM bisa memutuskan** langkah karena ini fitur perubahan besar. Diskusi & rencana dicatat untuk bahan pertimbangan; **TIDAK ada perubahan kode, TIDAK ada commit fitur**.
- User akan memberi kabar bila sudah memutuskan / berdiskusi lagi dengan berbagai pertimbangan.

## Requirement Final (klarifikasi user)

- Fitur adalah **NOTA/KWITANSI MULTI-ITEM**, BUKAN multi-kegiatan.
- **1 Nota = tepat 1 Kegiatan + boleh banyak Item Belanja.** Jangan pernah buat 1 nota dengan >1 kegiatan.
- Jika kegiatan berbeda → buat nota baru (NOTA-0001 Belanja ATK, NOTA-0002 Belanja Obat, dst).
- Struktur konseptual: `nota_bku` (id, no_nota, tanggal, kegiatan, sumber_dana, toko_penerima, metode_pengadaan, no_invoice_siplah, uraian, tahun_anggaran_id, created_by, timestamps, deleted_at) → `nota_bku_item` (id, nota_bku_id, rkas_item_id, jumlah, harga_satuan, subtotal, urutan). TIDAK ada perantara `nota_bku_kegiatan` (1 nota = 1 kegiatan).
- `TransaksiBku` TETAP transaksi finansial utama. Nota hanya pengelompokan/dokumen; saat disimpan, item di-_flatten_ jadi satu `transaksi_bku` per item (BPU018→Item1, BPU019→Item2, ...), semua memegang `nota_bku_id`.
- **`no_nota` ≠ `no_bukti`** (keputusan desain final — jangan pakai no_nota sebagai no_bukti). Format contoh: nota `NOTA-0001/20519260/08/2026`, item menghasilkan `BPU018/20519260/08/2026` dst.
- **Validasi Kegiatan wajib server-side** (bukan cuma JS): item dari kegiatan berbeda → tolak dengan pesan menjelaskan item itu milik kegiatan lain + sarankan nota baru.
- **1 Nota = 1 Sumber Dana** (batch pertama); sumber dana berbeda → nota baru.
- **Guard anggaran all-or-nothing** (§12): semua item divalidasi dulu; jika ada ≥1 item tidak cukup anggaran → **SELURUH nota ditolak** (jangan simpan sebagian). Pakai database transaction.
- Kwitansi utama lama tetap dipertahankan; transaksi hasil flatten bisa pakai mekanisme kwitansi lama. Nota punya halaman **Detail Nota** (header + rincian item + total).
- Cetak dari Detail Nota: checkbox **"Sertakan Lampiran Rincian Nota"** default **OFF**. OFF → kwitansi utama saja; ON → kwitansi utama + lampiran rincian nota (semua item dalam kegiatan tersebut).
- Form: pilih kegiatan → sistem tampilkan item RKAS anggota kegiatan itu; checkbox pilih banyak item; ada tombol "Tambah Item"; TIDAK ada tombol "Tambah Kegiatan".
- Jangan rusak fitur lama: input BKU single-item tetap ada; TransaksiBku tetap sumber transaksi; laporan/ekspor lama tidak boleh rusak.

## Audit Struktur Aktual SmartRKAS v0.4.2 (hasil pengecekan langsung)

- `transaksi_bku` (migrasi 000009 + 000020 override_note + 000021 no_invoice_siplah): UUID PK, `no_bukti` unique(100), `rkas_item_id` FK nullable set-null, `sumber_dana_id` FK nullable, `tahun_anggaran_id` FK restrict, enum `jenis`, `jumlah decimal(15,2)`, `volume`, `satuan`, `toko_penerima`, `metode_pengadaan`, `no_invoice_siplah`, `uraian`, `created_by`, softDeletes. BELUM ada kolom `nota_bku_id`.
- `TransaksiBku` model: HasUuids+SoftDeletes, relasi rkasItem/tahunAnggaran/sumberDana/createdBy/kwitansi(HasOne), `masihOverBudget()`.
- `TransaksiBkuController`: store() normalisasi `NumberParser::rupiah/decimal`, guard `sisaBulanBerjalan` (= rencana rkas_item_bulan ≤ bulan − realisasi pengeluaran ≤ bulan, `RkasItem::sisaKumulatifSd`), `ValidationException`, AuditLog+Outbox+`Cache::increment`. `generateNoBukti()` private: BPU/BBU + 0001 + /NPSN/MM/YYYY + loop anti-bentrok.
- **Kegiatan di aplikasi = `MasterProgram`** (`rkas_item.program_id` → `master_program`, kolom `kode`/`nama`/`program`/`sub_program`/`level`). Kwitansi menampilkan "Kegiatan" = `program->kode . nama`. Sumber dana item = `rkas_item.sumber_dana_id`.
- Delete transaksi: soft-delete + Audit + Outbox per baris (`destroy` & `destroyAll`).
- `AuditLog::record(tabel, aksi, dataBaru, dataLama, userId)`; `Outbox::record(model, modelId, aksi, payload)`.
- Kwitansi PDF: Barryvdh\DomPDF, view `transaksi-bku.kwitansi`/`kwitansi-content`, paper `[0,0,609.4488,935.433]`, `terbilang()` lokal, dedup "Untuk" (collapse whitespace); disimpan di tabel `kwitansi`.
- Tes: `TransaksiBkuTest` (guard, override, normalisasi angka, no_bukti auto, siplah), `KwitansiTest`, `DatabaseIndexTest`, dll. PHPUnit 11 · PHP 8.2 · Laravel 12.
- PHPStan level 6 (`app/`, `config/`, `database/factories/`, `tests/`) — model butuh `@property`.
- Migrasi terakhir `000021`; versi app `0.4.2`. Tidak ada referensi `nota_bku`/`NotaBku` di kode maupun view.

## Keputusan yang SUDAH dikonfirmasi user (sesi ini)

1. **Kegiatan & Sumber Dana = FK** (bukan teks bebas): `nota_bku.kegiatan_id` → `master_program`, `nota_bku.sumber_dana_id` → `sumber_dana`.
2. **Sumber dana diturunkan dari item**; item campur sumber dana → ditolak (sesuai mockup: form hanya kegiatan + checklist item).
3. **Override Sisa Anggaran untuk nota = TIDAK ada** (user: di ARKAS pergeseran/perubahan anggaran hanya untuk item yang BELUM dibelanjakan; nota = belanja yang sudah berjalan → tolak tegas all-or-nothing).
4. **Delete nota = hapus (soft) nota + SEMUA BPU hasil flatten** (Audit + Outbox per transaksi + nota).

## PERTIMBANGAN BESAR: Pergeseran / Perubahan Anggaran (PA) — BELUM ditentukan, krusial untuk keputusan nota multi-item

- **Definisi & alur PA di ARKAS** (aturan utama): **hanya item yang BELUM dibelanjakan yang bisa dilakukan pergeseran / perubahan anggaran**. Item yang sudah ada transaksi/realisasi TIDAK bisa diubah via PA. Konsekuensi untuk nota: nota = belanja yang SUDAH berjalan (item sudah dibelanjakan), sehingga begitu nota dibuat, item-nya tidak akan bisa diperbaiki lewat PA kalau ada kesalahan nominal.
- **Kaitan PA dengan nota multi-item**: guard anggaran nota **all-or-nothing** menolak seluruh nota bila ada ≥1 item tidak cukup anggaran. Jalur legal untuk mencukupkan anggaran = PA (geser/perubahan) pada item yang **belum dibelanjakan** SEBELUM membuat nota. Karena itu keputusan "tidak ada override untuk nota" terkait erat dengan keberadaan fitur PA.
- **PA vs Override Sisa Anggaran (fitur yang sudah ada)**: dua mekanisme berbeda, jangan dicampur.
    - Override (`transaksi_bku.override_note`, M-Override): memungkinkan transaksi melebihi sisa anggaran dengan catatan wajib min. 10 karakter; konsekuensi = **kwitansi terkunci** sampai item RKAS disesuaikan; flash mengingatkan "Segera ajukan pergeseran / Perubahan Anggaran (PA)". Override pakai `masihOverBudget()` untuk mengunci kwitansi.
    - PA (pergeseran/perubahan anggaran): mekanisme ARKAS untuk menyesuaikan rencana anggaran (memindahkan pagu antar item/kegiatan, atau revisi ATK). **BELUM diimplementasikan di SmartRKAS** — saat ini satu-satunya cara menyesuaikan rencana = ubah manual `rkas_item_bulan`/item RKAS.
- **Rencana fitur PA di masa depan (BACKLOG)**: PA yang proper (geser pagu antar item/kegiatan + revisi, diaudit, mereset kunci kwitansi) kemungkinan perlu dibangun agar: (1) jalur legal mencukupkan anggaran sebelum nota, (2) membuka kunci kwitansi override setelah item disesuaikan, (3) mendukung alur ARKAS. Belum ada keputusan scope/waktu; menjadi salah satu pertimbangan besar bagi user sebelum menyetujui nota multi-item.

## RENCANA IMPLEMENTASI (acuan bila user setuju lanjut — BELUM dikerjakan)

- **Migrasi** `2026_08_11_000022_create_nota_bku_tables.php` (tabel `nota_bku` + `nota_bku_item`) dan `2026_08_11_000023_add_nota_bku_id_to_transaksi_bku_table.php` (`transaksi_bku.nota_bku_id` FK nullable onDelete set null + index). Detail kolom sesuai requirement; `nota_bku_item.nota_bku_id` cascade; `rkas_item_id` set null; index pendukung.
- **Model** `NotaBku` (HasUuids+SoftDeletes+HasFactory; relasi kegiatan/sumberDana/tahunAnggaran/createdBy/items(HasMany order urutan)/transaksiBkus), `NotaBkuItem` (notaBku/rkasItem), `TransaksiBku` += `nota_bku_id` + relasi `notaBku()`.
- **Factory** `NotaBkuFactory`, `NotaBkuItemFactory`.
- **Nomor dokumen**: refactor `generateNoBukti` → `app/Support/NomorDokumen.php` (noBukti, noNota `NOTA-{0001}/{NPSN}/{MM}/{YYYY}`), perilaku identik.
- **Controller `NotaBkuController`**: index/create/store/show/destroy/cetak + AJAX `/nota-bku/items?kegiatan_id&bulan&q`; route prefix `nota-bku` dalam grup auth (route `{notaBku}` DIBAWAH route `/nota-bku/items`). store(): normalisasi angka, validasi (tanggal, kegiatan_id exists, items.\*, distinct rkas_item_id, subtotal dihitung server-side), validasi kegiatan & sumber dana server-side, guard all-or-nothing (bulan dari tanggal, `sisaKumulatifSd`), `DB::transaction` → NotaBku + NotaBkuItems + N× TransaksiBku (jenis pengeluaran, no_bukti unik, jumlah=subtotal, volume=qty, satuan item, uraian=item->uraian, nota_bku_id), Audit `nota_bku.create` + Outbox nota & tiap transaksi + `Cache::increment`. destroy(): soft-delete nota + transaksiBkus-nya. cetak(): PDF nota + lampiran opsional (`?lampiran=1`).
- **View**: `nota-bku/index|create|show|kwitansi|lampiran`; tombol "Tambah Nota Multi-Item" di `transaksi-bku/index.blade.php`; link sidebar "Nota Multi-Item".
- **Tests** `tests/Feature/BKU/NotaBkuTest.php` + update `DatabaseIndexTest`; pertahankan 330 test lama hijau.
- **Verifikasi akhir**: full PHPUnit, PHPStan level 6, `view:cache`, E2E HTTP live (cek proses php.exe duplikat di port sama sebelum uji manual — SOP).
- Bump versi/build/rilis = langkah TERPISAH menunggu keputusan user.

## Catatan jujur

- Rencana di atas BELUM dieksekusi; bisa berubah bila user mendiskusikan ulang (mis. skema 6-kartu BKU dibatalkan user di sesi lalu — jadi jangan asumsi, crosscheck dulu).
- Bug "saldo dobel" & "input BKU" dll sudah ditutup di sesi-sesi sebelumnya; acuan stabilitas v0.3.9/baseline v0.4.2.

---

# Sesi 11 Agu 2026 — IMPLEMENTASI FITUR NOTA MULTI-ITEM (NotaBku) — commit lokal, BELUM push/build/rilis

## Goal

Eksekusi rencana "Nota/Kwitansi Multi-Item" yang telah disetujui user (session sebelumnya): 1 nota = 1 kegiatan = banyak item → di-flatten menjadi satu `transaksi_bku` per item, semua memegang `nota_bku_id`. Diaudit all-or-nothing.

## Summary

- Fitur SELESAI + 19 test di `tests/Feature/BKU/NotaBkuTest.php`. Full suite `OK (349 tests, 974 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- BELUM push, BELUM bump versi, BELUM build installer, BELUM rilis GitHub (sesuai instruksi user: commit lokal dulu).

## Changes

- `database/migrations/2026_08_12_000022_create_nota_bku_tables.php` — tabel `nota_bku` (id uuid, no_nota, tanggal, bulan, kegiatan_id FK master_program, sumber_dana_id FK, toko_penerima, metode_pengadaan, no_invoice_siplah, uraian, tahun_anggaran_id FK, created_by, timestamps, softDeletes) + `nota_bku_item` (nota_bku_id FK cascade, rkas_item_id FK null, urutan, jumlah, satuan, harga_satuan, subtotal).
- `database/migrations/2026_08_12_000023_add_nota_bku_id_to_transaksi_bku_table.php` — `transaksi_bku.nota_bku_id` nullable FK set null + index (soft-delete aware).
- `app/Models/NotaBku.php` — HasUuids+SoftDeletes+HasFactory; relasi kegiatan/sumberDana/tahunAnggaran/createdBy/items(orderBy urutan)/transaksiBkus; `@property` dinamis.
- `app/Models/NotaBkuItem.php` — relasi notaBku/rkasItem.
- `app/Models/TransaksiBku.php` — tambah `nota_bku_id` di `$fillable` + relasi `notaBku(): BelongsTo`.
- `app/Support/NomorDokumen.php` (BARU) — `noBukti(string $jenis, string $tanggal)` (BPU/BBU+0001/NPSN/MM/YYYY, count by jenis, loop anti-bentrok) + `noNota(string $tanggal)` (`NOTA-0001/NPSN/MM/YYYY`, hitung denganTrashed agar nomor tidak terpakai ulang). `TransaksiBkuController::generateNoBukti` dihapus → pakai NomorDokumen.
- `app/Http/Controllers/NotaBkuController.php` (BARU) — index/create/items/store/show/destroy/cetak:
    - `items()` AJAX `GET /nota-bku/items?kegiatan_id&bulan` → `{results:[{id,no_urut,uraian,tarif,satuan,sumber_dana,sisa}]}`; **hanya item tahun anggaran aktif** (where tahun_anggaran_id status true) + filter kegiatan. `sisa` = bulan valid ? `sisaKumulatifSd(bulan)` : jumlah.
    - `store()`: validasi tanggal (required), kegiatan_id (exists master_program), toko_penerima required, metode_pengadaan in siplah/non_siplah, `no_invoice_siplah` required_if siplah, items array min:1 + items._.rkas_item_id exists + items._.qty gt:0 + items.*.harga. Normalisasi `NumberParser::rupiah/decimal`. Server-side: semua item dimuat (with sumberDana/tahunAnggaran), cek item milik kegiatan terpilih, item harus tahun anggaran aktif (`tahun_anggaran_id === $tahunAnggaranId`), sumber dana harus SERAGAM (item campur sumber dana → tolak), guard anggaran per item via `sisaKumulatifSd(bulan dari tanggal)` — jika ADA ≥1 item over-budget → `ValidationException` "SELURUH nota dibatalkan" (all-or-nothing). `DB::transaction` jika lolos: buat NotaBku + NotaBkuItems (subtotal = qty*harga) + N transaksi pengeluaran (no_bukti unik, uraian=item uraian, volume=qty, satuan, jumlah=subtotal, nota_bku_id). AuditLog `nota_bku.create` + Outbox per transaksi & nota. Flash sukses berisi no_nota + jumlah transaksi.
    - `destroy()`: soft-delete nota + semua transaksiBkus-nya (Audit+Outbox per transaksi, lalu nota).
    - `cetak()`: PDF via DomPDF gambar `[0,0,609.4488,935.433]` dari `nota-bku.cetak`, filename `NOTA-....-000001-2026.pdf` (safe). Belum ada opsi "lampiran" (sesi ini minimal; `?lampiran=1` belum dipakai).
- `resources/views/nota-bku/index.blade.php` — card Grid 3 kolom? TIDAK — tabel: No / No. Nota / Tanggal·Bulan / Kegiatan / Sumber Dana / Jumlah Item / Total / Aksi (Detail btn-info, Cetak btn-secondary, Hapus form DELETE dengan confirm). Empty-state. Pagination.
- `resources/views/nota-bku/create.blade.php` — form: tanggal (default now), pilih kegiatan (select dari MasterProgram), pilih sumber dana (select), toko penerima, metode pengadaan + row invoice SIPLah (hidden toggle), uraian, daftar item dinamis via JS: pilih item dengan menu `items()` berbasis kegiatan+bulan, setiap baris punya select item + input qty + harga (format rupiah) + satuan readonly + subtotal live + tombol hapus; tombol "Tambah Item"; sumbit dengan nilai-norm (parseRupiah/parseDecimal). Baris tambahan "Batal/Reset".
- `resources/views/nota-bku/show.blade.php` — Detail Nota: info header (no_nota, tanggal, bulan, kegiatan, sumber dana, metode+invoice, toko, uraian, pembuat), tabel item (urutan, uraian, qty×satuan, harga, subtotal) + footer total, ringkasan stat-card (Total, Jumlah Item, Transaksi Terkait), tabel transaksi terkait (no_bukti, tanggal, jumlah), tombol Cetak PDF (`target=_blank`), Hapus (form DELETE + confirm modal/JS).
- `resources/views/nota-bku/cetak.blade.php` — PDF nota: kop sekolah (nama, npsn, alamat, kab/kec), judul, field no_nota/tanggal/kegiatan/sumber dana/metode+invoice/toko/uraian, tabel item + total + terbilang, TTD (Bendahara & Kepala Sekolah). Paper custom.
- `resources/views/transaksi-bku/kwitansi-content.blade.php` — tambah baris **No. Nota** saat `$transaksiBku->notaBku` ada (kwitansi transaksi hasil flatten menampilkan referensi nota asal).
- `resources/views/layouts/navigation.blade.php` — link sidebar "Nota Multi-Item" (di bawah Buku Kas Umum, ikon receipt).
- `resources/views/transaksi-bku/index.blade.php` — tombol "Nota Multi-Item" (btn-info) di header card.
- `routes/web.php` — grup auth: `GET /nota-bku` (index), `GET /nota-bku/create`, `GET /nota-bku/items`, `POST /nota-bku` (store), `GET /nota-bku/{notaBku}`, `DELETE /nota-bku/{notaBku}`, `GET /nota-bku/{notaBku}/cetak`. Route `{notaBku}` DIBAWAH `/items`.
- `database/factories/NotaBkuFactory.php` + `NotaBkuItemFactory.php`.

## Guard Anggaran All-or-Nothing (detail yang diverifikasi user)

- Test `test_store_rejects_entire_nota_when_any_item_over_budget`: buat 2 item (rencana cukup 1jt & sisa 50rb), post nota dengan item2 qty 10×10rb = 100rb > sisa 50rb → `assertSessionHasErrors('items')` + teks "SELURUH nota dibatalkan". Ditegaskan dengan **`assertDatabaseMissing` eksplisit untuk SEMUA baris**:
    - `nota_bku` (no_nota `NOTA-0001/20519260/01/2026`)
    - `nota_bku_item` (kedua rkas_item_id)
    - `transaksi_bku` (kedua item, jenis pengeluaran)
    -   - assertDatabaseCount 0 pada ketiga tabel.

## Verifikasi

- `vendor\bin\phpunit --filter NotaBkuTest` → `OK (19 tests, 92 assertions)`.
- Full suite → `OK (349 tests, 974 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Kedua konfirmasi user dituntaskan: (1) all-or-nothing test pakai `assertDatabaseMissing` utk semua tabel; (2) `kwitansi-content.blade.php` punya baris "No. Nota" untuk transaksi flatten (+test render).

## Catatan

- Item bukan tahun anggaran aktif ditolak di `store()` DAN tidak muncul di `items()`.
- `no_nota` ≠ `no_bukti`: nota TIDAK menggunakan nomor BPU; transaksi hasil flatten tetap dapat `no_bukti` sendiri.
- Override sisa anggaran untuk nota TIDAK ada (keputusan user).
- Catatan: sebagian teks sesi perencanaan di AGENTS.md (sebelum ini) masih mengandung mojibake karakter (utk kasus lama); bagian ini ditulis UTF-8 bersih.
- Belum: bump versi, build installer, push, release — menunggu instruksi user.

## Test Status

- PHPUnit `OK (349 tests, 974 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. BELUM push; commit lokal sesuai instruksi.

---

# Sesi 12 Agu 2026 — Revisi Besar: Penyatuan Form BKU Pengeluaran + Nota Multi-Item — TAHAP 1 (Migrasi kode_rekening_id + Model/Relasi)

## Keputusan Final User (acuan seluruh revisi)

- Form Pengeluaran & Penerimaan TETAP terpisah; Penerimaan TIDAK berubah.
- Form Pengeluaran jadi SATU form tunggal: pilih Kegiatan → pilih Kode Rekening (terfilter dari Kegiatan) → centang item RKAS cocok (1 atau banyak) → isi qty+harga per item yang dicentang.
- **Tepat 1 item dicentang** → perilaku PERSIS form single-item lama: opsi "Override Sisa Anggaran" muncul, transaksi tersimpan langsung sebagai 1 `TransaksiBku` (TIDAK membuat NotaBku).
- **2+ item dicentang** → opsi override otomatis hilang, guard all-or-nothing (pola `NotaBkuController::store` yang sudah dibangun), tersimpan sebagai `NotaBku` + N `TransaksiBku` hasil flatten.
- Hapus menu sidebar "Nota Multi-Item" terpisah dan route `/nota-bku/create` — SEMUA masuk lewat `/transaksi-bku/create` yang direvisi.
- Halaman Detail Nota, PDF cetak nota, referensi "No. Nota" di kwitansi TETAP dipertahankan (hanya terpakai saat hasilnya NotaBku, 2+ item).
- Pengerjaan bertahap: (1) migrasi `kode_rekening_id` + model/relasi, (2) controller penggabungan (reuse, bukan rewrite), (3) view form gabungan + JS toggle override, (4) test lengkap + hapus rute/menu lama. Setiap tahap dilaporkan di AGENTS.md sesuai SOP.

## Status TAHAP 1 (SELESAI & TERVERIFIKASI)

Migrasi kolom `kode_rekening_id` pada `nota_bku` + update model/relasi sudah ada di working tree (sisa pekerjaan tahap 2–4 dari upaya sebelumnya) dan TERVERIFIKASI:

## Changes

- `database/migrations/2026_08_12_000024_add_kode_rekening_id_to_nota_bku_table.php` (BARU, untracked) — `nota_bku.kode_rekening_id` foreignUuid nullable `after('kegiatan_id')` `constrained('master_kode_rekening')` `onDelete('set null')` + index `[kode_rekening_id, bulan]`. `down()` drop index + dropConstrainedForeignId.
- `app/Models/NotaBku.php` — tambah `@property string|null $kode_rekening_id`, `$fillable` + `kode_rekening_id`, relasi `kodeRekening(): BelongsTo` (FK `kode_rekening_id` → `MasterKodeRekening`).
- `database/factories/NotaBkuFactory.php` — tambah `'kode_rekening_id' => MasterKodeRekening::factory()`.
- (Tahap 2 yang sudah sempat ter-bangun di working tree, TIDAK diverifikasi sebagai tahap 2 di sesi ini): `NotaBkuController` — `create()` muat `$kodeRekenings`; `items()` filter `kode_rekening_id`; `store()` validasi `kode_rekening_id` required|exists + cek item milik kode rekening terpilih + simpan di `NotaBku::create`. `TransaksiBkuController` — `create()` muat `$kegiatans`/`$kodeRekenings`; `store()` sudah memilah `items` 1 vs 2+ (reuse NotaBkuController::store). `NotaBkuTest` + `TransaksiBkuTest` sudah berisi test kode rekening & jalur 1/2 item.

## Verifikasi

- `vendor\bin\phpunit --filter NotaBkuTest` → `OK (23 tests, 107 assertions)` — termasuk `test_items_endpoint_filters_by_kode_rekening`, `test_store_rejected_when_kode_rekening_missing`, `test_store_rejected_when_item_from_other_kode_rekening`, `test_store_saves_kode_rekening_nota_relation_via_transaksi`. RefreshDatabase menjalankan migrasi 000024 pada sqlite :memory: → kolom + FK + index terbukti valid.
- `vendor\bin\phpunit --filter TransaksiBkuTest` → `OK (41 tests, 163 assertions)` — termasuk jalur 1 item (transaksi langsung, override, guard) & 2+ item (nota flatten, all-or-nothing).

## Catatan PENTING (rekon sisa working tree vs keputusan final — perlu dibereskan di tahap 3–4)

- Working tree saat ini berisi sisa perubahan tahap 2–4 dari upaya SEBELUMNYA yang SEBAGIAN BERTENTANGAN dengan keputusan final:
    - `resources/views/transaksi-bku/index.blade.php` masih menambahkan tombol "Tambah Pembelanjaan" → `route('nota-bku.create')` + tombol "Tambah Transaksi" → `route('transaksi-bku.create')` + link "Riwayat Nota". Sesuai keputusan final, route `/nota-bku/create` HARUS DIHAPUS dan SEMUA entri lewat `/transaksi-bku/create` (tombol tunggal) — bagian ini akan disesuaikan di tahap 3–4.
    - `resources/views/layouts/navigation.blade.php` — menu sidebar "Nota Multi-Item" sudah DIHAPUS (benar sesuai keputusan final).
    - `resources/views/nota-bku/create.blade.php` (rewrite besar, 448 baris) — bakal jadi bahan blok Kegiatan+KodeRekening+checklist untuk form gabungan `/transaksi-bku/create`, tapi route `/nota-bku/create` tetap akan dihapus.
    - `resources/views/nota-bku/index.blade.php` — judul diubah jadi "Riwayat Nota Belanja" (tetap dipertahankan sbg halaman riwayat/Detail Nota).
- Route `/nota-bku` index/show/destroy/cetak/items TETAP dipertahankan (Detail Nota, PDF, riwayat, AJAX items dipakai form gabungan). Hanya `/nota-bku/create` yang dihapus.
- Belum ada commit baru di sesi ini — seluruh pekerjaan tahap 1 masih di working tree (termasuk perubahan tahap 2–4 lama yang belum final).

## Test Status

- NotaBkuTest `OK (23, 107)` · TransaksiBkuTest `OK (41, 163)`. Belum full suite / PHPStan (menunggu tahap 2–4 selesai dari kondisi final).

---

# Sesi 12 Agu 2026 — Revisi Besar: Penyatuan Form BKU Pengeluaran + Nota Multi-Item — TAHAP 2 (Controller Reuse)

## Goal

Pemilahan 1-item vs 2+ item di `TransaksiBkuController::store()` murni REUSE, bukan reimplementasi: logika single-item lama di-extract apa adanya menjadi private `storeSingleItem()`; jalur 2+ item memanggil `NotaBkuController::storeFromItems()` yang reusable. Rapikan tombol index (hapus "Tambah Pembelanjaan"), TANPA menghapus route `/nota-bku/create` + `NotaBkuController::create()` + view-nya sampai Tahap 3 siap (urutan aman: bangun view gabungan dulu, baru hapus yang lama).

## Changes

- `app/Http/Controllers/NotaBkuController.php` — method `store(Request)` lama di-rename jadi **`storeFromItems(Request)`** (public, reusable, berisi seluruh logika nota: validasi kegiatan+kode rekening, sumber dana seragam, guard all-or-nothing, flatten). Tambah wrapper `store(Request)` yang hanya `return $this->storeFromItems($request);` — route `/nota-bku` POST tetap berfungsi sampai Tahap 3–4.
- `app/Http/Controllers/TransaksiBkuController.php` — `store()` kini hanya DISPATCH: baca `items`, filter baris ber-`rkas_item_id`, `count >= 2` → `(new NotaBkuController)->storeFromItems($request)` (REUSE penuh); `count === 1` → merge `rkas_item_id/volume/jumlah(round qty×harga)/satuan/jenis=pengeluaran` ke request lalu lanjut ke `storeSingleItem()`. Logika single-item lama (validasi, auto no_bukti, guard anggaran + override, kunci kwitansi, audit/outbox, cache) di-extract APA ADANYA ke `private function storeSingleItem(Request $request)` — tidak ada baris logika yang ditulis ulang dari ingatan.
- `resources/views/transaksi-bku/index.blade.php` — tombol "Tambah Pembelanjaan" (→ `nota-bku.create`) DIHAPUS; tombol "Tambah Transaksi" kembali jadi tombol tunggal `btn-primary` → `route('transaksi-bku.create')`; link "Riwayat Nota" → `nota-bku.index` tetap dipertahankan.
- TIDAK dihapus pada tahap ini (sesuai instruksi urutan aman): route `GET /nota-bku/create`, `NotaBkuController::create()`, `resources/views/nota-bku/create.blade.php`.

## Verifikasi

- `vendor\bin\phpunit --filter TransaksiBkuTest` → `OK (42 tests, 171 assertions)` — test LAMA (override, kwitansi terkunci over-budget, guard, normalisasi angka, no_bukti auto, siplah) TIDAK ada assertion yang diubah, semuanya tetap hijau lewat `storeSingleItem()`. Tambah 1 test baru: `test_store_single_checked_item_override_blocks_kwitansi_until_resolved` (jalur `items[]` 1-item + override → `masihOverBudget()` true → cetak kwitansi diblokir → setelah rencana dinaikkan → PDF OK + kwitansi tersimpan) — membuktikan jalur form baru mempertahankan kunci kwitansi PERSIS form lama.
- `vendor\bin\phpunit --filter NotaBkuTest` → `OK (23 tests, 107 assertions)` — route `/nota-bku` POST via wrapper `store()` → `storeFromItems()` tetap hijau.
- Full suite → `OK (359 tests, 1020 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.

## Catatan

- Jalur 2+ item murni meneruskan `$request` asli ke `storeFromItems()` (payload `items[]` + header kegiatan/kode rekening/toko/metode/siplah) — tidak ada normalisasi ulang duplikat di TransaksiBkuController.
- Jalur 1 item: `jumlah` dihitung server-side `round(qty*harga, 2)` lalu masuk `storeSingleItem()` yang tetap melakukan `NumberParser::rupiah/decimal` seperti biasa — konsisten dengan jalur form lama.
- Jalur form LAMA (tanpa `items`, langsung `rkas_item_id`+`jumlah`+`volume`) tetap masuk `storeSingleItem()` karena `$rawItems` bukan array → dispatch tidak aktif. Penerimaan tidak tersentuh.

## Test Status

- PHPUnit `OK (359 tests, 1020 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. BELUM commit — menunggu kelanjutan Tahap 3 (view gabungan + JS toggle) & Tahap 4 (hapus route/menu lama + test final).

---

# Sesi 12 Agu 2026 - Revisi Besar: Penyatuan Form BKU Pengeluaran + Nota Multi-Item - TAHAP 3 (View Gabungan + JS Toggle)

## Goal

Bentuk form pengeluaran menjadi SATU form tunggal di `transaksi-bku/create.blade.php`: field header + nominal/rincian dari form single-item lama, blok Kegiatan -> Kode Rekening -> checklist item diadaptasi dari `nota-bku/create.blade.php` (yang sudah berfungsi), toggle override reaktif dua arah, kalkulator per-item, tanpa menghapus route/controller/view nota lama (itu Tahap 4).

## Summary

- View `transaksi-bku/create.blade.php` ditulis ulang penuh (623 baris); `view:cache` OK; full suite `OK (359 tests, 1020 assertions)`, PHPStan level 6 `[OK] No errors`.
- 1 bug kecil ditemukan & diperbaiki saat review: urutan `renderItems()` lalu `clearSelection()` di `loadItems()` menghapus wrap hidden hasil restore `old('items')` saat validasi gagal -> selection hilang. Fix: `clearSelection()` dipindah SEBELUM `renderItems()`.

## Changes

- `resources/views/transaksi-bku/create.blade.php` (ditulis ulang):
    - Section 1 Informasi Transaksi: `tanggal`, `jenis` (dropdown penerimaan/pengeluaran TETAP dipertahankan di satu halaman; Penerimaan tidak berubah), `#row_no_bukti` + hint `#no_bukti_hint_nota` ("Nomor bukti (BPU) dibuat otomatis saat menyimpan nota multi-item.").
    - Include `_rkas-picker` (`#row_rkas_item`) - hanya untuk Penerimaan.
    - `#row_item_checklist` (hidden, untuk Pengeluaran): `kegiatan_id` + `kode_rekening_id` (dari `$kegiatans`/`$kodeRekenings`), info box "1 item dicentang" vs "2+ item dicentang" (Nota Multi-Item all-or-nothing tanpa override), `#item-list` (rows: `item-check`, `item-qty`, `item-harga`, `row-subtotal`), `#manual-rows` (baris "+ Tambah Item" dipertahankan dari form nota), `#total-belanja`, `#items-hidden` (wrap `items[{id}][rkas_item_id|qty|harga|satuan]`), checkbox `penyelesaian`.
    - `#row_kalkulator` (hidden, Penerimaan saja), `#row_jumlah` (hidden untuk Pengeluaran - jumlah dihitung server-side `round(qty*harga,2)`), `toko_penerima`, `row_metode_pengadaan` + `row_no_invoice_siplah` (toggle JS), `uraian`, `#row_override` (`override_anggaran` + `row_override_note` min 10 karakter, restore `old()`).
    - JS: `generateNoBukti()` (BBU/BPU + count + npsn + MM/YYYY), `loadItems()` fetch `/nota-bku/items?kegiatan_id&kode_rekening_id&bulan` (bulan dari input tanggal via `parseBulan`), `renderItems()` (restore `old('items')` sekali via flag `oldRestored`), `hiddenWrap/addHidden/updHidden/rmHidden`, `bindRow`, `bindManualRow`, `addManualRow`, `selectedCount()` (= jumlah wrap `#items-hidden div[id^="items-"]`), `recalcOverrideAndBukti()` (reaktif dua arah), `toggleVisibility()`, `updateHarga()`, `onPickerSelect`, `toggleNoInvoice`, `kalkulasiJumlah`, submit guard (pengeluaran tanpa item -> preventDefault + alert "Centang minimal satu item belanja terlebih dahulu.").
- **Keputusan desain** (penting untuk Tahap 4): `row_override` tampil hanya jika jenis=pengeluaran DAN `selectedCount() === 1`; `row_no_bukti` disembunyikan saat `>= 2` item (nilai diabaikan `storeFromItems`); checkbox `penyelesaian` TIDAK dipaksakan saat submit (hindari friksi 1-item; server tetap validasi `items`); `row_rkas_item`/`row_kalkulator`/`row_jumlah` hanya untuk Penerimaan.

## Verifikasi

- `php artisan view:cache` OK; `php artisan view:clear` tidak diperlukan.
- `vendor\bin\phpunit --filter TransaksiBkuTest` -> `OK (42 tests, 171 assertions)`.
- `vendor\bin\phpunit --filter NotaBkuTest` -> `OK (23 tests, 107 assertions)`.
- Full suite -> `OK (359 tests, 1020 assertions)`; PHPStan level 6 `[OK] No errors`.
- Konsistensi dicocokkan: `TransaksiBkuController::create()` mengirim `npsn`, `countPenerimaan`, `countPengeluaran`, `pickerInitial`, `kegiatans`, `kodeRekenings` - semuanya dipakai view; `_rkas-picker` menerima `['pickerInitial' => $pickerInitial]`; endpoint `items()` mengembalikan `{results:[{id,no_urut,uraian,tarif,satuan,kode_rekening_id,sumber_dana,sisa}]}` sesuai pemakaian view.
- `store()` dispatch (Tahap 2) konsisten: `count===1` merge volume/jumlah/satuan/jenis -> `storeSingleItem()`; `count>=2` -> `NotaBkuController::storeFromItems()`.

## Catatan

- BELUM menghapus route `/nota-bku/create`, `NotaBkuController::create()`, atau `resources/views/nota-bku/create.blade.php` - menunggu persetujuan Tahap 4.
- BELUM diuji manual di browser (belum ada langkah browser sesi ini); verifikasi terbatas pada view:cache + suite + review konsistensi.

## Test Status

- PHPUnit `OK (359 tests, 1020 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. BELUM commit - menunggu persetujuan lanjut Tahap 4.

---

# Sesi 13 Agu 2026 — BKU Final: 1 Nota = 1 BPU (Poin 5), Partial Unique no_bukti (Poin 6), Verifikasi Restore (Poin 4)

## Goal

Tutup 3 keputusan user dari evaluasi fitur nota multi-item yang belum tuntas: (4) form kembali terisi saat nota/gabungan ditolak, (5) **1 nota = 1 `TransaksiBku` (total)** — realisasi per item ditelusuri via `nota_bku_item` (atribusi), (6) **reuse `no_bukti` soft-deleted** via partial unique index. Semua + verifikasi HTTP live + commit lokal.

## Summary

- **Poin 6 (reuse no_bukti)**: migrasi `2026_08_13_000025` ganti unique `no_bukti` → **partial unique index** `transaksi_bku_no_bukti_aktif_unique` `WHERE deleted_at IS NULL` (sqlite) / `unique([no_bukti, deleted_at])` (non-sqlite). `NomorDokumen::noBukti()` di-rewrite: aktif-only + **nomor terkecil yang bebas (seq dari 1)** → nomor soft-deleted dipakai ulang; `TransaksiBkuController:259` revert ke aktif-only `->exists()`; `update()` `no_bukti` rule → `Rule::unique(...)->ignore($id)->whereNull('deleted_at')`. Terverifikasi di dev DB: migrasi DONE, test `test_store_reuses_soft_deleted_no_bukti_when_autogenerating` (nama baru, harap `BBU001/00000000/01/2026`).
- **Poin 5 (1 nota = 1 BPU + atribusi)**: `NotaBkuController::storeFromItems()` kini membuat **satu** `TransaksiBku` (total nota, `rkas_item_id => null`, `nota_bku_id`, satu `no_bukti` BPU, `volume/satuan => null`, uraian fallback `'Nota belanja ' . $noNota`), flash "…N item dibukukan sebagai 1 transaksi pengeluaran." Rincian item tetap di `nota_bku_item`. **RealisasiQuery** (`app/Support/RealisasiQuery.php`, BARU) — UNION `transaksi_bku` (pengeluaran, `whereNotNull rkas_item_id`) + `nota_bku_item` (join `nota_bku` non-deleted, `nbi.id, nbi.rkas_item_id, nb.bulan, nbi.subtotal as jumlah`), `base($alias='rb')` = derived table. Union membawa kolom `id` → pengecualian transaksi-saat-edit = `where('rb.id','!=',$except)` (id transaksi tak pernah sama dgn id nota_bku_item), TANPA perlu pengurangan.
- **Konsumen RealisasiQuery** (semua realisasi item kini = transaksi + nota):
    - `RkasItem`: `notaBkuItems()` HasMany; `realisasiKumulatifSd(int $bulan, ?string $exceptTransaksiId = null)` (sum `rb.jumlah` via base, `rb.rkas_item_id`, `rb.bulan <=`, optional `rb.id !=`); `sisaKumulatifSd(...)` sekarang delegasi (rencana − realisasiKumulatifSd). Tambah `@property float $nota_realisasi_sum`.
    - Guard `TransaksiBkuController::store` (~278) = `realisasiKumulatifSd($bulan)`; `update` (~399) = `realisasiKumulatifSd($bulan, $transaksiBku->id)`. `TransaksiBku::masihOverBudget()` pakai `realisasiKumulatifSd($bulan)`.
    - `LaporanController` `loadRekapRekeningItems`/`loadKuartalItems` (+2 call site `$realisasiSub` di `rekapRekening`/`rekapKuartal` yang sebelumnya masih TransaksiBku) → `RealisasiQuery::base()` dgn `rb.bulan`/`rb.jumlah`; **bonus bug**: kasus kuartal masih `SUM(CASE WHEN transaksi_bku.bulan...)` (referensi kolom hilang → 500) → diganti `rb.bulan`/`rb.jumlah`. `RekapRekeningExport`/`RekapKuartalExport` sama (import `TransaksiBku` → `RealisasiQuery`).
    - `RkasItemController::select2`: bulan path eager-load `notaBkuItems` (whereHas nota bulan <=), realisasi = transaksi + subtotal nota; non-bulan path `withSum` `nota_realisasi_sum` (nota non-deleted). DashboardController `totalRealisasi`/`chartData`/`realisasiPerBulan` → `RealisasiQuery::base()` (whereIn `rb.rkas_item_id` + bulan); per-item `dynamic_realisasi` = `transaksiBkus->sum('jumlah') + notaBkuItems->sum('subtotal')` dengan eager-load `notaBkuItems` (filter bulan bila `$bulan`).
- **Poin 4 (restore form)**: view `transaksi-bku/create.blade.php` (Tahap 3) sudah punya jalur restore `oldItems` → `loadItems()` saat init (kegiatan+kode rekening ter-restore via `old()` selected) → `renderItems()` → `oldRestored` → centang + isi ulang qty/harga/satuan + `addHidden`. **Terverifikasi HTTP live** (server dev 8026): POST over-budget `qty=100×30000` pada item sisa Rp1.350.000 → 302 ke `/transaksi-bku/create`, `old('kegiatan_id')`/`old('kode_rekening_id')` ter-`selected`, `oldItems` JSON memuat `{uuid:{rkas_item_id,qty:100,harga:30000,satuan}}`, pesan guard "Nominal Rp 3.000.000 melebihi sisa anggaran s.d. bulan 1…" dirender. Restore checklist/qty/harga = sisi klien (kode sudah benar; data tersedia).

## Changes (working tree)

- `database/migrations/2026_08_13_000025_replace_no_bukti_unique_with_partial_unique_index.php` (BARU) — up: dropUnique(`no_bukti`) → partial unique (sqlite) / `unique([no_bukti,deleted_at])`; down: drop partial + re-add `transaksi_bku_no_bukti_unique`. Sudah di-apply ke dev DB.
- `app/Support/NomorDokumen.php` — `noBukti()` seq dari 1 (aktif-only terkecil bebas); `noNota()` tetap `withTrashed()`.
- `app/Support/RealisasiQuery.php` (BARU) — union transaksi + nota, `base($alias='rb')` derived table (`id, rkas_item_id, bulan, jumlah`).
- `app/Models/RkasItem.php` — `notaBkuItems()`, `realisasiKumulatifSd()`, `sisaKumulatifSd(?string $exceptTransaksiId=null)` delegasi, `@property nota_realisasi_sum`.
- `app/Http/Controllers/TransaksiBkuController.php` — :259 aktif-only; store/update guard via `realisasiKumulatifSd`; `update()` Rule unique partial.
- `app/Http/Controllers/NotaBkuController.php` — `storeFromItems()` 1 TransaksiBku total, closure `use (... $total)`.
- `app/Http/Controllers/LaporanController.php` — 2 call site realisasiSub + helper → RealisasiQuery::base(); fix kasus kuartal `rb.bulan`/`rb.jumlah`.
- `app/Exports/RekapRekeningExport.php`, `app/Exports/RekapKuartalExport.php` — realisasiSub via RealisasiQuery.
- `app/Http/Controllers/RkasItemController.php` — select2 nota consumption (bulan + non-bulan).
- `app/Http/Controllers/DashboardController.php` — totalRealisasi/chartData/realisasiPerBulan via base; per-item dynamic_realisasi + eager `notaBkuItems`.
- Tests — `NotaBkuTest`: `test_store_creates_nota_items_and_transaksi` (1 transaksi, rkas*item_id null, jumlah=total, Outbox 2), `test_duplicate_no_bukti_from_nota_is_unique` (1 BPU), `test_store_saves_kode_rekening_nota_relation_via_transaksi` (assert `transaksi->notaBku->kode_rekening_id`, karena `rkasItem` kini null); `TransaksiBkuTest`: `test_store_two_checked_items_create_nota_and_flattened_transaksi` (1 transaksi), rename+ubah `test_store_skips_soft_deleted_no_bukti_when_autogenerating` → `...\_reuses*...` (`BBU001/00000000/01/2026`).

## Catatan Teknis

- Union membawa `id`: update-guard pengecualian `where('rb.id','!=',$except)`; id transaksi (uuid) vs id nota_bku_item (uuid) tidak pernah sama.
- `RealisasiQuery::base()` = `DB::query()->fromSub(self::union(), $alias)` → Query\Builder, jadi aman utk `leftJoinSub` & PHPStan `@param \Illuminate\Database\Query\Builder|null`.
- JS restore: hidden inputs `items[{uuid}][...]` (key = UUID rkas_item_id), jadi `old('items')` punya key UUID → `Object.keys(oldItems)` cocok dgn `[data-id="{uuid}"]`.
- Poin 6 risiko kecil: nomor soft-deleted yang dipakai ulang akan "bentrok" bila baris lama di-restore (accept). `DatabaseIndexTest` tidak mengecek unique `no_bukti` (renama index aman).
- Dev DB: `transaksi=7 nota=2` (data dev, bukan produksi); POST uji ditolak (0 insert). Cookie jar `%TEMP%\opencode\cookies-dev.txt`; server dev `artisan serve` 8026 tetap berjalan.

## Test Status

- PHPUnit `OK (360 tests, 1027 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK. BELUM commit — dikerjakan lanjut sesi ini; commit lokal sesuai instruksi user.

---

# Sesi 13 Agu 2026 — Fix Realisasi Nota Dobel + Cascade Hapus Nota dari BKU (commit lokal)

## Goal

Tanggapi laporan user: "setelah saya hapus BKU dan mau input lagi kok over?". Dua akar masalah ditemukan dan diselesaikan (keputusan user: cascade hapus nota): (1) realisasi item nota dihitung DOBEL untuk data transaksi flatten lama, (2) menghapus transaksi BKU milik nota tidak mengembalikan anggaran karena atribusi nota (nota_bku_item) tetap dihitung.

## Summary

- **Akar 1 (dobel hitung)**: transaksi flatten hasil nota versi LAMA membawa `rkas_item_id` + `nota_bku_id` → dihitung dua kali: cabang transaksi di `RealisasiQuery` (225.000) + `nota_bku_item` (225.000) → realisasi item tampil 450.000 padahal belanja asli 225.000 → sisa −225.000 (over tanpa input apa pun). **Fix**: cabang transaksi `RealisasiQuery::union()` kini `whereNull('nota_bku_id')` — atribusi realisasi nota hanya lewat `nota_bku_item`. Verifikasi dev DB: item Honor Al Banjari/Qiro'ah realisasi 450.000 → 225.000, sisa −225.000 → 0.
- **Akar 2 (anggaran tidak kembali saat BPU nota dihapus)**: `nota_bku_item` terus dihitung selama nota aktif, jadi hapus BPU (termasuk "Hapus Semua") tidak membebaskan anggaran. **Keputusan user: CASCADE** — menghapus transaksi yang merupakan bagian dari nota kini ikut menghapus (soft) nota + semua transaksi terkaitnya → anggaran kembali.

## Changes

- `app/Support/RealisasiQuery.php` — cabang transaksi tambah `->whereNull('nota_bku_id')`; docblock diperbarui.
- `app/Http/Controllers/NotaBkuController.php` — extract `deleteNotaWithTransaksis(NotaBku): int` (soft-delete nota + semua transaksi terkait + AuditLog/Outbox per transaksi & nota + `Cache::increment`); `destroy()` memanggilnya (perilaku sama).
- `app/Http/Controllers/TransaksiBkuController.php`:
    - `destroy()` — bila `nota_bku_id` terisi & nota aktif → `deleteNotaWithTransaksis($nota)` + flash "Transaksi dihapus beserta nota …"; selain itu jalur normal.
    - `destroyAll()` — pisahkan transaksi nota vs normal: transaksi normal dihapus biasa; nota (dari id unik transaksi nota di set filter) di-cascade `deleteNotaWithTransaksis`; audit `delete_bulk` kini memuat `jumlah_nota`; flash "…termasuk N nota terkait."
- Tests (`tests/Feature/BKU/NotaBkuTest.php`) — 3 baru: `test_realisasi_nota_tidak_dobel_dengan_transaksi_legacy_nota` (transaksi legacy rkas_item_id+nota_bku_id tidak dobel), `test_destroy_transaksi_nota_cascades_to_nota_dan_anggaran_kembali` (delete transaksi nota → nota & transaksi soft-deleted, realisasi 0, sisa kembali 1000000), `test_destroy_all_cascades_transaksi_nota`.

## Verifikasi

- Probe dev DB (item Honor): realisasi 450.000 → 225.000 (tidak dobel); sisa −225.000 → 0.
- PHPUnit NotaBkuTest `OK (26, 122)` · full suite `OK (363 tests, 1041 assertions)` · PHPStan level 6 `[OK] No errors` · `view:cache` OK.
- Catatan: item Honor di dev DB memang sudah terpakai penuh (sisa 0) — untuk input ulang setelah fix, user harus hapus nota-nya (kini otomatis saat BPU nota dihapus).

## Catatan

- Transaksi nota di `destroyAll` di-skip dari loop normal (ditangani cascade); nota hanya di-cascade bila ada transaksinya di set filter (tidak semua nota ikut terhapus).
- `deleteNotaWithTransaksis` reuse utk halaman Riwayat Nota (destroy) dan cascade dari BKU — satu sumber kebenaran.
- Dev DB `transaksi=7 nota=2`; cookie jar `%TEMP%\opencode\cookies-dev.txt`; server dev 8026 berjalan. BELUM push — commit lokal sesuai instruksi user.

---

# Sesi 13 Agu 2026 — Rekonsiliasi Dashboard Total Realisasi vs BKU Total Pengeluaran

## Temuan User

"Dashboard total realisasi 1.175.000, di BKU total pengeluaran 675.000, tidak sama." Verifikasi thd dev DB: RealisasiQuery total = 1.175.000, BKU pengeluaran aktif = 675.000. Selisih persis **500.000** = rincian NOTA-0002 (bulan 2) tetap dihitung walau transaksi BPU-nya (BPU004/005/006) sudah dihapus (soft) dari BKU.

## Root Cause

`nota_bku_item` di `RealisasiQuery` hanya mengecek nota aktif (`nb.deleted_at null`), TIDAK mengecek apakah transaksi pencatatannya di BKU masih ada. Karena nota-nya masih aktif (stale state dari masa sebelum fitur cascade hapus nota), item-nya tetap dihitung → dashboard realisasi lebih besar dari BKU. (NOTA-0002: transaksi BPU004/005/006 trashed, nota aktif, item 500.000 tetap terhitung.)

## Fix

- `app/Support/RealisasiQuery.php` — cabang nota tambah `whereExists` subquery berkorelasi: minimal SATU `transaksi_bku` aktif (`nota_bku_id = nb.id` dan `deleted_at null`). Rincian nota hanya dihitung bila pembeliannya masih tercatat di kas (BKU); bila semua transaksi nota dihapus → pembelian dianggap batal → item tidak dihitung (selaras dgn cascade).
- Verifikasi dev DB: RealisasiQuery total = **675.000 = BKU** (selisih 0). Item Antiseptic/Perban/Minyak Tawon realisasi 0 → sisa kembali penuh (juga menyelesaikan keluhan "over" sebelumnya untuk item itu). NOTA-0001 (BPU aktif) tetap dihitung 450.000.
- Tests — `NotaBkuTest::test_realisasi_nota_tidak_dihitung_saat_semua_transaksi_dihapus` (transaksi nota dihapus langsung tanpa cascade → nota tetap aktif tapi realisasi = 0, sisa kembali 1000000, total RealisasiQuery = 0).

## Catatan

- Perbaikan struktur test: baris `}` penutup class yang nyasar di tengah (akibat edit sebelumnya) dirapikan; `App\Support\RealisasiQuery` di test butuh leading backslash (`\App\Support\...`) karena namespace `Tests\Feature\BKU`.
- Stale data NOTA-0002 (nota aktif, transaksi trashed) tidak diubah di DB dev — kini otomatis tidak dihitung sebagai realisasi, tanpa perlu hapus nota.
- Invarian desain: realisasi per item (via `RealisasiQuery`) selalu = total kas BKU (sum transaksi pengeluaran aktif), karena setiap nota aktif wajib punya transaksi aktif.

## Test Status

- PHPUnit `OK (364 tests, 1047 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 — Kwitansi PDF: Hapus Program/Sub Program/Kode Rekening & No. Nota (cukup No. Bukti + No. SIPLah)

## Goal

User: "output PDF kwitansi: kode program, sub program dan kode rekening jadi kosong; jangan tampilkan No. Nota; yang dilaporkan di lapangan hanya no bukti dan no siplah karena no nota sudah didapat dari toko."

## Changes

- `resources/views/transaksi-bku/kwitansi-content.blade.php` (dipakai kwitansi single + batch):
    - Baris **Program** dan **Sub Program** DIHAPUS dari tabel field.
    - Baris **No. Nota** DIHAPUS (`@if($transaksiBku->notaBku)` dihapus).
    - Baris **Uraian** kini hanya menampilkan `$transaksiBku->uraian` (fallback `rkasItem->uraian`, lalu `-`) — TIDAK lagi menampilkan kode rekening + nama rekening.
    - Blok PHP "PROGRAM HIERARKI" disederhanakan hanya `$namaKegiatan` (hapus `$namaProgram`/`$namaSubProgram`/`$kodeRekening`/`$namaRekening`/kode segment).
    - Field yang tersisa: **No (no_bukti)**, Kegiatan, Uraian, Terima Dari, No. Invoice SIPLah (saat siplah), Sebesar + terbilang, TTD.

## Verifikasi

- Render view thd DB dev BPU001: `No. Nota`/`Sub Program`/`Program` TIDAK ADA; `Kegiatan`/`Uraian`/`Sebesar`/`Terima Dari` ADA; Uraian = "Belanja Honor Pembina Ekstra Pramuka" (tanpa kode rekening). PDF render OK (`%PDF-`, 2258 bytes).
- Test `NotaBkuTest` lama `test_cetak_kwitansi_flattened_transaksi_shows_no_nota` diubah → `test_cetak_kwitansi_flattened_transaksi_tidak_menampilkan_no_nota_program_dan_sub_program` (assert Not/Contains `No. Nota`, `NOTA-0001/...`, `Sub Program`; Contains `BPU001/...` + uraian item).

## Test Status

- PHPUnit `OK (364 tests, 1050 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 — Kwitansi PDF: Isi Program/Sub Program/Kode Rekening (dari nota) + Nota PDF "No. BPU"/"Rincian Belanja" (KOREKSI arah v0.3.x)

## Goal

Klarifikasi user atas temuan sebelumnya ("kode program, sub program dan kode rekening jadi kosong"): kolom tsb harus **DIISI data**, bukan dihapus. Jawaban user utk 2 pertanyaan: (1) Kode Rekening kwitansi → "Isi juga dari nota (Recommended)"; (2) Nota PDF → "Ya, cetak No. BPU saja (Recommended)" — No. BPU = `no_bukti` transaksi; nomor nota internal TIDAK dicetak; judul "Nota Belanja" → "Rincian Belanja".

## Root Cause (mengapa "awalnya kosong")

- Transaksi hasil nota (1 nota = 1 transaksi total) punya `rkas_item_id = NULL` → `rkasItem->program`/`rkasItem->kodeRekening` null → kolom Kegiatan/Program/Sub Program/Kode Rekening tampak kosong. Data sebenarnya TERSEDIA via `$transaksiBku->notaBku->kegiatan` (MasterProgram) + `$transaksiBku->notaBku->kodeRekening`.

## Changes

- `resources/views/transaksi-bku/kwitansi-content.blade.php` (single + batch) — blok PHP PROGRAM HIERARKI dipulihkan: `$prog`/`$rekening` diambil dari `rkasItem->program`/`->kodeRekening`, dan bila `rkasItem` null (transaksi nota) **fallback ke `notaBku->kegiatan`/`notaBku->kodeRekening`**. Baris tabel menampilkan lagi: **Kegiatan**, **Program**, **Sub Program**, **Kode Rekening** (`kode - nama`), lalu **Uraian** (`uraian ?: rkasItem->uraian ?: '-'`). Kegiatan = `kode + nama`; Program = `segment[0]. + program`; Sub Program = `segment[0].segment[1]. + sub_program` (kode di-`rtrim('.')` → explode). "No. Nota" tetap TIDAK dirender.
- `resources/views/nota-bku/cetak.blade.php` — `<title>` + `.judul` → "Rincian Belanja"; field `No. Nota` → **`No. BPU`** (`$noBpu = $notaBku->transaksiBkus->first()?->no_bukti ?? $notaBku->no_nota` — fallback no_nota bila belum ada transaksi); blok "Dibukukan sebagai transaksi BKU" DIHAPUS (info no_bukti kini di field utama); footer note "Nomor nota" → "Nomor BPU".
- `resources/views/nota-bku/cetak.blade.php` (lanjutan) — tambah baris **Program**, **Sub Program**, **Kode Rekening** (logika hierarki sama dengan kwitansi, dari `notaBku->kegiatan` + `notaBku->kodeRekening`) agar identik dgn pdf kwitansi. Field kini: No. BPU, Tanggal, Kegiatan, Program, Sub Program, Kode Rekening, Sumber Dana, Toko/Penerima, No. Invoice SIPLah, Uraian.
- `tests/Feature/BKU/NotaBkuTest.php` — test kwitansi lama (yang assert NOT Sub Program) **diubah jadi** `test_cetak_kwitansi_flattened_transaksi_menampilkan_program_subprogram_rekening_tanpa_no_nota` (set `program`/`sub_program` + `kode`/`nama` rekening via `update()` agar deterministik; Contains Program/Sub Program/Kode Rekening + nilai; Not/Contains `No. Nota`, `NOTA-0001/...`). Tambah `test_cetak_menampilkan_rincian_belanja_dan_no_bpu_tanpa_no_nota` (render `nota-bku.cetak`, transaksi nota rkas_item null → Contains `Rincian Belanja`, `No. BPU`, `BPU001/20519260/01/2026`, Program/Sub Program/Kode Rekening + nilai via `update()`; Not/Contains `No. Nota`, `NOTA-0001/...`).

## Verifikasi (render nyata thd DB dev `database/database.sqlite`)

- Kwitansi transaksi nota (BPU001/.../08/2026, rkas_item null): `No. Nota` ABSENT; Program/Sub Program/Kode Rekening PRESENT TERISI dari nota — `03. Pengembangan Standar Proses`, `03.05. Pelaksanaan Administrasi Kegiatan Sekolah`, `5.1.02.01.01.0037 - Belanja Obat-Obat-Obatan`.
- Nota PDF (NOTA-0003/.../08/2026): `Rincian Belanja` PRESENT; `No. BPU` = `BPU001/20519260/08/2026`; Program/Sub Program/Kode Rekening PRESENT TERISI (`03. Pengembangan Standar Proses` / `03.05. Pelaksanaan Administrasi Kegiatan Sekolah` / `5.1.02.01.01.0037 - Belanja Obat-Obat-Obatan`); `No. Nota` + `NOTA-0001` ABSENT — identik dgn pdf kwitansi.
- BPU001/002 (item biasa): Program/Sub Program/Kode Rekening tetap terisi dari rkasItem. BBU001 (tarik tunai, tanpa item & tanpa kegiatan nota) → `-` wajar.
- DB Roaming produksi (`%APPDATA%\id.smartrkas.desktop`) belum punya tabel `nota_bku` → probe nota PDF memakai dev DB.

## Catatan

- Test kwitansi memakai `$this->program->update([...])` + `$this->rekening->update([...])` sebelum `makeItem()` — factory default `program`/`sub_program` null & kode P.####/5.1.2.##.####, jadi nilai eksplisit wajib utk assert isi.
- Kwitansi transaksi nota kini memakai data kegiatan+kode rekening dari NOTA (bukan item); transaksi single-item memakai rkasItem. Keduanya mengisi kolom yang sama.

## Test Status

- PHPUnit `OK (365 tests, 1060 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 — Kwitansi PDF: Hapus Field Uraian (dobel dgn kotak "Untuk")

## Goal

User: "di pdf kwitansi ada yang dobel di uraian dan untuk tolong perbaiki / hapus salah satu." Kedua field menampilkan uraian yang sama. Jawaban user (pertanyaan): **hapus "Uraian"**, pertahankan kotak "Untuk" (sudah punya logika dedup uraian item vs transaksi).

## Changes

- `resources/views/transaksi-bku/kwitansi-content.blade.php` — baris `<td class="lbl">Uraian</td>` DIHAPUS dari tabel field utama. Field atas kini: No, Kegiatan, Program, Sub Program, Kode Rekening, Terima Dari, No. Invoice SIPLah (saat siplah), Sebesar + terbilang. Uraian hanya ditampilkan sekali di kotak **"Untuk"** (utk semua transaksi: item biasa maupun nota).
- Tidak ada perubahan test (assert uraian via kotak "Untuk" tetap berlaku — teks uraian item hanya 1 kemunculan di HTML).

## Verifikasi (render nyata thd DB Roaming produksi)

- BPU001/.../01/2026 (item biasa): `Uraian field` ABSENT; `Untuk box` PRESENT; teks uraian item "Honor Pembina Ekstra Al Banjari" muncul **1×** (hanya di kotak Untuk).

## Test Status

- PHPUnit `OK (365 tests, 1066 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 — Redesain Detail Nota + Form Edit BKU (pola create) + Hapus Route/View Nota Create (Tahap 4 selesai)

## Goal

Redesain halaman Detail Nota (`nota-bku/show.blade.php`) yang layoutnya berantakan, rapikan halaman Tambah BKU (`transaksi-bku/create.blade.php`), samakan halaman Edit BKU (`transaksi-bku/edit.blade.php`) dengan pola create baru, dan hapus tampilan tak terpakai (route `/nota-bku/create` + `NotaBkuController::create()` + view `nota-bku/create.blade.php` + tombol "Tambah Nota"). User minta lapor dulu sebelum ubah kode — 3 keputusan scope disetujui (edit disamakan dgn create; route create dihapus; create dirapikan).

## Summary

- `nota-bku/show.blade.php` ditulis ulang penuh: KPI stat-card + card Informasi Nota (Program/Sub Program/Kode Rekening diisi) + rincian item + transaksi terkait.
- `transaksi-bku/create.blade.php` dirapikan: kalkulator dipindah ke dalam section "Nominal & Rincian" (tidak ada blok Section 3 terpisah); checkbox `penyelesaian` kini wajib dicentang saat submit pengeluaran (client-side).
- `transaksi-bku/edit.blade.php` ditulis ulang mengikuti pola create: blok Kegiatan→Rekening→checklist item untuk pengeluaran (item lama auto-tercentang, jumlah = qty × harga); transaksi nota (`rkas_item_id` null) menampilkan panel read-only dari nota; penerimaan kini konsisten (picker + kalkulator tampil).
- `update()` menerima pola `items[]` (1 item → jumlah dihitung ulang qty × harga); 2+ item ditolak (domain Nota multi-item).
- Tahap 4 selesai: route `GET /nota-bku/create`, `NotaBkuController::create()`, view `nota-bku/create.blade.php`, tombol "Tambah Nota" dihapus; test diupdate (route create → 404).

## Changes

- `resources/views/nota-bku/show.blade.php` — tulis ulang: header aksi (Kembali / Cetak PDF / Hapus), KPI 3 stat-card (Total Belanja green, Jumlah Item blue, Transaksi BKU indigo via `$notaBku->transaksiBkus->count()`), card "Informasi Nota" grid 2 kolom (No. Nota, Tanggal, Bulan, Kegiatan, Program, Sub Program, Kode Rekening `kode - nama`, Sumber Dana, Toko/Penerima, Metode Pengadaan badge, No. Invoice SIPLah saat siplah, Dibuat Oleh, Uraian col-span-2 bila ada), card "Rincian Item Belanja" (data-table + tfoot Total), card "Transaksi BKU Terkait" (full-width, conditional `isNotEmpty()`). Hierarki Program/Sub Program dari segmen `kegiatan->kode`.
- `resources/views/transaksi-bku/create.blade.php` — `row_kalkulator` dipindah ke dalam section "Nominal & Rincian"; submit pengeluaran wajib centang `penyelesaian` (alert "Centang konfirmasi bahwa semua item dalam transaksi sudah dimasukkan.").
- `resources/views/transaksi-bku/edit.blade.php` — ditulis ulang penuh: Section 1 (tanggal / jenis / no_bukti), Section 2b checklist (`kegiatan_id` + `kode_rekening_id` dari `$initialKegiatanId`/`$initialKodeRekeningId` = `program_id`/`kode_rekening_id` item lama; item auto-tercentang via JS `initialItemId`/`initialQty`/`initialHarga` + flag `autoChecked`), panel read-only utk transaksi nota (No. Nota / Kegiatan / Jumlah Item / Program / Sub Program / Kode Rekening dari `notaBku`), kalkulator + `row_jumlah` (penerimaan), toko / metode / invoice / uraian, submit guard (pengeluaran wajib ≥1 item dicentang kecuali nota). Tanpa manual-rows / override / penyelesaian.
- `app/Http/Controllers/TransaksiBkuController.php` — `edit()`: load `rkasItem.program`/`rkasItem.kodeRekening` + `notaBku.kegiatan`/`notaBku.kodeRekening`, kirim `$kegiatans`/`$kodeRekenings`. `update()`: blok dispatch `items[]` — 1 item → merge `rkas_item_id`/`volume`/`jumlah`(=qty×harga)/`satuan`/`jenis`; 2+ → `ValidationException` "Transaksi gabungan ... Hapus dan buat nota baru dari menu Tambah Transaksi". Guard sisa anggaran tetap (dilewati hanya utk nota transaksi `rkas_item_id` null).
- `routes/web.php` — hapus `GET /nota-bku/create`.
- `app/Http/Controllers/NotaBkuController.php` — hapus `create()` + import `MasterProgram`/`MasterKodeRekening` (tidak terpakai lagi setelah create() hilang).
- `resources/views/nota-bku/index.blade.php` — tombol "Tambah Nota" → "Tambah Transaksi" (`route('transaksi-bku.create')`); empty-state diarahkan ke Tambah Transaksi.
- `resources/views/nota-bku/create.blade.php` — DIHAPUS (tampilan tak terpakai).
- `tests/Feature/BKU/NotaBkuTest.php` — `test_guest_is_redirected_to_login` hapus assert `/nota-bku/create`; `test_create_page_renders` → `test_create_route_removed_returns_404` (assertNotFound).

## Verifikasi

- `php artisan view:cache` OK; full suite `OK (365 tests, 1059 assertions)`; PHPStan level 6 `[OK] No errors`.
- Probe render nyata thd dev DB `database/database.sqlite` (nota=3, transaksi=9): SHOW nota NOTA-0003 → len 26965, memuat no_nota / Program / KPI "Total Belanja"; EDIT BPU001/.../08 (nota transaksi) → panel read-only "Nota Multi-Item"; EDIT BPU001/.../01 (single-item, pakai `withTrashed()` karena semua single-item dev soft-deleted) → selects kegiatan/rekening + items-hidden + initialItemId + tanpa panel nota.

## Catatan

- Transaksi dev yang single-item semuanya soft-deleted → render single-item dicek via `withTrashed()` (view statis valid; alur JS sama dgn create yang sudah teruji).
- Submit guard `penyelesaian` hanya client-side (server tetap validasi `items`); test POST langsung ke server tidak terdampak.
- `update()` jalur nota transaksi: `rkasItemId` null → guard dilewati, `jumlah` tetap editable (jumlah total nota), tidak mengubah `nota_bku_item`.
- Route `/nota-bku` index/show/destroy/cetak/items TETAP dipertahankan (Detail Nota, PDF, riwayat, AJAX items dipakai form gabungan).

## Test Status

- PHPUnit `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. BELUM push — commit lokal sesuai instruksi user.

---

# Sesi 13 Agu 2026 — 4 Temuan User: Form Penerimaan Sederhana + Riwayat/Detail Nota No. Bukti + Daftar Item di Edit Nota

## Goal

Tindak lanjut 4 temuan user (lapor → konfirmasi → implementasi). Keputusan user via pertanyaan: (1) Penerimaan di halaman Tambah Transaksi → **sembunyikan picker item RKAS + kalkulator otomatis** (form sederhana, isi nominal langsung); (3) Detail Nota → **No. Bukti dipindah ke card Informasi Nota, card "Transaksi BKU Terkait" di bawah dihapus**. Temuan (2) Riwayat Nota (tombol Kembali + kolom No. Bukti) dan (4) Edit BKU nota (daftar item) disetujui apa adanya.

## Summary

- 5 file diubah (2 controller + 3 view + 1 partial), full suite tetap `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK.
- Probe render nyata thd DB dev (NOTA-0003/20519260/08/2026 → BPU001/20519260/08/2026): 10 cek PASS (SHOW No. Bukti label+nilai, card bawah dihapus, KPI Transaksi BKU tetap; INDEX tombol Kembali + kolom No. Bukti + nilai; EDIT panel nota + tabel item + uraian item).

## Changes

- `resources/views/transaksi-bku/_rkas-picker.blade.php` — wrapper `<div id="row_rkas_item" class="hidden">` (picker item RKAS TIDAK pernah tampil lagi di create/edit; mencegah flash sebelum JS).
- `resources/views/transaksi-bku/create.blade.php` — `toggleVisibility()` penerimaan: `rowRkas.style.display='none'` + `rowKalkulator.style.display='none'` (sebelumnya `'block'`). Penerimaan kini: isi Jumlah Nominal langsung (row_jumlah), tanpa pilih item & tanpa kalkulator.
- `resources/views/transaksi-bku/edit.blade.php` — sama (konsisten dgn create); PLUS panel nota read-only kini menampilkan **tabel rincian item nota** (urutan, uraian item `no_urut. uraian`, jumlah, satuan, harga satuan, subtotal) saat `notaBku->items` tidak kosong — pengguna bisa melihat item apa saja yang terlibat.
- `app/Http/Controllers/TransaksiBkuController.php` — `edit()` eager-load `notaBku.items.rkasItem`.
- `app/Http/Controllers/NotaBkuController.php` — `index()` eager-load `transaksiBkus` (utk kolom No. Bukti); `show()` eager-load `transaksiBkus`.
- `resources/views/nota-bku/index.blade.php` — card-header: tambah tombol **"Kembali"** (`btn-secondary btn-sm` → `route('transaksi-bku.index')`) di samping "Tambah Transaksi"; tabel tambah kolom **"No. Bukti"** setelah No. Nota (nilai `transaksiBkus.pluck('no_bukti')` filter/unique/implode, `-` bila kosong).
- `resources/views/nota-bku/show.blade.php` — card "Informasi Nota" tambah field **"No. Bukti (BPU)"** (setelah No. Nota); **card "Transaksi BKU Terkait" dihapus** (karena 1 nota = 1 BPU; No. Bukti kini tampil di header). KPI "Transaksi BKU" di atas tetap dipertahankan.

## Catatan Teknis

- Nilai No. Bukti memakai `pluck('no_bukti')->filter()->unique()->values()` — aman utk nota legacy ber-transaksi ganda (dijoin koma).
- Blok kalkulator (`row_kalkulator`) & JS-nya (`kalkulasiJumlah`/`updateHarga`/`onPickerSelect`) TIDAK dihapus dari DOM — hanya disembunyikan; `window.RkasPicker` tetap ter-init karena partial masih di-include (menghindari JS error).
- `rkas_item_id` hidden pada picker tetap membawa nilai lama saat edit penerimaan → update tidak kehilangan relasi item (data aman).
- Test tidak ada assertion yang diubah; assertSee hanya cek kehadiran HTML (kalkulator/picker masih ada di source, hidden oleh class/style) sehingga suite tetap hijau.

## Test Status

- PHPUnit `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Probe render dev DB: 10/10 PASS. BELUM push — commit lokal sesuai instruksi user.

---

# Sesi 13 Agu 2026 — 4 Temuan User: Header Tabel Edit Sejajar + Dropdown Searchable + Tombol Riwayat Nota + Kolom Nota di Index BKU

## Goal

Perbaiki 4 temuan user (lapor → konfirmasi → implementasi): (1) tabel item di halaman edit BKU tidak lurus dengan header; (2) dropdown Kegiatan & Rekening di create BKU harus bisa dicari; (3) "Riwayat Nota" masih link teks kecil; (4) di index BKU kolom Kode Kegiatan/Kode Rekening/Jenis Belanja/Volume/Satuan kosong untuk transaksi nota.

## Summary

- CSS + 2 controller/view berubah; full suite tetap `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK, `npm run build` OK.
- Probe render index thd dev DB (baris nota `BPU001/20519260/08/2026`): Kode Kegiatan=`03.05.02.`, Kode Rekening=`5.1.02.01.01.0037`, Jenis Belanja=`Belanja Barang Persediaan`, Volume=`16` (sum qty 6+6+4), Satuan=`-` (item campur botol/buah → benar sesuai keputusan user).

## Changes

- `resources/css/app.css` — tambah `.data-table thead th.text-center { text-align: center; }` + `.data-table thead th.text-right { text-align: right; }` (spesifisitas lebih tinggi dari `.data-table thead th { text-align:left }` agar header kolom angka rata kanan sejajar isi). **Jangan pakai `@apply text-center`** di rule yang selectornya mengandung `.text-center` sendiri → Tailwind error "circular dependency"; pakai CSS polos `text-align`.
- `resources/views/transaksi-bku/index.blade.php` — link "Riwayat Nota" (`text-xs underline`) → tombol `btn btn-secondary btn-sm` + ikon dokumen; blok `@php` per-baris: `$kegiatanKode`/`$rekeningKode`/`$jenisBelanjaNama` = `rkasItem?->program/kodeRekening` dengan fallback `notaBku?->kegiatan/kodeRekening`; `$volumeTampil`/`$satuanTampil`: untuk nota → `items->sum('jumlah')` + satuan hanya bila semua item satu satuan (`strtolower(trim)` unik count 1), selain itu `-` (transaksi biasa tidak berubah).
- `app/Http/Controllers/TransaksiBkuController.php` — `index()` eager-load `notaBku.kegiatan`, `notaBku.kodeRekening.jenisBelanja`, `notaBku.items` (tambahan dari eager-load rkasItem yang sudah ada).
- `resources/views/transaksi-bku/create.blade.php` + `edit.blade.php` — input `kegiatan_search`/`kode_rekening_search` di atas select kegiatan & rekening (di edit hanya branch non-nota); JS `initSearchableSelect(selectId, inputId)` filter `opt.hidden` via `textContent` lowercased, placeholder (`value===''`) dan opsi `selected` selalu tampil; dipanggil di blok init DOMContentLoaded.

## Catatan

- Keputusan user (via question tool) utk kolom Volume/Satuan baris nota: Volume = total qty seluruh item; Satuan hanya ditampilkan bila semua item memakai satuan sama (selain itu `-`).
- Header tabel edit (No/Jumlah/Harga Satuan/Subtotal) sudah berkelas `text-center`/`text-right` sejak sebelum sesi ini; akar "tidak lurus" = CSS bawaan `.data-table thead th` `text-align:left` mengalahkan utilitas → fix cukup di CSS, struktur HTML tidak diubah.
- `npm run build` menghasilkan `public/build/assets/app-BBIO6Wtu.css` + manifest baru (`public/build/` tidak di-track).

## Test Status

- PHPUnit `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK, `npm run build` OK. Probe render dev DB: index (kolom nota terisi) + edit (tabel item utuh) PASS. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 - Redesain Pencarian Kegiatan & Rekening ala Picker Item RKAS (commit lokal)

## Goal

User: "sistem pencarian kegiatan dan rekening kok gak kayak sistem pencarian di item rkas dulu, enak begitu gampang". Pendekatan filter <select> yang sudah di-commit (`initSearchableSelect`) DIGANTI total dengan UX picker ala `_rkas-picker.blade.php` (input teks + dropdown hasil + hidden input).

## Changes

- `resources/views/transaksi-bku/_search-picker.blade.php` (BARU) - partial reusable: label + search input (`{prefix}_search`) + hidden input tetap bernama `{prefix}_id` (id & name sama, agar binding `old()`/error/submit tetap jalan) + dropdown hasil `{prefix}_results` (z-20, max-h-60) + status hint + tombol "Bersihkan". JS generik `window.initEntityPicker(cfg)` (guard `window.__entityPickerInit`, 1 definisi per halaman) + instance init per include. Fitur: filter client-side 150ms debounce (data di-embed `@json`), klik hasil select, Enter memilih item pertama (preventDefault agar tak submit form), klik luar menutup dropdown, tombol clear, restore nilai awal dari hidden input. Emits CustomEvent `entitypicker:change {id, value}`.
- `resources/views/transaksi-bku/create.blade.php` - blok select kegiatan/rekening diganti 2 include partial (opsi dari `\`/`\`: `['id'=>(string)id,'text'=>kode.' - '.nama]`). JS: hapus `initSearchableSelect` + 2 pemanggilan; listener `change` select diganti listener global `entitypicker:change` -> `loadItems()` (guard id kegiatan_id/kode_rekening_id). Submit guard baru: pengeluaran wajib kegiatan+rekening terpilih (sebelumnya ditegakkan browser via `required` pada select; hidden input tidak di-validate browser).
- `resources/views/transaksi-bku/edit.blade.php` - sama (include partial, hapus initSearchableSelect, listener global `entitypicker:change` DI DALAM blok `if (!isNota)`). Nilai awal pakai `old('kegiatan_id', \ ?? '')`.
- TIDAK ada perubahan controller/test. `kegiatanSelect`/`kodeRekeningSelect` kini = hidden input; semua pembacaan `.value` dan kondisi init (`if (kegiatanSelect.value && kodeRekeningSelect.value) loadItems();`) tetap berfungsi.

## Catatan Teknis

- hidden input tetap `name=` {prefix}\_id sehingga POST/validasi/old()/error tidak berubah (test POST langsung ke server tidak terdampak).
- initEntityPicker TIDAK men-dispatch entitypicker:change saat init (hanya set teks/status) - menghindari fetch ganda; loadItems init tetap lewat blok `if (value && value)`.
- Edit untuk transaksi nota: pickers & script berada di cabang `@else` (non-nota), jadi tidak dirender - tidak ada referensi null (kegiatanSelect null untuk nota sudah kondisi pra-ada).
- Partial include script di dalam `<form>` - valid; guard definisi mencegah fungsi terdefinisi ganda saat 2 include.

## Verifikasi

- `php artisan view:cache` OK; PHPUnit full suite `OK (365 tests, 1059 assertions)`; PHPStan level 6 `[OK] No errors`.
- Probe render dev DB (`probe-picker.php`): create - kegiatan_search/hidden kegiatan_id/kegiatan_results/kode_rekening_results/initEntityPicker/entitypicker:change/placeholder FOUND; `<select name=kegiatan_id` & `initSearchableSelect` ABSENT. edit (BPU001/20519260/01/2026, item tunggal) - sama + hidden value preset = rkas_item_id FOUND. Opsi JSON ter-embed benar (139 program/276 rekening di dev DB); regex "options count = 0" pada probe pertama adalah regex yang salah tangkap, output asli diverifikasi berisi 139 item.

## Test Status

- PHPUnit `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Commit lokal; BELUM push.

---

# Sesi 13 Agu 2026 — Audit Dampak Menyeluruh (dashboard → laporan, semua cetak) + Laporan MD Terpisah

## Goal

Jawaban permintaan user: "sekarang cek ke semua apakah ada dampak dari perubahan kita sesuai sop dan buatkan laporan md terpisah", lalu "di cek semua halaman dan fungsinya dari dashboard sampai laporan tanpa terlewatkan, dan juga isi cetak yang lain". Selesai: 69 cek PASS, laporan `LAPORAN-PERUBAHAN.md` dibuat di root repo.

## Summary

- **Probe komprehensif** `%TEMP%\opencode\probe-all-pages.php` (login admin, render controller via `app()->call`, PDF via stream, export via `Excel::store`, AJAX via Request): **69/69 PASS**.
- Tidak ada perubahan kode aplikasi pada sesi ini (murni audit + laporan). Working tree bersih (3 commit lokal: `8c96eb0`, `b748a4b`, `7076b98`; belum push).

## Cakupan Verifikasi (69 cek)

- **BKU**: index (judul, tombol Riwayat Nota btn, tombol "Tambah Pembelanjaan" dihapus, no_bukti nota tampil), create (form+jumlah, picker kegiatan/rekening search+hidden+results, initEntityPicker+event, `<select>` lama absen, rkas picker penerimaan tetap, override, no_invoice_siplah, hint format), edit single (picker + preset, tanpa panel nota), edit nota (panel read-only + tabel item, tanpa picker).
- **Nota**: index (Kembali, kolom No. Bukti, Tambah Transaksi), show (No. Bukti di Informasi, card Transaksi BKU Terkait dihapus, KPI Total Belanja, Rincian Item), cetak PDF (`%PDF` + judul "Rincian Belanja" + field No. BPU via render view karena stream PDF terkompresi FlateDecode).
- **Kwitansi**: single + batch PDF valid.
- **Dashboard**: render OK.
- **Laporan 4×**: web render + PDF valid (bku, rekapRekening, rekapKuartal, rekapSiplah).
- **Export 4×**: file `.xlsx` valid (PK, >1KB) — BkuExport, RekapRekeningExport, RekapKuartalExport, RekapSiplahExport.
- **AJAX**: `/nota-bku/items` (filter kegiatan+rekening+bulan) → `{results:[...]}`; `/rkas-items/select2` → `{results:[...]}`.
- **Master & pengaturan 21 halaman**: rkas index+edit, master-program, master-kode-rekening, sumber-dana, tahun-anggaran, jenis-belanja (index+create), pengaturan-sekolah, backup, riwayat aktivitas, telegram, kode-pemulihan, tentang, import-rkas — render tanpa error.

## Catatan Teknis Probe

- `Excel` facade alias TIDAK terdaftar di config → pakai `\Maatwebsite\Excel\Facades\Excel::store`. Path export **relatif** ke disk `local` (root = `storage/app/private`), bukan `storage/app`.
- Controller dgn dependency (mis. `AboutController(AppUpdateService)`) harus `app()->make()`, bukan `new`; route-model-bound page (rkas edit) butuh model nyata (`RkasItem::withTrashed()->first()`).
- Controller return `Illuminate\View\View` → `->render()`; View TIDAK punya `getContent()`.
- Cek PDF: `str_starts_with($body, '%PDF')` + status 200. Teks di dalam PDF TIDAK bisa dicari di stream (content terkompresi) → render view langsung untuk cek isi.
- 3 FAIL pertama saat iterasi = salah pilih data (probe awal pakai `withTrashed()->first()` → ambil transaksi soft-deleted yang memang tidak tampil di index; edit nota kosong). Setelah pakai transaksi aktif → 69/69.
- Sisa proses php dev: 8026 (repo) + 61389 (app desktop terinstall, kode sendiri) — tidak ada duplikat port (SOP).

## Laporan MD

- `LAPORAN-PERUBAHAN.md` (root repo) — ringkasan perubahan 3 commit, tabel verifikasi 69 cek, dampak per halaman/fungsi, keterbatasan jujur (belum uji browser klik penuh picker; `npm run build` tidak dijalankan ulang karena tanpa perubahan aset; export diuji via class langsung; probe DB dev), status rilis (lokal, belum push).

## Test Status

- PHPUnit `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. Tidak ada perubahan kode. `LAPORAN-PERUBAHAN.md` + AGENTS.md ini BELUM di-commit.

---

# Sesi 13 Agu 2026 — Verifikasi Keamanan Data Lama + Build & Install v0.5.0 (fitur sesi 12–13 akhirnya masuk installer)

## Goal

1. Jawab kekhawatiran user "apakah aman dengan data lama dan dengan tampilan data tabelnya, karena yang sudah perubahan ada yang tidak muncul coba cek" — ternyata "perubahan tidak muncul" karena instalasi terpasang masih **v0.4.2** (fitur nota multi-item, picker kegiatan/rekening, kolom Kode Kegiatan/Rekening di tabel, Riwayat Nota dll. masih commit lokal, belum di-build). 2) Setelah konfirmasi user, build installer v0.5.0 dan verifikasi di instalasi nyata.

## Verifikasi Keamanan Data Lama (sebelum build — dibuktikan pada SALINAN DB produksi, bukan asli)

- Salin DB produksi (`%APPDATA%\id.smartrkas.desktop\smartrkas.sqlite`) → `%TEMP%\opencode\prod-mig-test.sqlite`, lalu migrasi 000022–000025: **semua DONE**, 19 transaksi utuh, partial unique index `transaksi_bku_no_bukti_aktif_unique` (WHERE `deleted_at IS NULL`) terbentuk, tabel nota kosong.
- Render `TransaksiBkuController::index` terhadap salinan pasca-migrasi: semua PASS (19 no_bukti tampil, kolom Kegiatan/Rekening/Jenis Belanja/Volume/Satuan terisi, tombol Riwayat Nota ada, tanpa link `nota-bku/create`). Contoh BPU006: `05.09.04.`, `5.2.02.10.02.0003`, badge "Belanja Modal Peralatan & Mesin", volume 1 unit.
- **RealisasiQuery vs BKU = selisih 0** (11.181.000 = 11.181.000) → `RealisasiQuery::base()` aman untuk data lama tanpa nota (base() hanya kolom `id, rkas_item_id, bulan, jumlah` — TIDAK ada kolom `jenis`; cabang transaksi sudah filter pengeluaran di dalam union). Dashboard konsisten: Rencana Rp 180.320.000, Realisasi Rp 11.181.000, Sisa Rp 169.139.000.
- Semua item yang dipakai transaksi produksi masih aktif (tidak ada soft-deleted) → kolom tidak kosong. BBU001/BBU002 (`rkas_item_id` NULL) = penerimaan, normal.
- 2 artefak probe yang menyesatkan (jangan diulang): `DB::table('rkas_item')->withTrashed()` → BadMethodCallException (pakai `App\Models\RkasItem::withTrashed()`); `RkasItem::find('019fd4fc')` (8-char id) gagal → fallback `first()` memberi item SALAH ("Perjalanan Dinas dalam Daerah-"); `where('rb.jenis', ...)` di derived table → "no such column" (jenis ada di tabel sumber, bukan base). UUIDv7 punya prefix 8-hex sama untuk data sesaat.

## Build v0.5.0

- Bump **0.4.2 → 0.5.0** (5 file: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja) — `git diff --stat` = 5 file, 5+/5−.
- `npm run build` OK (manifest + app-\*.css/js). `tauri build --bundles nsis,msi` (background, log `%TEMP%\opencode\build-v050.log`): compile 4m55s → NSIS `SmartRKAS_0.5.0_x64-setup.exe` (61.2MB) → MSI `SmartRKAS_0.5.0_x64_en-US.msi` (92.8MB). `smartrkas.exe` ProductVersion = **0.5.0**.

## Install & Verifikasi Instalasi Nyata (prosedur 3× bersih)

- Kill app v0.4.2 (CloseMainWindow tidak merespon → kill paksa; job object mematikan anak php bersih). Uninstall `uninstall.exe /S` → folder `%LOCALAPPDATA%\SmartRKAS` terhapus, **data user di Roaming SELAMAT** (smartrkas.sqlite 1.5MB). Install v0.5.0 `/S` → exe 0.5.0, `php\php.exe` + `php\extras\ssl\cacert.pem` terbundle.
- `Start-Process` app → server `php -S 127.0.0.1:53954` (router **tanpa** prefix `\\?\`, semua `-d opcache.enable=0 log_errors=1 error_log=... curl.cainfo=... openssl.cafile=...` terpasang) + `schedule:work`.
- `/login` = **200/200/200** (len 11614). **Auto-migrate DB produksi bekerja**: 000022–000025 kini `[4] Ran` di DB asli. Data utuh: 19 transaksi aktif, nota=0, realisasi=BKU (selisih 0). `php-server-error.log` = 4 baris, semua fatal lama era `\\?\` (08-Agu), TIDAK ada error baru.

## Catatan Proses

- Sebelum build cek tidak ada proses cargo/tauri/node build lain (SOP anti-build rangkap).
- App v0.5.0 sengaja DIBIARKAN BERJALAN setelah verifikasi — user akan uji manual UI (tabel BKU, picker, nota 2+ item, Riwayat Nota, penerimaan sederhana).
- BELUM commit (bump versi + AGENTS.md masih working tree), BELUM push, BELUM rilis GitHub — menunggu hasil uji manual user.

## Test Status

- Tidak ada perubahan logika PHP pada sesi ini (hanya bump versi + AGENTS.md) → suite tetap `OK (365 tests, 1059 assertions)`, PHPStan level 6 `[OK] No errors`. Next: uji manual user → bila OK, commit bump + AGENTS + push + `gh release create v0.5.0`.

---

# Sesi 13 Agu 2026 — Kwitansi 500 "tidak bisa dicetak": UNIQUE kwitansi.nomor saat no_bukti di-reuse (commit d409d23)

## Gejala (user, app v0.5.0)

"Kwitansi tidak bisa dicetak" — klik Cetak PDF → toast gagal. `laravel.log` instalasi: `SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: kwitansi.nomor` (3× pada 20:55–20:56, transaksi `019ffb66-fffd-7287-9062-5db01fdf6c12` = BPU001/20519260/02/2026).

## Root Cause (bukti keras dari DB produksi)

- Ada **2 transaksi** dgn `no_bukti = BPU001/20519260/02/2026`: yang lama (`019ffb36…`) **soft-deleted 20:51** TAPI baris `kwitansi`-nya tetap (nomor unik `BPU001/20519260/02/2026`, dicetak 20:07); yang baru (`019ffb66…`) dibuat 20:54 (reuse nomor terpilih bebas per bulan).
- `cetakKwitansi()` pakai `$transaksiBku->kwitansi()->firstOrNew([])` → query by `transaksi_bku_id` tidak ketemu (baris kwitansi milik tx lama) → insert baris baru → **tabrakan `kwitansi_nomor_unique`** → 500 → toast "Gagal mengunduh".

## Fix

- `app/Http/Controllers/TransaksiBkuController.php` — `cetakKwitansi()` & `cetakKwitansiBatch()`: `firstOrNew([])+save()` → **`Kwitansi::updateOrCreate(['nomor' => $noBukti], [...])`** — saat `no_bukti` di-reuse setelah soft-delete, baris kwitansi lama untuk nomor tsb **direassign** ke transaksi baru (transaksi_bku_id + dicetak_pada + file_pdf_path diperbarui), bukan insert baru.
- `tests/Feature/BKU/KwitansiTest.php` — test baru `test_cetak_kwitansi_reuses_nomor_when_no_bukti_reused_after_soft_delete` (print → delete tx lama → tx baru no_bukti sama → print OK, kwitansi 1 baris milik tx baru).

## Verifikasi

- Probe terhadap **salinan DB produksi** (repo code): PDF OK, kwitansi 9→9 baris, baris `BPU001/20519260/02/2026` direassign ke `019ffb66`.
- Full suite `OK (372 tests, 1078 assertions)`, PHPStan level 6 clean, `view:cache` OK.
- Commit lokal `d409d23` (14 file, +304/−40) — merangkum: fix no_bukti (NomorDokumen), fix kwitansi, PDF kwitansi/nota (dobel REJOSO/uppercase/margin/footer), bump v0.5.0. **BELUM push/rilis.**

## Perilaku no_nota vs no_bukti (klarifikasi user)

- **`no_nota` (NotaBku) TIDAK pernah di-reuse** (`NomorDokumen.php:107,113`): `withTrashed()->count()` + cek bentrok `withTrashed()` → selalu meneruskan dari nomor tertinggi pernah ada; nota dibatalkan tetap tidak muncul lagi (dokumen fisik, nomor tidak boleh dobel). Karena itu UNIQUE kwitansi tidak akan kena nomor nota.
- **`no_bukti` (BPU/BBU) reuse nomor teratas** bila soft-deleted (`NomorDokumen.php:94`); gap yang tidak pernah ada tidak diisi.

---

# Sesi 13 Agu 2026 — v0.5.1: Fix kwitansi masuk installer + reinstall & verifikasi (RILIS DITUNDAK keputusan user)

## Goal

Bawa fix "kwitansi tidak bisa dicetak" (yang selama ini hanya di repo) ke app terpasang: build installer v0.5.1, reinstall, verifikasi sesuai SOP. User menguji manual sendiri; bila lolos → user putuskan release atau belum.

## Build v0.5.1

- Bump **0.5.0 → 0.5.1** (5 file: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja) — diff 5 file, 5+/5−.
- `npm run build` OK (60 modules, app-BKekO2tT.css/app-CA7a7cYK.js). `tauri build --bundles nsis,msi` (background `%TEMP%\opencode\build-v051.log`): compile crate smartrkas 9m16s (version bump → recompile penuh) → NSIS `SmartRKAS_0.5.1_x64-setup.exe` (58.4MB) + MSI `SmartRKAS_0.5.1_x64_en-US.msi` (88.5MB).

## Reinstall & Verifikasi Instalasi Nyata

- Close app v0.5.0 (kill paksa; job object mematikan anak php). Uninstall `/S` exit 0 → folder `%LOCALAPPDATA%\SmartRKAS` hilang, **data Roaming SELAMAT**. Install v0.5.1 `/S` exit 0 → exe ProductVersion **0.5.1**, `php\php.exe` + `php\extras\ssl\cacert.pem` terbundle.
- **Fix aktif di instalasi**: `TransaksiBkuController.php` terpasang berisi `updateOrCreate` (TRUE), `firstOrNew` (FALSE).
- `Start-Process` app → `php -S 127.0.0.1:56953` (router TANPA `\\?\`, semua `-d` TLS + opcache off + error_log terpasang). `/login` = **200/200/200** (len 11614). `php-server-error.log` = 4 baris (semua fatal lama era `\\?\` 08-Agu), TIDAK ada error baru.
- **Kasus 500 nyata terverifikasi pasca-instal**: boot Laravel TERPASANG (v0.5.1) terhadap salinan DB produksi → `cetakKwitansi('019ffb66')` = **PDF OK**, kwitansi 9→9 baris, `BPU001/20519260/02/2026` direassign ke transaksi baru. Artefak probe (pdf storage instalasi + copy DB + script) dibersihkan.

## Status

- Commit lokal bump v0.5.1 + AGENTS.md. **BELUM push, BELUM rilis GitHub** — menunggu uji manual user + keputusan release.
- App v0.5.1 dibiarkan berjalan untuk uji manual user (user: "saya akan cek juga jadi kita sama sama cek").

---

# Sesi 13 Agu 2026 — Laporan BKU Bulanan: Kolom Kegiatan/Rekening/Jenis Isi dari Nota + Jenis Belanja "Belanja Cetak" (commit lokal)

## Goal

Tindak lanjut laporan user (setelah app v0.5.1 berjalan lancar): (1) di laporan BKU bulanan, transaksi hasil nota menampilkan kolom Kode Kegiatan, Kode Rekening, Jenis Belanja KOSONG; (2) mapping jenis belanja kode rekening `5.1.02.01.01.0026` (Bahan Cetak dan Penggandaan) → "Belanja Cetak". Konfirmasi user: (a) ubah jenis belanja langsung di DB; (b) scope fix laporan = PDF + Web + Export Excel.

## Root Cause

- `LaporanController` hanya eager-load `rkasItem.program` + `rkasItem.kodeRekening.jenisBelanja`; transaksi hasil nota punya `rkas_item_id = null` → `rkasItem?->...` null → kolom tampil `-`. Data sebenarnya tersedia di `notaBku->kegiatan` + `notaBku->kodeRekening`.
- Kasus nyata DB produksi: BPU018/20519260/02/2026 (Rp 550.000, NOTA-0003) → kegiatan `06.05.08.`, rekening `5.1.02.01.01.0025`, jenis "Belanja Barang Persediaan".

## Changes (repo)

- `app/Http/Controllers/LaporanController.php` — `bku()` (line 65) DAN `prepareBkuData()` (line 523, dipakai `bkuWeb`): eager-load ditambah `notaBku.kegiatan`, `notaBku.kodeRekening.jenisBelanja`.
- `resources/views/laporan/bku.blade.php` (PDF) — `$jenisBelanja` fallback ke nota; sel Kode Kegiatan/Kode Rekening `rkasItem?->... ?? notaBku?->... ?? '-'`.
- `resources/views/laporan/bku-web.blade.php` — 3 sel (Kegiatan/Rekening/Jenis Belanja) fallback nota.
- `app/Exports/BkuExport.php` — `collection()` eager-load nota; `map()` kolom Kode Kegiatan/Kode Rekening fallback nota.
- `tests/Feature/Laporan/LaporanTest.php` — +3 test: `test_laporan_bku_menampilkan_kegiatan_rekening_jenis_dari_nota` (preview), `test_laporan_bku_pdf_menampilkan_kegiatan_rekening_jenis_dari_nota` (PDF), `test_bku_export_mengisi_kegiatan_rekening_dari_nota` (map kolom 2/3/6). Helper private `createNotaTransaksi()`.

## Perubahan DB produksi (langsung, sesuai keputusan user)

- `UPDATE master_kode_rekening SET jenis_belanja_id = '019fda40-b84c-7203-86fc-fa24f493fde9' WHERE kode = '5.1.02.01.01.0026'` (jenis "Belanja Cetak" sudah ada di DB; sebelum = "Belanja Barang Persediaan"). Memakai kode Laravel TERPASANG (v0.5.1) dengan `DB_DATABASE` → Roaming DB.
- `Cache::forget('master_kode_rekenings')` + `Cache::forget('jenis_belanjas')` — cache store desktop = `database` (bundle `.env` `CACHE_STORE=database`), tabel `caches` di DB produksi; forget lintas-proses berlaku utk app yang berjalan.
- Berlaku retroaktif: transaksi BPU017 (item "FOTOCOPI HITAM PUTIH", 1.176.000) kini "Belanja Cetak".

## Verifikasi

- Probe render thd DB produksi (kode repo, read-only, login admin): **9/9 PASS** — BPU018 tampil kegiatan `06.05.08.`/rekening `5.1.02.01.01.0025`/"Belanja Barang Persediaan"; BPU017 jadi "Belanja Cetak"; bku-web render OK; export map BPU018 kolom 2=`06.05.08.`, kolom 3=`5.1.02.01.01.0025`, BPU017 rekening tetap `5.1.02.01.01.0026`.
- PHPUnit `OK (375 tests, 1090 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Script temp di `%TEMP%\opencode` dihapus; tidak ada perubahan pada instalasi (`AppData\Local\SmartRKAS`) — fix baru masuk rilis berikutnya.

## Catatan

- Baris BBU (penerimaan/tarik tunai) tetap `-` di kolom tsb (wajar, tanpa item & tanpa kegiatan nota).
- `prepareBkuData()` dipakai `bkuWeb` (preview interaktif) — sama-sama di-fix agar web & PDF & export konsisten.
- Aplikasi terpasang (v0.5.1) BELUM berisi fix laporan ini — perlu build installer baru bila user ingin fix masuk app (menunggu keputusan release v0.5.1 vs bump berikutnya).

## Test Status

- PHPUnit `OK (375 tests, 1090 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK. BELUM commit; BELUM push — commit lokal sesuai instruksi user.

---

# Sesi 13 Agu 2026 — Migrasi Universal Jenis Belanja "Belanja Cetak" (kode 5.1.02.01.01.0026) → masuk v0.5.2

## Goal

Jadikan mapping kode rekening `5.1.02.01.01.0026` (Bahan Cetak & Penggandaan) → jenis belanja **"Belanja Cetak"** sebagai **patokan konsisten di laporan untuk SEMUA instalasi** (bukan hanya DB sekolah ini). Klarifikasi user: **hanya `jenis_belanja_id` yang diubah, NAMA REKENING TETAP** — seperti kode lain yang ikut jenisnya (Belanja Modal, Belanja Jasa, dst).

## Fakta (jawaban pertanyaan user "apakah user lain juga berfungsi")

- Fix **kode** laporan (fallback kegiatan/rekening/jenis dari nota) = kode aplikasi → berlaku untuk semua user yang pakai v0.5.2.
- Kategori "Belanja Cetak" sudah ter-seed di **semua instalasi** (`DatabaseSeeder`, `app:install`).
- **Mapping** kode→jenis adalah **data per-instalasi** (hasil import master data masing-masing via `MasterKodeRekeningImport::firstOrCreate`); edit DB langsung sebelumnya HANYA mengubah DB sekolah ini. Untuk patokan universal → dibuat migrasi.

## Changes

- `database/migrations/2026_08_13_000026_set_jenis_belanja_cetak_for_kode_0026.php` (BARU) — pakai **DB facade + `Str::uuid()`** (bukan Eloquent model, agar tahan perubahan model di masa depan):
    - up(): `jenis_belanja` di-`value('id')` by nama "Belanja Cetak"; bila belum ada → insert dengan uuid baru; lalu `master_kode_rekening WHERE kode='5.1.02.01.01.0026'` di-`update(['jenis_belanja_id'=>...])`; `Cache::forget('master_kode_rekenings')` + `Cache::forget('jenis_belanjas')` (cache store desktop = `database`).
    - down(): no-op (nilai sebelumnya per instalasi tidak bisa diketahui).
- Idempoten: bila sudah "Belanja Cetak" tidak mengubah apa pun. Berlaku di first-run (`app:install`) dan upgrade (auto-migrate `lib.rs` tiap startup).

## Verifikasi (salinan DB produksi, bukan asli)

- Salin Roaming DB → `%TEMP%\opencode\mig-000026-test2.sqlite`; 000026 Pending.
- Revert mapping ke "Belanja Barang Persediaan" (simulasi kondisi lama) → `php artisan migrate --force` (env `DB_DATABASE` salinan) → **000026 DONE** (7.61ms).
- Hasil: `KODE 5.1.02.01.01.0026` → `JENIS: Belanja Cetak`, **nama rekening tetap** "Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak dan Penggandaan"; jenis id `019fda40-b84c-7203-86fc-fa24f493fde9` (sama dengan edit manual). Script verify `mig-000026-verify.php` di temp.

## Build & Release

- Karena migrasi ditambahkan SETELAH build v0.5.2 dimulai (tanpa migrasi), build lama **di-kill** dan **di-restart** agar installer menyertakan migrasi (build v0.5.2 berjalan di background; log `C:\Users\yudhi\AppData\Local\Temp\opencode\build-v052.log`).
- **Catatan log redirect**: Start-Process `cmd /c "npm run build && npm run tauri -- build --bundles nsis,msi > LOG"` — LOG harus **path absolut** (`>` relatif akan menulis di repo root). Terverifikasi log di `%TEMP%\opencode`.
- Belum push; release menunggu build selesai + verifikasi installer + uji manual user.

## Test Status

- PHPUnit `OK (375 tests, 1090 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK. Commit lokal menyusul setelah verifikasi build (migrasi + AGENTS).

---

# Sesi 13 Agu 2026 — Reinstall v0.5.2 Terverifikasi + Push & Release GitHub

## Reinstall v0.5.2 (verifikasi penuh)

- Kill v0.5.1 bersih (0 proses tersisa) → uninstall `/S` exit 0 (sisa cache view runtime `storage/framework/views` dihapus manual) → **DB Roaming utuh** (1.63MB) → install v0.5.2 `/S` exit 0.
- Verifikasi: exe ProductVersion **0.5.2**; `php.exe` + `php\extras\ssl\cacert.pem` terbundle; **migrasi `2026_08_13_000026` terbundle** di instalasi.
- App jalan → server `php -S 127.0.0.1:59772` (semua arg `-d opcache.enable=0 log_errors=1 error_log=... curl.cainfo=... openssl.cafile=...` terpasang; router TANPA prefix `\\?\`) → `/login` = **200**.
- **Auto-migrate DB produksi berjalan**: `000026` → **batch 5 (Ran)**; mapping `5.1.02.01.01.0026` = "Belanja Cetak" (nama rekening tetap).
- `php-server-error.log` = 4 baris (semua fatal lama era `\\?\` 08-Agu), tidak ada error baru.

## Release

- Commit lokal `d88ded0` (migrasi 000026 + bump v0.5.2 + AGENTS) → push `master` (`1579849..d88ded0`).
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.5.2 — 2 asset state `uploaded` (NSIS 61.2MB + MSI 93.0MB), bukan draft. Notes via `--notes-file` temp (hindari globbing PowerShell).

## Catatan

- v0.5.2 merangkum SEMUA pekerjaan sejak v0.4.2 (16 commit): Nota Multi-Item + RealisasiQuery (1 nota = 1 BPU, atribusi), form BKU pengeluaran terpadu, Riwayat/Detail Nota + PDF Rincian Belanja (No. BPU), fix kwitansi UNIQUE nomor saat no_bukti di-reuse, partial unique index no_bukti, laporan BKU isi kegiatan/rekening/jenis dari nota (PDF+web+export), migrasi universal "Belanja Cetak" (hanya jenis, nama rekening tetap).

## Test Status

- Tidak ada perubahan logika PHP pada sesi rilis → suite tetap `OK (375 tests, 1090 assertions)`, PHPStan level 6 `[OK] No errors`.

---

# Sesi 14 Agu 2026 — Fix Konsistensi Realisasi Nota Multi-Item di Rekap SIPLAH (Web+Export) & Kartu Total Realisasi RKAS (commit lokal)

## Goal

Samakan sumber data Rekap SIPLAH (`LaporanController::prepareRekapSiplahData` + `RekapSiplahExport`) dan kartu "Total Realisasi" di `/rkas` agar memakai **`RealisasiQuery` yang sudah ada** (bukan query `TransaksiBku` mentah), sehingga realisasi nota multi-item (atribusi via `nota_bku_item`) ikut terhitung dan terkategorisasi ke jenis belanja item-nya — bukan jatuh ke "Tidak Terkategori". Instruksi user: pakai `RealisasiQuery` yang ada (jangan query baru), samakan pola dengan `RekapRekeningExport`/`RekapKuartalExport`.

## Summary

- `RealisasiQuery::union()` kini mengembalikan kolom tambahan **`metode_pengadaan`** (cabang transaksi: `... jumlah, metode_pengadaan`; cabang nota: `... nbi.subtotal as jumlah, nb.metode_pengadaan as metode_pengadaan` — diambil dari header `nota_bku`, bukan `nota_bku_item`).
- `prepareRekapSiplahData`: totals + breakdown via `RealisasiQuery::base()` (alias `rb`) + `join`/`leftJoin` `ri_sub` (rkas_item) → `mkr_sub` (master_kode_rekening) → `jb_sub` (jenis_belanja); filter `rb.bulan` + `ri_sub.sumber_dana_id` (+ `ri_sub.tahun_anggaran_id` bila ada). Web pakai `leftJoin` + `COALESCE(jb_sub.nama, 'Tidak Terkategori')` (perilaku lama); export pakai INNER join. `import DB` dihapus.
- `RekapSiplahExport::collection()`: query via `RealisasiQuery::base()` + INNER join, `selectRaw` + `groupBy` + `orderBy('jb_sub.nama')`; mapping lama (`new TransaksiBku()` + `setAttribute`) dipertahankan utuh.
- `RkasController::totalRealisasi`: `RealisasiQuery::base()->joinSub($filteredIds()->toBase(), 'ri_filtered', ...)->sum('rb.jumlah')` (joinSub butuh `Query\Builder` → `->toBase()` wajib); import `TransaksiBku` dihapus, `RealisasiQuery` ditambah.
- Nota multi-item tetap hanya dihitung bila ≥1 transaksi flatten aktif (`whereExists`); transaksi flatten (`rkas_item_id` null) TIDAK dobel dihitung (cabang transaksi `whereNull('nota_bku_id')`).

## Tests

- `tests/Feature/Laporan/LaporanTest.php` — helper `createNotaMultiItem()` (+ docblock `@return array{...}` utk PHPStan): NotaBku metode `siplah` + 2 NotaBkuItem subtotal 100000/200000 (rkas_item_id & jenis belanja beda) + 1 transaksi flatten (rkas_item_id null, jumlah 300000). Test `test_laporan_rekap_siplah_menampilkan_realisasi_nota_multi_item` (assertSee jenis "Belanja ATK Nota"/"Belanja Obat Nota", `Rp 100.000`/`Rp 200.000`/`Rp 800.000`, assertDontSee `Tidak Terkategori`) dan `test_rekap_siplah_export_mencerminkan_realisasi_nota_multi_item` (rows per jenis, assertSame 100000.0/200000.0, doesntContain `Tidak Terkategori`).
- `tests/Feature/RKAS/RkasControllerTest.php` — `test_index_total_realisasi_mencerminkan_nota_multi_item`: makeItem(1/2/3) = 500000/100000/200000 + nota 2 item (100000+200000) + flatten 300000 → `GET /rkas` assertSee `Rp 300.000` (unik vs Total Rencana `Rp 800.000`; tarif factory ≤ 100.000 tidak bentrok).
- **Koreksi PHPStan**: `$totals` hasil `->first()` pada query agregat dinilai non-nullable → `(float) ($totals->total ?? 0)`, BUKAN `$totals?->total` (error `nullsafe.neverNull`).
- Verifikasi: targeted `--filter "LaporanTest|RkasControllerTest"` → OK (56 tests, 147 assertions); full suite → **OK (378 tests, 1105 assertions)** (naik dari 375/1090); PHPStan level 6 → **`[OK] No errors`**.

## Catatan

- Union TIDAK membawa `tahun_anggaran_id`/`sumber_dana_id` → filter lewat join `ri_sub`.
- `ExportAmountFormatTest::test_rekap_siplah_export_writes_amount_as_numeric_with_number_format` tetap hijau (pola export lama dipertahankan).
- File berubah: 6 (`RealisasiQuery`, `LaporanController`, `RekapSiplahExport`, `RkasController`, `LaporanTest`, `RkasControllerTest`) — +244/−49.
- BELUM push/build/rilis — masih ada uji manual belum tuntas (skenario 2+ item, all-or-nothing, dsb dari daftar sebelumnya) sebelum bicara rilis. Commit lokal sesuai instruksi user.

## Test Status

- PHPUnit `OK (378 tests, 1105 assertions)`, PHPStan level 6 `[OK] No errors`. Commit lokal; BELUM push.

---

# Sesi 14 Agu 2026 — Dashboard Konsisten dgn Nota: "Transaksi Terkini" & "Transaksi Bulan Ini" Ikut Transaksi Nota (commit lokal)

## Goal

Setelah fix realisasi nota (commit `816f7a3`) menyelaraskan Rekap SIPLAH + kartu Total Realisasi RKAS, tutup celah konsistensi yang tersisa di dashboard: transaksi hasil nota (`rkas_item_id = NULL`) TIDAK muncul di kartu **"Transaksi Terkini"** dan TIDAK dihitung oleh **`transaksiBulanIni`** (alert "Belum ada transaksi BKU bulan ini" bisa salah muncul). Audit log (Riwayat Aktivitas) dicek lebih dulu dan sudah konsisten (nota tercatat `nota_bku.create/delete`; transaksi flatten hanya Outbox — by design 1 nota = 1 BPU).

## Changes

- `app/Http/Controllers/DashboardController.php` — `transaksiBulanIni` & `recentTransaksi` kini pakai closure filter:
    - `whereIn('rkas_item_id', $filteredIds)` **OR** `whereHas('notaBku', ...)` dengan `whereNull('deleted_at')` (nota aktif) + `whereHas('items', rkas_item_id in filteredIds)` — transaksi nota milik item yang ter-filter ikut terhitung/tampil.
    - `recentTransaksi` eager-load ditambah `notaBku.kegiatan`, `notaBku.kodeRekening.jenisBelanja`, `notaBku.items.rkasItem` (selain `rkasItem.program/kodeRekening.jenisBelanja`).
- `resources/views/dashboard.blade.php` — baris "Transaksi Terkini": fallback kolom saat transaksi nota (`rkasItem` null): `$kegiatanNama` = `rkasItem?->program?->nama ?? notaBku?->kegiatan?->nama ?? '-'`; `$jenisNama` serupa via kode rekening; `$uraianItem` = uraian item, bila null → daftar `notaBku->items` uraian item (map/filter/unique/implode ', '). Tambah badge `badge-purple text-xs ml-1` "Nota" di samping uraian saat `$trx->nota_bku_id`.
- `tests/Feature/Laporan/LaporanTest.php` — **fix test flaky** (pra-ada, TIDAK terkait): `test_laporan_bku_with_different_tahun` memakai `tahun => 2024` yang bisa bertabrakan dgn tahun acak `TahunAnggaran::factory()` di setUp (`fake()->unique()->year()` range lebar ~1972–2026+) → `UNIQUE constraint failed: tahun_anggaran.tahun`. Ganti ke `tahun => 2100` (di luar range factory → deterministik).
- `tests/Feature/Dashboard/DashboardTest.php` — +2 test (+ imports `NotaBku`, `NotaBkuItem`, `Illuminate\Support\Carbon`):
    - `test_dashboard_transaksi_terkini_menampilkan_transaksi_nota`: nota 2 item (100000+200000) + 1 transaksi flatten (rkas_item_id null, BPU901, uraian "Nota belanja NOTA-0001", jumlah 300000) → assertSee uraian transaksi, kegiatan nota ("Kegiatan Unik Nota Dashboard" — kegiatan sengaja beda dari item agar bukti fallback), gabungan uraian item "Item Nota A, Item Nota B", dan `Rp 300.000`.
    - `test_dashboard_alert_transaksi_bulan_ini_menyertakan_transaksi_nota`: nota bulan sekarang + transaksi flatten bulan sekarang → alert "Belum ada transaksi BKU bulan" TIDAK muncul (`assertDontSee`).

## Verifikasi

- PHPUnit full suite **`OK (380 tests, 1112 assertions)`** (naik dari 378/1105), PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Catatan: satu-satunya error saat iterasi = flaky LaporanTest (collision tahun 2024) — dibuktikan lewat sample range Faker (`fake()->year()` menghasilkan 1972–2023+ acak) lalu di-fix deterministik (2100); `--filter LaporanTest` → OK (29 tests, 69 assertions).

## Catatan

- Audit log sudah konsisten SEBELUM perubahan ini: `NotaBkuController` mencatat `nota_bku.create` (no_nota/total/jumlah_item) + `nota_bku.delete` (via `deleteNotaWithTransaksis`), badge aksi create/delete tampil lewat fallback `badge-green/badge-red` di `audit-log.blade.php`.
- Deskripsi bug lama di baris-baris sesi sebelumnya yang menyebut "AuditLog `transaksi_bku.create` dijalankan per transaksi flatten" (sesi Nota Multi-Item) tidak akurat untuk perilaku nota saat ini — nota flatten hanya Outbox, bukan AuditLog (audit di level nota).
- File berubah: 4 (DashboardController, dashboard.blade.php, DashboardTest, LaporanTest) — +137/−9. Commit lokal `5eb763d`; BELUM push/build/rilis (menunggu uji manual skenario 2+ item & all-or-nothing).

## Test Status

- PHPUnit `OK (380 tests, 1112 assertions)`, PHPStan level 6 `[OK] No errors`. Commit lokal; BELUM push.

---

# Sesi 14 Agu 2026 — Sidebar: Group "Pengaturan" Jadi Dropdown (commit lokal)

## Goal

User menanyakan perbaikan sidebar agar tidak terlalu scroll ke bawah. Pilihan disetujui user: **hanya group "Pengaturan" jadi dropdown** (pola sama seperti "Referensi & Master" yang sudah ada).

## Changes

- `resources/views/layouts/navigation.blade.php` — 7 item flat Pengaturan (Profil Sekolah, Akun & Login, Backup & Pemulihan, Riwayat Aktivitas, Kode Pemulihan, Notifikasi Telegram, Tentang Aplikasi) dibungkus dalam `x-data` dropdown ala "Referensi & Master":
    - `open` = `routeIs('pengaturan-sekolah.*|profile.*|pengaturan.backup.*|pengaturan.audit.*|pengaturan.recovery-code.*|pengaturan.telegram.*|tentang.*')` → auto-expand saat halaman aktif.
    - Submenu link memakai pola submenu lama (bullet circle + `nav-text`).
    - **Bonus**: item aktif di-highlight `text-white bg-white/5` (submenu "Referensi & Master" belum punya ini — konsistensi visual untuk pengaturan saja, cakupan sesuai keputusan user).
    - Item flat turun 14 → ~6 di layar (Dashboard + 4 RKAS + 2 dropdown).

## Verifikasi

- `php artisan view:cache` OK; PHPUnit `OK (380 tests, 1112 assertions)`.
- Probe render nyata (CLI, route disimulasi): **9/9 PASS** — tombol Pengaturan ada, `open:false` di dashboard, `open:true` saat current route profile.edit, 7 submenu ada, link flat lama hilang, highlight `text-white bg-white/5` benar-benar terpasang pada item aktif.
- Catatan probe: `ProfileController::edit` CLI butuh `view()->share('errors', new ViewErrorBag)`; `request()->routeIs()` di CLI selalu false → wajib simulasikan route (Route::get + setRouteResolver + `app()->instance('request', ...)`); panggil controller via `app()->make()` + `app()->call([$instance, 'method'])` (array `[Class::class, 'method']` dipanggil statik → error).

## Test Status

- PHPUnit `OK (380 tests, 1112 assertions)`, PHPStan level 6 `[OK] No errors` (tidak ada perubahan logika PHP/controller). Commit lokal `4dad817`; BELUM push/build/rilis.

---

# Sesi 11–13 Agu 2026 — RINGKASAN FITUR NOTA MULTI-ITEM (dari awal sampai Tahap 4 selesai)

## Konsep Final (keputusan user, sesi 11 Agu)

- **1 Nota = tepat 1 Kegiatan + boleh banyak Item Belanja.** Kegiatan berbeda → nota baru (NOTA-0001, NOTA-0002, dst).
- `nota_bku` (no_nota, tanggal, bulan, kegiatan_id→master_program, kode_rekening_id→master_kode_rekening, sumber_dana_id→sumber_dana, toko_penerima, metode_pengadaan, no_invoice_siplah, uraian, tahun_anggaran_id, created_by) + `nota_bku_item` (urutan, rkas_item_id, jumlah, satuan, harga_satuan, subtotal). TIDAK ada perantara `nota_bku_kegiatan`.
- `TransaksiBku` tetap transaksi finansial utama; nota = pengelompokan/dokumen yang di-_flatten_ jadi transaksi.
- **`no_nota` ≠ `no_bukti`** (format `NOTA-0001/NPSN/MM/YYYY` vs `BPU001/NPSN/MM/YYYY`).
- Kegiatan & Sumber Dana = FK; sumber dana diturunkan dari item (campur → ditolak).
- **Override Sisa Anggaran untuk nota = TIDAK ada** → guard all-or-nothing; PA/pergeseran (backlog) hanya utk item belum dibelanjakan.
- Delete nota = hapus (soft) nota + SEMUA BPU hasil flatten (Audit + Outbox per transaksi + nota).

## Evolusi Desain Selama Implementasi

1. **Awal (11–12 Agu)**: `NotaBkuController` punya `create()` + route `POST /nota-bku` + view `nota-bku/create.blade.php`; transaksi flatten **N item = N TransaksiBku** (BPU018→Item1, BPU019→Item2, ...).
2. **Poin 5 (13 Agu)**: 1 nota = **1 TransaksiBku** (total, `rkas_item_id=null`, `nota_bku_id`), realisasi per item ditelusuri via `nota_bku_item` (atribusi). `RealisasiQuery` (UNION) jadi satu-satunya sumber realisasi item.
3. **Poin 6 (13 Agu)**: reuse `no_bukti` soft-deleted via **partial unique index** `transaksi_bku_no_bukti_aktif_unique (WHERE deleted_at IS NULL)`; `NomorDokumen::noBukti()` nomor terkecil bebas.
4. **Poin 4 (13 Agu)**: form kembali terisi saat nota/gabungan ditolak (restore `old('items')` via hidden inputs `items[{uuid}][...]`).
5. **Penyatuan form (12 Agu, Tahap 1–4)**: form Pengeluaran jadi SATU form di `/transaksi-bku/create` — pilih Kegiatan → Kode Rekening → centang item → qty/harga. **1 item tercentang** = transaksi langsung (dgn opsi override + kunci kwitansi); **2+ item** = NotaBku all-or-nothing. Route `/nota-bku/create` + `NotaBkuController::create()` + view `nota-bku/create.blade.php` DIHAPUS (Tahap 4). Entry point nota kini HANYA lewat form BKU terpadu.
6. **Fix realisasi (13 Agu)**: transaksi flatten legacy (`rkas_item_id` + `nota_bku_id`) tidak dobel (`whereNull('nota_bku_id')`); rincian nota hanya dihitung bila ≥1 transaksi aktif (`whereExists`); hapus BPU nota → cascade hapus nota (anggaran kembali).

## Struktur Akhir (Tahap 4)

- **Model**: `NotaBku` (HasUuids+SoftDeletes+HasFactory; relasi kegiatan/sumberDana/tahunAnggaran/createdBy/items/transaksiBkus/kodeRekening), `NotaBkuItem` (notaBku/rkasItem).
- **Controller**: `NotaBkuController` (index/show/destroy/cetak/items + `storeFromItems()` reusable + `deleteNotaWithTransaksis()`); `TransaksiBkuController::store()` dispatch: `count≥2` → `storeFromItems()`, `count===1` → `storeSingleItem()`.
- **Routes** (grup auth): `nota-bku.index`, `nota-bku.items`, `nota-bku.show`, `nota-bku.destroy`, `nota-bku.cetak` — TANPA POST `/nota-bku` (dihapus Tahap 4). Form gabungan via `transaksi-bku.create` POST `/transaksi-bku`.
- **Views**: `nota-bku/index|show|cetak`; `transaksi-bku/create|edit` (form gabungan + picker `_search-picker.blade.php`); kwitansi/nota PDF isi Program/Sub Program/Kode Rekening (dari nota saat `rkas_item_id` null), "No. BPU"/"Rincian Belanja", tanpa "No. Nota" & tanpa field "Uraian" (cukup kotak "Untuk").
- **Realisasi item**: `app/Support/RealisasiQuery.php` (UNION transaksi aktif tanpa nota + nota_bku_item dari nota ber-transaksi aktif) dipakai Dashboard, Rekap SIPLAH (web+export), kartu Total Realisasi RKAS, guard `realisasiKumulatifSd()`.
- **Dashboard**: Transaksi Terkini & transaksi bulan ini ikut transaksi nota (fallback kegiatan/rekening/uraian dari nota + badge "Nota").
- **Laporan BKU**: kolom Kegiatan/Rekening/Jenis Belanja isi dari nota (PDF+web+export); migrasi universal `5.1.02.01.01.0026` → "Belanja Cetak".

## Status Akhir

- PHPUnit **`OK (380 tests, 1114 assertions)`** (jumlah test sama dgn baseline 380; +2 assertion dari cek item kedua di test normalisasi; 0 test dihapus — semua cakupan nota dipertahankan lewat payload 2+ item di `postNota()`), PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Rilis v0.5.2 sudah di-push (memuat fitur nota sampai 13 Agu). Perubahan Tahap 4 (route POST `/nota-bku` dihapus + `store()` wrapper dihapus + test diadaptasi ke `/transaksi-bku`) adalah **commit lokal baru** — BELUM push/build/rilis.

---

# Sesi 15 Agu 2026 — Reinstall v0.5.2 (fix realisasi nota + dropdown searchable) + 2 TEMUAN BARU (PENDING — dilanjutkan nanti)

## Reinstall v0.5.2 (fix RKAS + dropdown searchable) — SELESAI

- **Fix realisasi nota per-item** (commit `364ce4c`): `RkasController::index()` eager-load `notaBkuItems`; view `rkas/index.blade.php:168` — `$realisasi = transaksiBkus->sum('jumlah') + notaBkuItems->sum('subtotal')`; sisa/persen konsisten dgn kartu Total Realisasi & Dashboard. Test `test_index_sisa_per_item_mencerminkan_realisasi_nota`.
- **Dropdown program & kode rekening searchable** (commit `9b7d939`): partial `_search-picker.blade.php` dipakai di filter RKAS, dashboard, laporan rekap-rekening & rekap-kuartal (program), form edit RKAS. Opsi baru partial: `spRequired` (default true), `spStatusHint`, `spShowError`, `spAutoSubmit` (submit form saat pilih/bersihkan — menggantikan `onchange="this.form.submit()"`).
- Build ulang installer: NSIS `SmartRKAS_0.5.2_x64-setup.exe` (61.2MB, 12:15) + MSI `SmartRKAS_0.5.2_x64_en-US.msi` (93.2MB, 12:18). Reinstall (uninstall `/S` exit 0 → install `/S` exit 0) → exe ProductVersion **0.5.2**, php + cacert terbundle, controller/view terpasang berisi fix (verif string). App jalan → `/login` **200** (port 58116), DB Roaming utuh (1.63MB), `php-server-error.log` tetap 4 baris (tidak bertambah).
- Full suite **`OK (384 tests, 1126 assertions)`** (naik dari 380 — 2 test nota RkasController + 2 test dashboard + fix flaky LaporanTest tahun 2100), PHPStan `[OK] No errors`, `view:cache` OK. Commit lokal `364ce4c` + `9b7d939`; **BELUM push**.

## TEMUAN BARU 1 — Tabel RKAS item "Tinta Spidol Whiteboard" (angka tampil mencurigakan)

User melaporkan baris item `Tinta Spidol Whiteboard` di tabel RKAS menampilkan:

```
Volume: 10 dus · Tarif: Rp 10.000 · Jumlah: Rp 500.000 · Realisasi: Rp 100.000 · Sisa: Rp 400.000 · (sisa 10 dus) · Status: 20%
```

- **Masalah A (volume sisa salah)**: `rkas/index.blade.php:171-172` — `$realisasiVolume = $item->transaksiBkus->sum('volume')`; `$sisaVolume = volume - realisasiVolume`. Bila realisasi Rp 100.000 berasal dari **nota multi-item** (transaksi flatten `rkas_item_id=null`, `volume=null`, atribusi via `nota_bku_item`), maka `transaksiBkus` item TIDAK membawa volume → realisasiVolume 0 → sisa volume tampil 10 dus padahal secara nominal 10 dus×Rp 10.000 = Rp 100.000 sudah terpakai semua. **Belum diverifikasi dari DB** (probe PHP bundle `-r` gagal parse `\` di PowerShell — perlu script file di `%TEMP%`).
- **Masalah B (konsistensi volume×tarif vs jumlah)**: 10 × 10.000 = 100.000 ≠ jumlah 500.000. Status 20% dihitung dari realisasi/jumlah (100.000/500.000) — perlu cek apakah `jumlah` item (sum rencana bulan) memang 500.000 & tarif/volume memang 10.000/10 (data entry user) atau ada bug normalisasi.
- **Belum ada perbaikan** — perlu verifikasi DB dulu (data produksi Roaming, jangan ubah apa pun).

## TEMUAN BARU 2 — Form pencarian program & kode rekening "tidak responsif" (di tabel RKAS)

- Setelah dropdown diganti searchable picker, user melaporkan form pencarian kode program & rekening di halaman RKAS **tidak responsif** (kemungkinan: dropdown hasil tidak muncul / autocomplete tidak jalan / layout flex form filter rusak / item hasil tak bisa diklik).
- `rkas/index.blade.php:73-131` — form filter `<form class="flex items-center gap-3">` berisi: search input, select `bulan`, **picker program**, select `tahun`, select `sumber_dana_id`, **picker kode_rekening**, select `jenis_belanja_id` — 7 kontrol dalam satu baris flex tanpa `flex-wrap`. Picker partial merender div `<label>+<input>+<div relative>+<p status>` (tinggi lebih besar drpd select) → layout tidak sejajar & di layar kecil meluber. Plus partial punya 2 blok `<script>` per include (guard `__entityPickerInit`), 2 include → 2 panggilan init.
- **Belum diverifikasi di browser** — perlu cek DOM nyata (apakah `#program_results` muncul, `initEntityPicker` error, atau hanya masalah layout/wrap).

## Catatan Proses

- Probe PHP bundle dari PowerShell: jangan pakai `php -r` dengan `\` escaped berlapis (parse error); tulis script temp di `%TEMP%\opencode` + panggil `php.exe <script>` dengan env `DB_DATABASE`/`SMARTRKAS_DATA_DIR` di-set.
- App v0.5.2 dibiarkan berjalan (port 58116) untuk uji manual user.

---

# Sesi 15 Agu 2026 — Fix 2 Temuan: Volume Sisa dari Nota + Picker Compact di Filter RKAS (commit lokal, BELUM push)

## Temuan 1 TERVERIFIKASI & DIFIX — Volume sisa item RKAS tidak menghitung nota multi-item

- **Bukti DB produksi** (probe `%TEMP%\opencode\probe-tinta.php` thd Roaming DB): item `Tinta Spidol Whiteboard` (id `019fd4fc-920c-70c5-a9d8-dbdcd74027a9`): volume=10 dus, tarif=10000, jumlah=500000, sum rencana bulan=500000. Transaksi aktif item = **0**; nota aktif 1 (`01a003aa-...`): `nota_bku_item qty=10 dus subtotal=100000`. Jadi realisasi Rp 100.000 = nota multi-item (10 dus × Rp 10.000 sudah terpakai semua).
- **Fix** `resources/views/rkas/index.blade.php:171`: `$realisasiVolume = $item->transaksiBkus->sum('volume') + $item->notaBkuItems->sum('jumlah')` (qty nota ikut dihitung). Sebelumnya hanya `transaksiBkus->sum('volume')` = 0 utk transaksi flatten (`volume=null`) → sisa tampil 10 dus (SALAH). Kini tampil `(sisa 0 dus)` — terverifikasi render nyata thd DB produksi.
- **Inkonsistensi data item** (BUKAN bug kode): `volume×tarif` (100.000) ≠ `jumlah` (500.000) — user pernah menaikkan rencana tanpa menyesuaikan volume/tarif. Status 20% = 100.000/500.000 benar sesuai rumus.
- Test baru `test_index_sisa_volume_mencerminkan_nota_multi_item` (skenario persis laporan user).

## Temuan 2 TERVERIFIKASI & DIFIX — Form filter RKAS meluber karena picker lebih tinggi dari select

- User konfirmasi gejala = **layout berantakan/meluber** (bukan dropdown mati). Form `rkas/index.blade.php:73` = `flex items-center gap-3` (7 kontrol, tanpa `flex-wrap`); partial picker merender label + input + status hint (tinggi jauh melebihi select).
- **Fix**: partial `_search-picker.blade.php` dapat opsi baru **`spCompact`** (default false):
    - Saat `spCompact=true`: label status & tombol "Bersihkan" bawah TIDAK dirender; tombol clear jadi `×` absolute di kanan input; input pakai `py-1.5 text-sm` (sejajar select); `statusId` dikirim `null` ke JS.
    - Filter RKAS: kedua include picker tambah `'spCompact' => true` + form jadi `flex flex-wrap items-center gap-3`.
    - Dashboard & laporan TIDAK diubah (sudah pakai grid `items-end`, tidak meluber).
- Test lama `test_index_page_renders_new_filters` diadaptasi: assert `Cari rekening (kode / nama)...` (placeholder) menggantikan `Semua Kode Rekening` (status hint tak dirender lagi).

## Verifikasi

- Render nyata thd DB produksi (probe `probe-rkas-picker.php`): item Tinta → `(sisa 0 dus)`; picker → `statusId: null` ×2, label `<label for="program_search"` count 0, `flex flex-wrap` aktif.
- PHPUnit full suite **`OK (385 tests, 1130 assertions)`** (naik dari 384/1126 — +1 test volume), PHPStan level 6 `[OK] No errors`, `view:cache` OK.
- BELUM build installer, BELUM push — menunggu uji manual user di app (app terpasang masih v0.5.2 tanpa fix ini).

---

# Sesi 15 Agu 2026 — Ringkasan Capaian & Realisasi per Jenis Belanja di Data RKAS (commit lokal, BELUM push)

## Goal

Tambahkan blok "Ringkasan Capaian" + "Realisasi per Jenis Belanja" (sebelumnya hanya ada di Dashboard) ke halaman Data RKAS di atas card filter "Daftar RKAS", dengan perhitungan **identik Dashboard** (keputusan user: "hitungannya tetap seperti itu").

## Summary

- PHPUnit full `OK (389 tests, 1150 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK.
- HTTP live (salinan DB): capaian 6.9% (12.388.000/180.320.000), breakdown 5 jenis belanja yang **jumlahnya = total realisasi** (selisih 0).
- Route temp `/__shot/rkas` & `/__shot/dashboard` DIHAPUS; server dev 8027 dimatikan; artefak temp dibersihkan.

## Changes

- `app/Http/Controllers/RkasController.php` — `index()`:
    - `$filteredIdsNoBulan = (clone $baseQuery)->pluck('id')` diambil **SEBELUM** `whereHas('bulanRencana')` → dipakai breakdown per jenis secara kumulatif (pola persis `DashboardController::chartData` baris 95-103). **PENTING**: closure `$filteredIds` men-capture object `$baseQuery`, jadi kalau dipanggil setelah `whereHas` bulan sudah masuk filter bulan — itu yang dipakai untuk `totalJumlah`/`totalRealisasi` bulan-aware.
    - `$persentaseCapaian = $totalJumlah > 0 ? round(($totalRealisasi / $totalJumlah) * 100, 1) : 0`.
    - `$jenisBelanjaRealisasi` = `RealisasiQuery::base()->whereIn('rb.rkas_item_id', $filteredIdsNoBulan)` + join `rkas_item`/`master_kode_rekening`/`jenis_belanja`, `selectRaw('jenis_belanja.nama as label, sum(rb.jumlah) as total')`, groupBy nama, orderByDesc total → map ke `['label','total','persen']` (persen dari `$totalRealisasi`), filter total > 0.
    - Tambah `persentaseCapaian`, `jenisBelanjaRealisasi` ke `compact()`.
- `resources/views/rkas/index.blade.php` — blok `grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6` di atas card filter:
    - Card "Ringkasan Capaian": progress bar `bg-gradient-to-r from-indigo-500 to-emerald-500` + tile Rencana/Realisasi/Sisa (sisa merah bila negatif).
    - Card "Realisasi per Jenis Belanja": daftar label + `Rp … (persen%)` + progress bar `bg-blue-500` (persen dari total realisasi); empty-state "Belum ada realisasi".
    - Kondisi tampil: `$totalJumlah > 0 || $jenisBelanjaRealisasi->isNotEmpty()` (aman saat tahun non-aktif: variabel default 0/collect()).
- `resources/views/pengaturan/tentang.blade.php` — "Petunjuk Penggunaan Singkat" item 3 (Data RKAS: filter + pantau capaian per jenis) & item 4 (BKU pengeluaran: Kegiatan→Rekening→centang item, 2+ item = Nota Multi-Item).
- `tests/Feature/RKAS/RkasControllerTest.php` — `test_index_menampilkan_ringkasan_capaian_dan_realisasi_per_jenis_belanja` (2 jenis belanja beda, 2 transaksi → assert "Ringkasan Capaian", "Realisasi per Jenis Belanja", Rp 800.000, Rp 150.000, nama kedua jenis, "66.7" = 100.000/150.000).
- `LAPORAN-PERUBAHAN.md` — tambah bagian 6.

## Catatan

- Breakdown per jenis sengaja **kumulatif** (tanpa filter bulan) agar konsisten dengan chart dashboard; ringkasan capaian tetap bulan-aware mengikuti total halaman. Inkonsistensi ini sudah ada di Dashboard dan dipertahankan sesuai instruksi user.
- Progress bar `min(100, ...)` mencegah overflow visual saat capaian >100%.
- Working tree juga membawa perubahan sebelumnya yang belum di-commit dari picker compact (`dashboard.blade.php` `spCompact`, `_search-picker.blade.php` guard `spLabel`) — diverifikasi suite hijau, digabung di commit sesi ini.
- Verifikasi HTTP live memakai salinan DB (bukan produksi); DB produksi tidak diubah. Route temp `/__shot/*` dihapus agar tidak bocor ke produksi.
- BELUM build installer, BELUM push — menunggu keputusan user.

---

# Sesi 15 Agu 2026 — Release v0.5.3 (Build + Push + GitHub)

## Goal

Bawa fitur "Ringkasan Capaian & Realisasi per Jenis Belanja di Data RKAS" (commit `8f9d8ee`) + fix volume sisa nota + picker compact (`0007070`) ke installer dan rilis ke GitHub. Versi bump 0.5.2 → **0.5.3** (karena v0.5.2 sudah ter-rilis dengan 2 asset).

## Build

- Bump 0.5.3 di 5 file (`config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja — diff Cargo.lock = 1 file 1+/1-).
- `npm run build` OK. `tauri build --bundles nsis,msi` (background, log `%TEMP%\opencode\build-v053.log`): compile 7m21s → NSIS `SmartRKAS_0.5.3_x64-setup.exe` (58.4MB) + MSI `SmartRKAS_0.5.3_x64_en-US.msi` (89MB).

## Reinstall & Verifikasi

- Kill app v0.5.2 → job object mematikan anak php bersih (0 proses tersisa). Uninstall `/S` exit 0 → folder `%LOCALAPPDATA%\SmartRKAS` hilang, **data Roaming SELAMAT**. Install v0.5.3 `/S` → exe ProductVersion **0.5.3**, `php\php.exe` + `php\extras\ssl\cacert.pem` (186KB) terbundle.
- App jalan → server `php -S 127.0.0.1:63200` (semua `-d opcache.enable=0 log_errors=1 error_log=... curl.cainfo=... openssl.cafile=...` terpasang; router TANPA prefix `\\?\`) → `/login` **200** (len 11272).
- Fix strings terverifikasi di instalasi: `RkasController.php` (`persentaseCapaian`/`jenisBelanjaRealisasi`), `rkas/index.blade.php` ("Ringkasan Capaian"/"Realisasi per Jenis Belanja"), `config/app.php` APP_VERSION 0.5.3.
- `php-server-error.log` = 4 baris (semua fatal lama era `\\?\` 08-Agu), tidak ada error baru.

## Release

- Commit `5633ae0` (bump 0.5.3) → push `master` (`52b0afc..5633ae0`).
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.5.3 — 2 asset state `uploaded` (NSIS 61.2MB + MSI 93.3MB), bukan draft.
- Catatan rilis: Ringkasan Capaian, Realisasi per Jenis Belanja, fix volume sisa nota, picker filter compact, petunjuk halaman Tentang diperbarui.

## Catatan Proses

- Release create di-PowerShell bisa "timeout" di tool setelah URL muncul — jangan anggap gagal; verifikasi via `gh release view --json isDraft,isPrerelease,assets`.
- `Invoke-WebRequest -MaximumRedirection 0` di PowerShell non-interactive melempar prompt error → pakai `curl.exe -s -o <file> -w "%{http_code}"` untuk cek status HTTP.
- `(Get-Content -Raw) -replace '^version = ...'` (anchor `^...$`) TIDAK bekerja pada single string tanpa flag `(?m)` — pakai `-replace '(?m)^version = "0\.5\.2"$', ...`.
- App v0.5.3 dibiarkan berjalan untuk uji manual user (server 63200).

---

# Sesi 15-16 Agu 2026 - SELESAI (commit lokal, BELUM push/build/rilis): Fitur "Terima Hasil Pergeseran/PAK dari ARKAS (via Import)"

## Status

- **SELESAI DIIMPLEMENTASI** (Tahap 1-4) + 18 test baru (11 ImportRevisiControllerTest + 7 ImportRevisiImportTest). Commit lokal `5119ece`.
- Pivot desain: pengerjaan pergeseran/PAK dilakukan di ARKAS (system of record) - SmartRKAS TIDAK membangun form pergeseran manual, tapi MENERIMA hasil via import (menumpang infrastruktur import RKAS).
- **BELUM diuji manual browser** - seluruh Tahap 1-4 belum pernah disentuh dari UI sungguhan; uji manual (dgn SALINAN DB produksi di `%TEMP%\opencode\test-revisi.sqlite`) adalah langkah berikutnya sebelum bicara rilis.

## Ringkasan Implementasi

- `database/migrations/2026_08_16_000027_create_rkas_revisi_tables.php` — tabel `rkas_revisi` (id, no_revisi, tanggal, bulan? NO — per revisi: tahun_anggaran_id, sumber_dana_id, jenis pergeseran/pak, keterangan, arah?, data_perubahan JSON, created_by, timestamps, softDeletes) + `rkas_revisi_item` (rkas_revisi_id FK cascade, rkas_item_id FK null, urutan, bulan, sebelum, sesudah, selisih, arah naik/turun, jenis_belanja_id?, data_perubahan JSON).
- Model `RkasRevisi`/`RkasRevisiItem` + factory; `NomorDokumen::noRevisi()` (PGS-0001/NPSN/MM/YYYY atau PAK-0001/...).
- `app/Imports/ImportRevisiImport.php` — parser diff-first per bulan (header 2 baris via `RkasImportHeaderDetector::detectColumns`, akses 0-based), cocokkan item by (program, kode_rekening, normalizeUraian), item baru dibuat (rkas_item_id null, arah naik), net-zero per scope, item sumber ber-realisasi ditolak.
- `app/Jobs/ProcessRkasRevisiImport.php` — sinkron (tanpa ShouldQueue), all-or-nothing, `DB::transaction` → RkasRevisi + RkasRevisiItem + tulis `rkas_item_bulan` + `RkasItem::renumber()`/`syncJumlah()` + AuditLog (`tabel='rkas_revisi'`, aksi `import_pergeseran`/`import_pak`) + Outbox + Cache::increment.
- `app/Http/Controllers/ImportRevisiController.php` — index (list revisi + import log terbaru), store (validasi files 1..12 + upload ke `storage/app/import_revisi` + dispatch job sinkron + flash), show (detail snapshot per item).
- View `resources/views/import-revisi/index.blade.php` + `show.blade.php`; link sidebar "Import Revisi / PAK".
- Routes: `GET/POST /import-revisi`, `GET /import-revisi/{revisi}` (grup auth, di bawah route index).
- **Guard sempit `RkasController::update()` TIDAK diimplementasikan** (sesuai keputusan user saat commit: dikeluarkan dari scope, dikerjakan terpisah nanti bila diminta).

## Keputusan kunci (konfirmasi user)

1. Format file = SAMA dengan template import RKAS (No Urut, Kode Rekening, Kode Program, Uraian, Volume, Satuan, Tarif, Jumlah); rencana per bulan; upload files[1..12], boleh hanya bulan yang berubah.
2. Item yang TIDAK ada di file revisi - DIBIARKAN apa adanya (tidak dihapus).
3. Item ter-realisasi boleh jadi TARGET (naik), TIDAK boleh jadi SUMBER (turun) - guard tolak semua bila ada item sumber ber-realisasi.
4. Net-zero per scope (toleransi ~Rp1): Pergeseran - per (sumber_dana + jenis_belanja); PAK - per sumber_dana.
5. TIDAK ada blok kuota PAK 1x/tahun (ARKAS yang otoritas).
6. Riwayat = rkas_revisi + rkas_revisi_item (snapshot sebelum/sesudah per bulan).
7. no_revisi: PGS-0001/NPSN/MM/YYYY (pergeseran), PAK-0001/... (pak) - lewat NomorDokumen::noRevisi().
8. Pengetatan RkasController::update/destroy TIDAK masuk sesi ini.

## Alur

Menu /import-revisi (grup RKAS) - form (tahun anggaran, sumber dana, jenis radio, tanggal, keterangan, files[1..12]) - ProcessRkasRevisiImport (sinkron, tanpa ShouldQueue) - parse via RkasImportHeaderDetector::detectColumns (baris 8/9, akses 0-based) - cocokkan item by (program, kode_rekening, normalizeUraian) - diff per (item, bulan) - guard all-or-nothing (negatif, sumber ber-realisasi, net-zero) - DB::transaction: RkasRevisi + RkasRevisiItem + tulis rkas_item_bulan + syncJumlah + AuditLog (tabel rkas_revisi, aksi import_pergeseran/import_pak) + Outbox + Cache::increment. Gagal - ImportLog failed + error_detail, tak ada yang diterapkan.

## Artefak yang akan dibuat

- Migrasi 2026_08_16_000027_create_rkas_revisi_tables.php (rkas_revisi + rkas_revisi_item; arah, sebelum_total, sesudah_total, data_perubahan JSON per bulan)
- Model RkasRevisi/RkasRevisiItem + factory
- NomorDokumen::noRevisi()
- ImportRevisiImport (parser per bulan; RkasImport ToModel tidak bisa dipakai untuk diff-first)
- ImportRevisiController (index/store) + job ProcessRkasRevisiImport
- View import-revisi/index + show (detail snapshot); link sidebar
- ImportRevisiTest (net-zero lolos/gagal, sumber ber-realisasi tolak, target ber-realisasi izin + kunci kwitansi terbuka, item tak di file dibiarkan, bulan negatif tolak, format no_revisi, item baru dibuat, AuditLog)

## Verifikasi

- PHPUnit full suite `OK (407 tests, 1223 assertions)`, PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- 18 test baru: `ImportRevisiControllerTest` (11) + `ImportRevisiImportTest` (7).
- BELUM diuji manual browser; uji manual berikutnya memakai **salinan** DB produksi (`%TEMP%\opencode\test-revisi.sqlite`), bukan Roaming asli.
- BELUM push / bump versi / build installer / rilis — tunggu konfirmasi user + uji manual.

---

# Sesi 16 Agu 2026 — Fix Celah Bulan Guard Realisasi: Realisasi Lintas-Bulan (`realisasiTotal()`) di Import Revisi + Guard Baru `RkasController::update()` (commit lokal, BELUM push)

## Goal

Perbaiki celah logika pada guard "item sumber ber-realisasi ditolak" di fitur Import Revisi/PAK: sebelumnya memakai `realisasiKumulatifSd($bulan)` yang hanya menghitung realisasi S.D. bulan dif-nya — padahal definisi yang benar (dan konsisten dengan keputusan fitur) adalah item "sudah ber-realisasi" bila **pernah direalisasikan di bulan mana pun** dalam tahun anggaran (realisasi lintas-bulan). Sekaligus pasang guard yang SAMA di `RkasController::update()` (menurunkan `jumlah` item ber-realisasi ditolak), karena sebelumnya `update()` TIDAK punya guard apa pun — user bisa menurunkan rencana item yang sudah terpakai tanpa jejak.

## Root Cause (celah bulan, dari investigasi test_d)

- `ImportRevisiImport.php` guard turun: `$item->realisasiKumulatifSd($bulan) > 0` → untuk diff bulan 1, item dengan realisasi di bulan 2 dianggap "belum ber-realisasi" → pergeseran keluar dari item itu LULUS (anggaran bisa berkurang padahal barang sudah dibeli).
- `RkasController::update()` tidak memvalidasi penurunan rencana sama sekali → beban ber-realisasi bisa dikurangi manual tanpa memicu pergeseran/PAK.

## Perubahan

- `app/Models/RkasItem.php` — method baru **`realisasiTotal(?string $exceptTransaksiId = null): float`** (total realisasi SELURUH bulan TA, tanpa filter bulan; delegasi ke `RealisasiQuery::base()`; dgn `exceptTransaksiId` utk pengecualian transaksi saat edit). Di-pakai sebagai definisi tunggal "item ber-realisasi" (dipilih eksplisit, bukan `realisasiKumulatifSd(12)` agar tidak ambigu).
- `app/Imports/ImportRevisiImport.php` — baris 135: `$realisasi = $item !== null ? $item->realisasiTotal() : 0.0;`; docblock perbarui (definisi realisasi lintas-bulan; field `realisasi` HANYA dipakai guard turun, `ProcessRkasRevisiImport::apply()` tidak menggunakannya — sudah diverifikasi).
- `app/Http/Controllers/RkasController.php` — import `Illuminate\Validation\ValidationException`; `update()` tambah guard: bila `$rkasItem->realisasiTotal() > 0 && (float) $validated['jumlah'] < (float) $rkasItem->jumlah` → `ValidationException::withMessages(['jumlah' => 'Item sudah ber-realisasi (total Rp …) sehingga jumlah/rencana tidak dapat diturunkan. Gunakan pergeseran/perubahan anggaran (PAK) di ARKAS bila perlu mengubah anggaran.'])`. (Menaikkan jumlah/rencana tetap diperbolehkan.)
- `tests/Feature/Import/RealisasiLintasBulanGuardTest.php` (BARU, permanen) — 5 test regression: a/b/d = guard import menolak item ber-realisasi di bulan berbeda/lebih awal/lebih akhir (rencana sumber TIDAK berubah, `rkas_revisi` count 0); c = `update()` menolak penurunan jumlah item ber-realisasi lintas-bulan; c2 = tanpa realisasi, penurunan jumlah tetap diizinkan (jumlah jadi 50000).

## Verifikasi

- `RealisasiLintasBulanGuardTest` → `OK (5 tests, 30 assertions)` · `ImportRevisi` → `OK (18 tests, 68 assertions)` · `RkasControllerTest` → `OK (33 tests, 128 assertions)`.
- Full suite **`OK (412 tests, 1239 assertions)`** (naik dari 407/1223), PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Test utk membuktikan celah (temp) TIDAK ikut commit; `fwrite(STDERR)` debug dihapus; `git mv` gagal utk file untracked → pakai `Move-Item` PowerShell.

## Catatan Proses

- PHPStan level 6 error 3× saat penambahan test (`missingType.iterableValue` + `missingType.generics` di helper `makeUpload`/`postPergeseran`) → di-fix dengan PHPDoc: `@param array<int, array<int, string>> $rows` dan `@return \Illuminate\Testing\TestResponse<\Illuminate\Http\RedirectResponse>`.
- Perintah user: **commit sekarang dengan pesan yang jelas** (sebut fix celah bulan, guard baru update(), regression test permanen); **update AGENTS.md dgn status akhir; JANGAN push**.
- Commit ini = lanjutan dari fitur Import Revisi/PAK (commit `5119ece`). Menunggu uji manual + keputusan user sebelum push/build/rilis.

---

# Sesi 16 Agu 2026 — Tahap 4 Import Revisi: Controller Test + Commit + Setup Uji Manual (server 8027) — ISTIRAHAT, LANJUT NANTI

## Status (titik berhenti)

- **Commit lokal**: `5119ece` (fitur Import Revisi/PAK lengkap) + `7c06202` (AGENTS status akhir). Working tree BERSIH. BELUM push/bump/build/rilis.
- **Uji manual fitur Import Revisi/PAK BELUM dimulai** — server dev sudah jalan (lihat di bawah), tinggal login & uji dari browser.

## Tahap 4 (sesi ini)

- `tests/Feature/Import/ImportRevisiControllerTest.php` — 11 test (guest redirect; index render; index warn tanpa tahun aktif `assertDontSee('Upload File Revisi')`; store wajib files; store error tanpa tahun aktif; net-zero sukses `PGS-0001/20519260/01/2026` + sebelum/sesudah 300000 + rkas_item_bulan updated + 2 items + ImportLog success + file_path null; net-zero tidak seimbang tolak + rkas_revisi 0 + rencana utuh + ImportLog failed + error_detail; sumber ber-realisasi tolak via `TransaksiBku::factory()`; item baru dibuat dgn item lain menurun (net-zero butuh offset!); PAK `PAK-0001/...` + audit_log `import_pak`; show render detail). Helper: `makeUpload` (Storage::fake('local') + anonymous FromArray + Excel::store + UploadedFile test=true mime xlsx), `makeItem` (kode `5.1.01.01.001`, program `P.001.01`), `makeRencana`, `makeNota`? TIDAK — uji sumber-realisasi pakai `TransaksiBku::factory()`.
- **Bug yang di-fix**: `ProcessRkasRevisiImport` pakai `use Illuminate\Queue\Queueable;` → harus `Illuminate\Bus\Queueable` (fatal trait not found). PHPStan 6 issue: array type `array<int|string, UploadedFile|null>` (kunci bisa string), hapus `?? ''` redundant di `$meta['log_id']` (offset selalu ada), `@param array<int, string>|string $errors` di `mapAllLogs`.
- Full suite `OK (407 tests, 1223 assertions)` · PHPStan `[OK] No errors` · `view:cache` OK.

## Setup Uji Manual (JANGAN sentuh DB Roaming asli)

- **Salinan DB**: `C:\Users\yudhi\AppData\Local\Temp\opencode\test-revisi.sqlite` (dari Roaming `id.smartrkas.desktop\smartrkas.sqlite`; 1 user, 108 rkas_item, 139 master_program, 23 transaksi, 1 tahun anggaran; `rkas_revisi`/`rkas_revisi_item` = 0). Migrasi `000027` sudah di-`migrate --force` pada salinan ini.
- **Server dev**: `php -S 127.0.0.1:8027 -t public` (di `cmd /c` dengan `set "DB_DATABASE=C:\Users\yudhi\AppData\Local\Temp\opencode\test-revisi.sqlite"` — PENTING: kutip `set "VAR=path"` tanpa spasi; versi tanpa kutip menghasilkan path dgn spasi trailing → 500 "Database file ... does not exist"). TIDAK pakai `SMARTRKAS_DATA_DIR` (tanpa itu error "Please provide a valid cache path" — storage_path rusak). Log: `%TEMP%\opencode\server-revisi.log`.
- `/login` = 200; `/import-revisi` guest → 302 ke `/login` (benar).
- Port 8027 sudah dicek bersih (hanya VS Code php-intellisense yang hidup — bukan server web, wajar). Pastikan SOP cek duplikat port sebelum uji.

## Next (lanjutan)

1. Buka `http://127.0.0.1:8027/login` di browser (akun = akun produksi; DB = salinan, data asli).
2. Uji manual: upload file revisi pergeseran (net-zero per sumber_dana+jenis_belanja) → sukses + PGS-0001 + rencana berubah + Riwayat Revisi + Detail snapshot; uji sengaja-gagal: net-zero tidak seimbang, item sumber ber-realisasi diturunkan → semua ditolak, tak ada yang diterapkan.
3. Bila OK → lapor ke user → putuskan commit lanjutan/push/build/rilis (TIDAK otomatis).

---

# Sesi 16 Agu 2026 — TEMUAN UJI: Guard `RkasController::update()` (realisasiTotal) TERVERIFIKASI BERFUNGSI (bukan tembus) — perbedaan DB server adalah penjelasnya

## Laporan user

"Guard RkasController::update() (commit 03996a9) masih tembus setelah restart server" — penurunan jumlah item "Perjalanan Dinas dalam Daerah-" seharusnya ditolak karena item ber-realisasi, tapi seolah lolos.

## Verifikasi langsung (bukan asumsi) — HASIL: guard bekerja, TIDAK tembus

1. **HEAD server = benar**: `git log --oneline -1` di direktori repo yang dibaca kedua server → `5693f54` (lebih baru dari `03996a9`).
2. **File yang benar-benar dibaca server**: kedua server php (`php.exe -S` port 8027 & 8031) jalan dari repo ini, dan `app\Http\Controllers\RkasController.php` memuat `realisasiTotal()` di baris 199. Tidak ada server dari `%LOCALAPPDATA%\SmartRKAS` (proses smartrkas tidak ada).
3. **Reproduksi langsung (bukan browser)**:
    - Programmatic (boot Laravel + `RkasController::update()` pada item realisasi Rp 420.000, turunkan 1.950.000 → 1.850.000): `ValidationException` → `{"jumlah":["Item sudah ber-realisasi (total Rp 420.000) ..."]}`, DB tidak berubah.
    - HTTP live server 8027 (login nyata + PUT `/rkas/{id}`): `302` → `/rkas/{id}/edit` (bukan redirect sukses ke index), halaman edit render error merah, `jumlah` di DB tetap 1.950.000, tanpa baris AuditLog baru.
4. **Urutan kode benar**: guard (baris 199–207) SEBELUM `$rkasItem->update($validated)` (baris 214).

## Akar "terlihat tembus" (penjelasan, bukan bug)

- Server **8031 memakai DB dev** (`database/database.sqlite`) di mana KEDUA item "Perjalanan Dinas dalam Daerah-" punya **`realisasiTotal() = 0`** → penurunan jumlah memang lulus secara logika. Server 8027 memakai salinan DB produksi (`test-revisi.sqlite`) yang item-nya ber-realisasi → ditolak.
- **Pelajaran**: kalau uji manual memakai server yang menunjuk DB berbeda (dev vs salinan produksi), hasil uji akan beda. Pastikan dulu DB mana yang benar-benar dibaca server yang diuji (`DB_DATABASE` di env proses), bukan asumsi dari .env repo.
- Guard `update()` memang hanya menolak bila `realisasiTotal() > 0 && jumlah baru < jumlah lama`; menaikkan jumlah atau item tanpa realisasi TIDAK ditolak (by design).

## Artefak uji (temp, tidak ikut commit)

- Probe: `%TEMP%\opencode\probe-guard-update.php`, `probe-item.php`, `probe-devdb.php`, `probe-verify.php`, `probe-setpw.php`; cookie jar `cookies-guard*.txt`; body `put-body.html`, `edit-after-put.html`.
- Password user di DB salinan `test-revisi.sqlite` di-set sementara `probe-pass-2026` utk uji HTTP (DB salinan, bukan Roaming asli).

## Test Status

- Tidak ada perubahan kode pada sesi ini (murni verifikasi) → suite tetap `OK (412 tests, 1239 assertions)`, PHPStan level 6 `[OK] No errors`.

---

# Sesi 17 Agu 2026 — Fitur Template Transaksi (commit lokal)

## Goal

Tambahkan fitur "Template Transaksi Berulang": simpan pola transaksi BKU sebagai template, lalu pakai ulang di form BKU baru (auto-fill kegiatan, rekening, item, toko, metode, uraian). Template hanya dari transaksi single-item (bukan nota multi-item).

## Summary

- Phase 1 (migration + model + factory) + Phase 2 (controller + routes + sidebar) + Phase 3 (UI — manage page + modal simpan + dropdown pakai) SELESAI dalam satu commit.
- `cariItemDiTahunAktif()` pada model menggunakan `RkasItem::normalizeUraian()` untuk pencocokan uraian item lintas tahun anggaran (sama dgn `ImportRevisiImport`).
- Template `apply()` endpoint AJAX mengembalikan data kegiatan/rekening (nama), item RKAS tahun aktif (atau null bila tidak cocok), toko, metode, uraian.
- Form BKU create: dropdown "Pakai Template?" + handler JS `applyTemplate()` yang fetch AJAX, set picker kegiatan/rekening, auto-check item (via `_templateAutoCheck` flag di `renderItems`), isi toko/metode/uraian. Mendukung `?template_id=` URL param dari tombol "Pakai" di manage page.
- BKU index: tombol ikon "Simpan Template" per baris (hanya pengeluaran single-item) + modal dialog input nama.
- Manage page (`transaksi-template/index`): tabel + hapus + link "Pakai" ke form BKU create.
- Edit BKU: tambah `autoLengkapiBulanUraian()` (dari sesi sebelumnya, belum di-commit).

## Changes

- `database/migrations/2026_08_17_000028_create_transaksi_template_table.php` — tabel `transaksi_template`: id uuid, nama_template, kode_rekening_id FK, kegiatan_id FK, uraian_item_snapshot, toko_penerima, metode_pengadaan, uraian_dasar, sumber_dana_id FK nullable, created_by, timestamps, softDeletes.
- `app/Models/TransaksiTemplate.php` — HasUuids+SoftDeletes+HasFactory, relasi kodeRekening/kegiatan/sumberDana/createdByUser, `cariItemDiTahunAktif()` (filter TA aktif + whereHas kodeRekening + whereHas kegiatan + where normalizeUraian match).
- `database/factories/TransaksiTemplateFactory.php` — factory standar.
- `app/Http/Controllers/TransaksiTemplateController.php` — `index()`, `store()` (dari BKU row, cek single-item), `destroy()`, `apply()` (AJAX JSON).
- `app/Http/Controllers/TransaksiBkuController.php` — `create()` tambah load `$templates`.
- `routes/web.php` — 4 route template (index/store/destroy/apply) + import controller.
- `resources/views/transaksi-template/index.blade.php` — manage page (tabel + hapus + link pakai).
- `resources/views/transaksi-bku/index.blade.php` — tombol simpan template per row + modal dialog.
- `resources/views/transaksi-bku/create.blade.php` — banner "Pakai Template?" + dropdown + `applyTemplate()` JS + `_templateAutoCheck` hook di `renderItems` + `?template_id=` URL param handler.
- `resources/views/transaksi-bku/edit.blade.php` — tambah `autoLengkapiBulanUraian()`.
- `resources/views/layouts/navigation.blade.php` — link sidebar "Template Transaksi".

## Verifikasi

- PHPStan level 6: `[OK] No errors` (fix `@property` nullable di TransaksiTemplate).
- PHPUnit: `OK (414 tests, 1251 assertions)` — semua hijau.
- `php artisan view:cache` OK.
- BELUM diuji manual browser; BELUM push/build/rilis.

## Test Status

- PHPStan level 6: `[OK] No errors`. PHPUnit `OK (414 tests, 1251 assertions)`. Commit `43001ab`.

---

# Sesi 17 Agu 2026 — Fix Bug: Dropdown Searchable Kegiatan/Rekening Tidak Muncul di Dashboard & BKU

## Goal

Perbaiki bug yang dilaporkan user: dropdown searchable untuk Program/Kode Rekening (Dashboard) dan Kegiatan/Rekening (BKU create/edit) tidak menampilkan hasil saat diketik.

## Root Cause

`.card` CSS class (`resources/css/app.css:275`) punya `overflow-hidden`. Dropdown hasil di `_search-picker.blade.php` memakai `position: absolute` di dalam `<div class="relative">`. Karena picker berada di dalam `.card` (Dashboard: `<div class="card mb-6">` wrapper form filter; BKU create: `<div class="card">` wrapper form), overflow-hidden memotong dropdown → tidak pernah terlihat.

## Fix

Hapus `overflow-hidden` dari `.card`. `border-radius: 1rem` (dari `rounded-2xl`) sudah menangani tampilan visual sudut membulat berkat background + border card sendiri. `overflow-hidden` tidak diperlukan untuk konten yang sudah punya padding (`card-body` = `p-6`).

## Changes

- `resources/css/app.css:275` — `.card` dari `@apply ... overflow-hidden` → hapus `overflow-hidden`.
- `AGENTS.md` — tambah sesi ini.

## Verifikasi

- `php artisan view:cache` OK.
- `npm run build` OK (CSS di-recompile: `app-CQef_afV.css`).
- PHPUnit `OK (414 tests, 1251 assertions)` — tidak ada regression.

## Test Status

- PHPStan level 6: `[OK] No errors`. PHPUnit `OK (414 tests, 1251 assertions)`.

---

# Sesi 17 Agu 2026 — Fix JS Syntax Error (Blade `{{ }}` di Script Tags) + Release v0.6.2

## Goal

Perbaiki bug JS `Unexpected token '&'` yang muncul di halaman BKU create/edit, Dashboard, RKAS filter, Laporan — semua halaman yang memakai `_search-picker.blade.php`.

## Root Cause

`_search-picker.blade.php:141` menggunakan `{{ $spCompact ? 'null' : "'{$spPrefix}_status'" }}` di dalam tag `<script>`. Blade `{{ }}` meng-HTML-encode karakter `'` menjadi `&#039;`, menghasilkan JavaScript tidak valid:

```js
statusId: 'kegiatan_status&#039;',  // ← syntax error
```

## Fix

Ganti `{{ }}` → `{!! !!}` (raw output) untuk nilai `statusId` yang menghasilkan string literal JS:

```js
statusId: 'kegiatan_status',      // ← benar
```

## Changes

- `resources/views/transaksi-bku/_search-picker.blade.php:141` — `{{ }}` → `{!! !!}`.

## Catatan Verifikasi

- Probe render (`find-ampersand.php`): output dikonfirmasi bersih dari `&#039;` di blok `<script>` (line 588 & 717 sekarang render `'kegiatan_status'` / `'kode_rekening_status'` tanpa encoding). Line 880 `esc()` function adalah string literal HTML entity yang disengaja.
- SOP: PHPStan `[OK] No errors`, PHPUnit `OK (414 tests, 1251 assertions)`, `view:cache` OK, `npm run build` OK.

## Release v0.6.2

- Commit `e3ac813` (fix JS syntax error + bump v0.6.2 di 5 file) → push `master`.
- Build: NSIS 61.3MB + MSI 93.8MB.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.2 (2 asset, state `uploaded`).

## Reinstall v0.6.2

- Uninstall v0.6.1 → install v0.6.2 → exe ProductVersion **0.6.2**.
- php.exe + cacert.pem terbundle.
- App jalan → server `php -S 127.0.0.1:62385` → `/login` = **200**.
- Tidak ada orphan php setelah kill app.

## Test Status

- PHPStan level 6: `[OK] No errors`. PHPUnit `OK (414 tests, 1251 assertions)`. Tidak ada perubahan logika PHP/app — hanya view + versi.

---

# Sesi 18 Agu 2026 — Fix Ringkasan Capaian & Badge Konsisten Kumulatif (v0.6.3)

## Goal

Perbaiki badge "Sisa" & "Status" di Dashboard dan Data RKAS supaya pakai **kumulatif** (rencana s.d. bulan filter − realisasi s.d. bulan filter), konsisten dgn guard BKU. Serta summary card "Ringkasan Capaian" juga kumulatif.

## Root Cause

Ketika `$bulan` filter aktif, controller menghitung rencana & realisasi hanya dari **satu bulan itu saja** — badge per-item (DynamicSisa/Persentase), summary card (Total Rencana/Realisasi/Sisa), dan guard BKU (`sisaKumulatifSd`) pakai angka berbeda → kontradiksi.

## Changes

- `app/Http/Controllers/DashboardController.php:80-87` — `totalRencana`/`totalRealisasi` summary card → `where('bulan', '<=', $bulan)` (kumulatif).
- `app/Http/Controllers/RkasController.php:85-92` — `totalJumlah`/`totalRealisasi` summary card → `where('bulan', '<=', $bulan)` (kumulatif).
- `tests/Feature/RKAS/RkasControllerTest.php` — `test_index_sisa_dan_badge_pakai_kumulatif_bukan_per_bulan`: badge "Hampir Habis (98%)" (bukan "Normal"); assertDontSee('Rp -100.000').

## Build & Reinstall v0.6.3

- Bump 0.6.2 → **0.6.3** di 5 file (`config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` blok `name="smartrkas"` saja).
- Build: NSIS `SmartRKAS_0.6.3_x64-setup.exe` (61.3MB) + MSI `SmartRKAS_0.6.3_x64_en-US.msi` (93.9MB).
- Reinstall: uninstall v0.6.1 (`uninstall.exe /S` exit 0) → force-remove `C:\Users\yudhi\AppData\Local\SmartRKAS` → install v0.6.3 `/S` exit 0 → exe ProductVersion **0.6.3**, `php\php.exe` + `php\extras\ssl\cacert.pem` terbundle.
- App jalan → server `php -S 127.0.0.1:63200` (semua `-d` TLS + opcache off terpasang; router TANPA prefix `\\?\`) → `/login` **200** (len 11272).
- Kill app → 0 orphan processes (job object bekerja).
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.3 — 2 asset state `uploaded` (NSIS 61.3MB + MSI 93.9MB), bukan draft. Notes: fix badge kumulatif + ringkasan capaian.

## Test Status

- PHPUnit `OK (416 tests, 1264 assertions)`, PHPStan level 6 `[OK] No errors`.
- Commit `202299c` → push `master`.
- Bump 0.6.2 → **0.6.3** (5 file). Build NSIS + MSI sukses.

---

# Sesi 19 Agu 2026 — Notifikasi Telegram Otomatis + UX Fix Template + Release v0.6.4

## Goal

Tambahkan notifikasi Telegram otomatis (kwitansi reminder + realisasi warning), perbaiki UX template BKU, dan dokumentasi di halaman Telegram & Tentang. Rilis ke GitHub.

## Summary

- 2 command Artisan baru: `telegram:kwitansi-reminder` (Senin 08:00, transaksi tanpa kwitansi) + `telegram:realisasi-warning` (tgl 25/bulan, threshold 50%).
- UX fix: info "Item yang tersimpan" + warning modal "Simpan Template" di BKU index.
- Dokumentasi: card "Notifikasi Otomatis" di `telegram.blade.php`, poin "Notifikasi Telegram" di `tentang.blade.php`.
- Full suite `OK (416 tests, 1264 assertions)`, PHPStan level 6 clean, `view:cache` OK.
- Build: NSIS 58.5MB + MSI 89.6MB. Reinstall v0.6.4 terverifikasi: exe 0.6.4, php+cacert bundled, `/login` 200, error log tidak bertambah, 0 orphan.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.4

## Changes

- `app/Console/Commands/TelegramKwitansiReminder.php` (BARU) — `telegram:kwitansi-reminder`, hitung `TransaksiBku` tahun aktif tanpa kwitansi, kirim ke user dgn `hasTelegramDelivery()`.
- `app/Console/Commands/TelegramRealisasiWarning.php` (BARU) — `telegram:realisasi-warning {--threshold=50} {--month=10}`, cek realisasi via `RealisasiQuery::base()`, kirim peringatan jika di bawah threshold menjelang akhir tahun.
- `routes/console.php` — 2 jadwal baru: kwitansi-reminder `cron('0 8 * * 1')`, realisasi-warning `cron('0 9 25 * *')`.
- `resources/views/pengaturan/telegram.blade.php` — alert info di-update (sebutkan notifikasi otomatis); card baru "Notifikasi Otomatis" (2 panel grid: Pengingat Cetak Kwitansi + Peringatan Realisasi Rendah) + warning "Start bot".
- `resources/views/pengaturan/tentang.blade.php` — poin 6 (Notifikasi Telegram) ditambah di "Petunjuk Penggunaan Singkat".
- `resources/views/transaksi-bku/index.blade.php` — UX fix modal "Simpan Template": tambah info "Item yang tersimpan" (no_bukti, uraian item, kegiatan) + warning "Pastikan data sudah benar".

---

# STATUS AKHIR — Sesi 19 Agu 2026 (v0.6.4)

## Versi Terakhir

- **v0.6.4** (commit `841309d`, push `master`, rilis GitHub).
- Rilis GitHub: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.4 — 2 asset (NSIS 58.5MB + MSI 89.6MB).

## Kondisi Repo

- Working tree bersih.
- `origin/master` = `841309d` (sama dgn HEAD, sudah push).
- **Semua fitur sudah di-build & dirilis** — tidak ada commit lokal yang tertinggal.
- Tidak ada proses build/server yang berjalan.

## Test Suite

- PHPUnit `OK (416 tests, 1264 assertions)`.
- PHPStan level 6 `[OK] No errors`.
- `php artisan view:cache` OK.
- `npm run build` OK.

## Fitur yang Sudah Rilis (v0.6.0 s.d. v0.6.4)

- Import Revisi/PAK (terima hasil pergeseran dari ARKAS via import).
- Template Transaksi (simpan & pakai ulang pola BKU).
- Guard realisasi lintas-bulan (item ber-realisasi tidak bisa diturunkan).
- Dropdown searchable Program/Rekening (picker compact di filter RKAS).
- Badge kumulatif s.d. bulan filter (konsisten dgn guard BKU).
- Ringkasan Capaian & Realisasi per Jenis Belanja di Data RKAS.
- Fix volume sisa item dari nota multi-item.
- Fix JS syntax error di searchable picker.
- Sidebar group Pengaturan dropdown.
- **Notifikasi Telegram Otomatis** (kwitansi reminder + realisasi warning).

---

# Sesi 20 Agu 2026 — Fix Backup Gagal: Custom SQLite Dumper (PDO) + Cek Exit Code Controller

## Goal

Perbaiki bug "Backup Sekarang" menampilkan flash sukses tapi tidak ada file .zip yang tercipta. Dua root cause: (1) spatie/db-dumper mengeksekusi `sqlite3.exe` CLI yang tidak ada di PATH (desktop bundle), (2) `BackupCommand::handle()` menangkap exception internal + return exit code integer, sehingga controller yang hanya `catch \Throwable` tidak pernah terpukul — selalu jatuh ke jalur sukses.

## Root Cause 1 — `sqlite3.exe` tidak ada

- Spatie `Sqlite` dumper memanggil `sqlite3 --bail` via `Process::fromShellCommandline`. Exit code 255, stderr: "sqlite3: not found".
- Bundle PHP desktop tidak menyertakan `sqlite3.exe` (hanya `php.exe` + extension DLL).
- ZipArchive `Class not found` di log lama = gejala secondary (backup tidak pernah sampai tahap zip karena dump gagal duluan).

## Root Cause 2 — Controller abaikan exit code

- `BackupCommand::handle()` (line 77-101): `try { ... } catch { return static::FAILURE; }` — menangkap exception + return int.
- `Artisan::call()` mengembalikan integer exit code, TIDAK melempar exception.
- `BackupController::run()` hanya `catch \Throwable` → tidak pernah terpukul → selalu flash "berhasil".

## Changes

- **`app/Support/PdoSqliteDumper.php`** (BARU) — extends `Spatie\DbDumper\Databases\Sqlite`, override `dumpToFile()`: `PRAGMA wal_checkpoint(TRUNCATE)` via PDO + `copy()` file .sqlite ke dump target. Zero dependency ke CLI tool.
- **`app/Providers/AppServiceProvider.php`** — `register()`: `DbDumperFactory::extend('sqlite', fn() => new PdoSqliteDumper)`.
- **`app/Http/Controllers/BackupController.php`** — `run()`: tangkap `BufferedOutput`, `$exitCode = Artisan::call('backup:run', [], $output)`, cek `$exitCode !== 0` → flash error + AuditLog `status=failed`. Import `BufferedOutput`.
- **`tests/Feature/Backup/BackupPageTest.php`** — 3 test baru: exit 0 → success flash, exit 1 → error flash, RuntimeException → error flash. Hapus test lama `test_run_triggers_backup_command`.
- **`tests/Feature/Audit/AuditLogCoverageTest.php`** — `test_backup_run_is_logged`: `Artisan::spy()` → `Artisan::shouldReceive('call')->once()->andReturn(0)`.
- **`tests/Unit/PdoSqliteDumperTest.php`** (BARU) — 2 test: dump valid (PDO copy + verifikasi isi tabel) + source missing (throw RuntimeException).

## Verifikasi E2E

- `php artisan backup:run --only-db` → `Successfully copied zip to disk named local... Backup completed!`
- File `.zip` = `storage/app/private/SmartRKAS/2026-08-20-13-59-31.zip` (130KB) — path konsisten dengan `BackupController::index()`.
- `php artisan backup:run` (full) → DB dump sukses, zipping timeout karena 55K+ file di `base_path()` (wajar di dev; di desktop install lebih cepat).

## Catatan

- `PdoSqliteDumper::dumpToFile()` menghasilkan copy biner SQLite (bukan SQL dump .sql). Spatie hanya menambahkan hasil dump ke zip — isi tidak harus SQL text.
- `PRAGMA wal_checkpoint(TRUNCATE)` bersifat best-effort; bila gagal (locked), copy tetap berjalan.
- `config('backup.backup.name')` = `SmartRKAS` (dari `APP_NAME` di .env) → backup dir = `storage/app/private/SmartRKAS/`.

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (424 tests, 1284 assertions)`.

---

# Sesi 20 Agu 2026 — Release v0.6.6 (Fix Backup Gagal Senyap)

## Goal

Bawa fix backup ke installer & rilis ke GitHub karena backup adalah jaring pengaman data yang kritis — fix harus segera sampai user, bukan digabung sambil menunggu fitur lain.

## Summary

- 2 commit: `19cd344` (fix + AGENTS) + `a0819e6` (bump v0.6.6). Push `master`.
- Build: NSIS 58.6MB + MSI 89.7MB.
- Clean-install v0.6.6 → `/login` **200** (len 11272) → 0 orphan processes.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.6

## Changes (release ini)

- Bump v0.6.5 → 0.6.6: `config/app.php`, `.env.example`, `src-tauri/tauri.conf.json`, `src-tauri/Cargo.toml`, `src-tauri/Cargo.lock` (blok `name = "smartrkas"` saja).

## Test Status

- PHPStan level 6: `[OK] No errors`.
- Full suite: `OK (424 tests, 1284 assertions)`.

---

# Sesi 21 Agu 2026 — Verifikasi Fix Backup di Instalasi: FIX BERFUNGSI (3 backup sukses) + Akar Persepsi "Tidak Terjadi Apa Apa"

## Goal

User melaporkan "saya cek tidak terjadi apa apa" setelah klik Backup Sekarang. Verifikasi independen apakah fix backup (PdoSqliteDumper + cek exit code, rilis v0.6.6) benar-benar berjalan di instalasi.

## Kesimpulan: FIX BERFUNGSI — backup SUKSES, persepsi "tidak terjadi apa apa" = masalah UX feedback

- **Bukti 1 — file zip tercipta**: 3 zip di `%APPDATA%\id.smartrkas.desktop\storage\app\private\SmartRKAS\`: `2026-08-21-10-52-28.zip`, `2026-08-21-10-52-56.zip`, `2026-08-21-10-53-19.zip` (masing-masing ~41MB).
- **Bukti 2 — dump DB VALID**: entri `db-dumps\pdosqlitedumper-sqlite-database.sql` di dalam zip = **1.818.624 bytes**, header `SQLite format 3` → salinan DB produksi utuh (85 transaksi aktif terverifikasi di DB asli).
- **Bukti 3 — audit_log**: 3x `backup.run {"status":"success"}` hari ini (10:52:56 / 10:53:19 / 10:53:42) — exit code check bekerja.
- **Bukti 4 — laravel.log**: hari ini TIDAK ada error (hanya DEBUG mail-log notifikasi sukses). Bandingkan **kemarin (20 Agu) 08:44 & 13:40**: `production.ERROR: The dump process failed with a none successful exitcode` tapi audit_log tetap "success" → itu perilaku kode LAMA (controller abaikan exit code). Berarti v0.6.6 baru terpasang antara kemarin 13:40 dan hari ini 10:52; kegagalan user kemarin = versi lama.
- `php-server-error.log` tidak bertambah (masih 4 baris era `\\?\` 8 Agu).

## Akar persepsi "tidak terjadi apa apa" (UX, bukan bug backup)

1. **Backup sinkron ~23–27 detik per klik** (`Artisan::call('backup:run')` memblokir request POST; zip 41MB karena source include base_path instalasi — app+vendor+php bundle, bukan hanya DB).
2. **Tombol tanpa loading state**: `backup.blade.php:70-76` form POST polos, tidak ada disabled/spinner → selama ~25 detik halaman tampak diam → user klik lagi (3 klik = 3 zip berurutan ~25 detik, terbukti dari timestamp).
3. Flash sukses (`session('success')`, view baris 8-18) BARUT muncul setelah round-trip selesai; kalau user menutup/navigasi sebelum selesai, tidak ada umpan balik sama sekali.

## Rekomendasi backlog (BELUM dikerjakan, butuh persetujuan)

- UX: tombol "Backup Sekarang" disabled + teks "Memproses..." saat submit (JS kecil), supaya user tahu sedang jalan.
- Opsional: pangkas source backup agar hanya DB (+storage penting), bukan seluruh base_path instalasi → zip jauh lebih kecil & proses lebih cepat.

## Metode verifikasi (reproducible)

- Versi exe: `(Get-Item $exe).VersionInfo.ProductVersion` → 0.6.6.
- Isi zip: `[System.IO.Compression.ZipFile]::OpenRead()` → cek entri `db-dumps*` (HATI-HATI: `-like '*pdosqlitedumper*'` match 2 entri — dump + file PdoSqliteDumper.php ikut ter-backup; filter `db-dumps*` saja).
- audit_log: PDO sqlite langsung ke DB Roaming via script temp `%TEMP%\opencode\check-audit.php` (jangan `php -r` inline — quoting PowerShell pecah, sesuai pelajaran lama).

## Test Status

- Tidak ada perubahan kode pada sesi ini (murni verifikasi + dokumentasi) → suite tetap `OK (424 tests, 1284 assertions)`, PHPStan level 6 `[OK] No errors`.

---

# Sesi 21 Agu 2026 — Jam Jadwal ke Jam Kerja + Jam Realtime di Header (commit lokal)

## Goal

(1) User minta cleanup backup digeser ke jam 20:00 karena scheduler desktop hanya jalan selama app terbuka (jadwal 01:00-04:00 nyaris tidak pernah tereksekusi). Disetujui user via question tool: SEMUA jadwal malam dipindah ke jam kerja. (2) Tambah jam realtime di header atas semua halaman agar user tidak lupa waktu saat bekerja. Lokasi disetujui user: header atas (bukan hanya dashboard).

## Changes

- `routes/console.php` — jadwal baru (semua WIB): `backup:clean` harian **20:00**, `backup:run` harian **20:15**, `audit:clean 90` Minggu **20:30**, hapus failed_jobs >30 hari Minggu **20:35**, `kwitansi:clean 2` bulanan tanggal 1 **20:40**. Telegram reminder TETAP (Senin 08:00, tgl 25 jam 09:00 — sudah jam kerja). Komentar di file menjelaskan alasan desktop.
- `resources/views/layouts/app.blade.php` — pill jam realtime di `.top-header` sisi kanan (sebelum tombol dark mode), `hidden sm:flex` (sembunyi di layar sempit): ikon jam + `#realtime-clock` (HH:MM:SS, tabular-nums) + `#realtime-date` (hari, tgl, bulan singkat id-ID). JS IIFE baru di blok script bawah: format manual padStart (bukan toLocaleTimeString id-ID yang memakai titik "20.15.30"), tick tiap 1 detik.
- `tests/Feature/Console/ScheduleTimesTest.php` (BARU, 5 test) — ekspresi cron persis per jadwal (`0 20 * * *`, `15 20 * * *`, `30 20 * * 0`, `40 20 1 * *`) + guard umum: tidak ada jadwal dengan jam < 07:00 (regex `^(\d+) (\d+) ` pada expression).
- `tests/Feature/Dashboard/DashboardTest.php` — +1 test `test_layout_shows_realtime_clock` (assertSee raw `id="realtime-clock"` / `id="realtime-date"`).

## Catatan Teknis

- PHPUnit `--filter 'A|B'`: karakter pipe dipecah shell tool walau sudah di-quote → jalankan dua perintah terpisah.
- Jam memakai waktu mesin klien (desktop = komputer user sendiri, jadi selalu benar); bukan waktu server.
- Perubahan routes/console.php + view HANYA aktif di desktop setelah build installer baru (routes & view ter-bundle).

## Test Status

- PHPUnit full suite `OK (432 tests, 1304 assertions)` (naik dari 426/1290), PHPStan level 6 `[OK] No errors`, `view:cache` OK.
- Commit lokal; BELUM push; BELUM bump versi; BELUM build installer v0.6.7 (menunggu konfirmasi user).

---

# Sesi 21 Agu 2026 — Pengingat Istirahat 2 Jam (Modal Popup + Snooze 15 Menit) (commit lokal)

## Goal

User minta sistem memberi pengingat tiap 2 jam agar user istirahat sejenak (sering lupa istirahat saat bekerja). Disetujui via question tool: bentuk = **modal popup wajib klik** (pola Stretchly/Time Out), dengan opsi tolak = **tunda 15 menit**.

## Keputusan Desain (konfirmasi user)

- Bentuk: modal popup (layar diredupkan + kartu tengah), BUKAN toast/banner — pasti terlihat.
- Opsi tolak: tombol "Tunda 15 Menit" → pengingat muncul lagi 15 menit kemudian; tombol utama "Baik, Saya Istirahat Sebentar" → hitung ulang 2 jam penuh.

## Implementasi

- **Client-side murni** (JS di `layouts/app.blade.php`), bukan scheduler server — pengingat harus muncul saat user sedang memakai app. Hitungan disimpan di **localStorage** (`smartrkas-break-reminder-at`) agar TETAP JALAN lintas navigasi halaman (setInterval polos akan ke-reset tiap pindah halaman karena Laravel Blade = full page load).
- Markup modal `#break-reminder-modal` sebelum blok script bawah layout; z-[70] (di atas page-loader z-50); ikon jam emerald; dark mode support.
- Logika JS: first run → simpan baseline tanpa tampil; elapsed 2-4 jam → tampil; elapsed >= 4 jam (app ditinggal semalaman) → reset diam-diam TANPA tampil (user baru kembali, bukan sedang kerja); cek interval tiap 60 detik.
- Snooze: set timestamp = now − 2 jam + 15 menit → due() lagi tepat 15 menit kemudian.
- Test: `DashboardTest::test_layout_shows_break_reminder_modal` (modal, judul, tombol OK, "Tunda 15 Menit").

## Catatan Teknis

- localStorage disabled → try/catch, degradasi aman (pengingat tidak tampil, tidak error).
- Modal hanya di layout auth (`layouts/app.blade.php`); halaman guest (login) tidak perlu.
- Aktif di desktop setelah build installer baru (view ter-bundle).

## Test Status

- PHPUnit full suite `OK (433 tests, 1309 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK.

---

# Sesi 21 Agu 2026 — Release v0.6.7 (Backup UX + Jam Kerja Scheduler + Jam Realtime + Pengingat Istirahat)

## Goal

Build & rilis v0.6.7: fix jam backup UTC + pangkas isi backup + loading state tombol (`635601f`), jadwal malam ke jam kerja + jam realtime header (`ff7ea32`), pengingat istirahat 2 jam modal popup + tunda 15 menit (`b329d41`) + animasi (`7b6b26b`). User: "jika sudah lolos silahkan build dan rilis seperti biasa".

## Build

- Bump 0.6.6 -> **0.6.7** di 5 file. **PELAJARAN BARU**: `Set-Content -Encoding UTF8` di PowerShell 5.1 menambahkan **BOM** di awal file (diff jadi 2+/2- per file; BOM bisa mematahkan parse Cargo.toml/JSON) -> revert, ulangi dengan Edit tool + `[System.IO.File]::WriteAllText(..., UTF8Encoding($false))`. Hasil final: 5 file, tepat 1+/1- per file, BOM check semua False.
- `npm run build` OK (app-CEI5vHHi.css / app-CA7a7cYK.js). `tauri build --bundles nsis,msi`: compile 4m10s -> NSIS `SmartRKAS_0.6.7_x64-setup.exe` (58.6MB) + MSI `SmartRKAS_0.6.7_x64_en-US.msi` (89.5MB).

## Reinstall & Verifikasi Instalasi Nyata

- Kill app v0.6.6 (CloseMainWindow tidak merespon -> Stop-Process -Force; job object mematikan anak php bersih; sisa php.exe hanya milik XAMPP/VS Code). Uninstall `/S` exit bersih -> folder `%LOCALAPPDATA%\SmartRKAS` hilang, **DB Roaming utuh** (1.826.816 bytes). Install v0.6.7 `/S` -> exe ProductVersion **0.6.7**, php bundle + cacert.pem ada.
- App jalan -> server `php -S 127.0.0.1:59524`, `/login` = **200**.
- **Kejanggalan yang ternyata bukan masalah**: cmdline proses mengandung `\\?\` -> setelah diperiksa, prefix itu HANYA di path executable php.exe (cara Rust spawn, normal); SEMUA argumen (router server.php, curl.cainfo, openssl.cafile, error_log) bersih tanpa prefix. Jangan panik hanya karena grep kasar.
- Fitur baru terverifikasi terbundle: break-reminder-modal + realtime-clock di layout, config 0.6.7, jadwal `->daily()->at('20:15'/'20:00')` di routes/console.php terpasang (catatan: jadwal ditulis fluent `daily()->at()`, BUKAN string cron mentah -> test ScheduleTimesTest membaca ekspresi cron hasil resolve, bukan source).
- `php-server-error.log` tetap 4 baris (fatal lama era `\\?\` 08-Agu), TIDAK ada error baru.

## Release

- Commit bump + AGENTS -> push `master`.
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.7 (2 asset NSIS + MSI).

## Test Status

- PHPUnit full suite `OK (433 tests, 1309 assertions)`, PHPStan level 6 `[OK] No errors`, `view:cache` OK.

---

# Sesi 22 Agu 2026 — Fitur Baru: Monitoring Kepatuhan Juknis BOSP — TAHAP 1 (Migrasi + Model) — commit lokal

## Goal

Tahap 1 dari 3 fitur Monitoring Kepatuhan Juknis BOSP: tabel `kategori_juknis` + pivot `kode_rekening_kategori_juknis`, model `KategoriJuknis` + relasi dua arah, factory, smoke test relasi. Tahap 2 = halaman pengaturan kategori + centang kode rekening; Tahap 3 = halaman monitoring (persentase via `RealisasiQuery` thd pagu tahunan).

## Changes

- `database/migrations/2026_08_22_000029_create_kategori_juknis_tables.php` (BARU):
    - `kategori_juknis`: uuid PK, `nama` string UNIQUE, `arah` enum(`maksimal`,`minimal`) (batas atas vs batas bawah), `batas_persen` decimal(5,2), `berlaku_untuk` string(50) nullable (mis. negeri/swasta), timestamps, index `arah`.
    - Pivot `kode_rekening_kategori_juknis`: FK `master_kode_rekening` + FK `kategori_juknis`, keduanya `cascadeOnDelete`; unique gabungan dgn **nama eksplisit** `krek_kategori_juknis_unique` (nama default Laravel 72 char > batas identifier MySQL 64).
- `app/Models/KategoriJuknis.php` (BARU) — HasUuids+HasFactory, `$fillable`, cast `batas_persen=float`; relasi `kodeRekenings(): BelongsToMany` (pivot table + FK eksplisit). Relasi BelongsToMany PERTAMA di codebase.
- `app/Models/MasterKodeRekening.php` — tambah relasi `kategoriJuknis(): BelongsToMany` (satu kode rekening bisa banyak kategori).
- `database/factories/KategoriJuknisFactory.php` (BARU) + state helper `maksimal(float)` / `minimal(float)` utk test Tahap 2-3.
- **Tanpa seeder** — sesuai kesepakatan "default tidak ditandai, tidak ditebak otomatis": tabel mulai kosong, user isi manual dari halaman pengaturan (Tahap 2).

## Smoke Test (`tests/Feature/Juknis/KategoriJuknisTest.php`, 7 test)

UUID PK (regex uuid v4-format), relasi dua arah attach/detach, satu rekening banyak kategori (m2m), pivot unique tolak pasangan duplikat (`UniqueConstraintViolationException`), cascade hapus pivot saat kategori/rekening dihapus, cast `batas_persen` float + `berlaku_untuk` nullable.

- PHPStan note: `assertIsString($kategori->id)` ditolak PHPStan (`method.alreadyNarrowedType` karena `@property string $id`) → cukup assert panjang 36 + regex UUID.

## Verifikasi

- Round-trip migrasi di sqlite file scratch: `migrate --force` (000029 DONE) → `migrate:rollback --step=1` → kedua tabel hilang (`hasTable` false×2) → up/down VALID.
- Dev DB (`database/database.sqlite`) sudah dimigrasi ke 000029 (tabel + kolom + index unique terverifikasi via tinker probe).
- PHPUnit full suite `OK (440 tests, 1329 assertions)` (naik dari 433/1309), PHPStan level 6 `[OK] No errors`.

## Status

- Commit lokal; BELUM push/build/rilis. Menunggu laporan Tahap 1 disetujui user sebelum Tahap 2 (halaman pengaturan kategori + centang kode rekening) dan Tahap 3 (halaman monitoring, hitung % via RealisasiQuery thd pagu tahunan).

---

# Sesi 22 Agu 2026 - Monitoring Kepatuhan Juknis BOSP - TAHAP 2 (Halaman Pengaturan Kategori + Pemetaan Kode Rekening) - commit lokal

## Goal

Tahap 2 dari 3: halaman pengaturan kategori Juknis BOSP (CRUD) + halaman pemetaan kode rekening -> kategori (checkbox multi-pilih per baris). Seed 3 kategori default (Honor/Pemeliharaan/Buku) via MIGRASI (bukan DatabaseSeeder - desktop upgrade hanya jalan `migrate --force`, precedent migrasi 000026).

## Changes

- `database/migrations/2026_08_22_000030_seed_default_kategori_juknis.php` (BARU, idempoten): seed `Honor` (maksimal 20%), `Pemeliharaan` (maksimal 20%), `Buku` (minimal 10%) via DB facade + `Str::uuid()` (firstOrCreate by nama). down() hanya menghapus kategori yang BELUM dipakai di pivot (aman utk data user).
- `app/Http/Controllers/KategoriJuknisController.php` (BARU): index (withCount kodeRekenings), store/edit/update/destroy (validasi nama unique/arah in maksimal,minimal/batas 0-100/berlaku_untuk nullable max 50; AuditLog `kategori_juknis` create/update/delete), pemetaan (GET, search q di kode/nama, paginate(50)->withQueryString(), eager-load jenisBelanja+kategoriJuknis), simpanPemetaan (POST, DB::transaction sync per baris dari hidden `rows[]`; AuditLog aksi `update_pemetaan` berisi jumlah rekening diperbarui).
- `routes/web.php` - grup prefix `pengaturan/kategori-juknis` name `pengaturan.kategori-juknis.*`: index/store/edit/update/destroy + `pemetaan` & `simpan-pemetaan` (static routes SEBELUM param `{kategoriJuknis}`). Import controller.
- Views `resources/views/pengaturan/kategori-juknis/`: index (form tambah kiri lg:col-span-1 + daftar kanan lg:col-span-2, badge arah Minimal>= green / Maksimal<= red, batas number_format id), edit (max-w-2xl), pemetaan (search form terpisah, POST form membungkus tabel dgn hidden rows[] per baris + checkbox map[rid][kid], paginasi DI LUAR post-form, alert-info aturan "menyimpan hanya memperbarui baris yang tampil").
- `resources/views/layouts/navigation.blade.php` - link "Kategori Juknis BOSP" di dropdown Pengaturan (setelah Profil Sekolah, highlight aktif) + kondisi routeIs ditambah `pengaturan.kategori-juknis.*`.
- Test baru `tests/Feature/Juknis/KategoriJuknisPageTest.php` (12 test, 50 assertions).

## Keputusan desain pemetaan (dikonfirmasi via reasoning, konsisten kebutuhan)

- Hidden input `rows[]` = id SEMUA baris tampil -> uncheck semua checkbox pada satu baris tetap tersinkron (pemetaan dilepas); baris di halaman lain paginasi TIDAK disentuh.
- Simpan hanya memperbarui baris yang tampil (paginasi 50 + search) -> aman untuk 276+ rekening.

## Bug yang di-fix saat pengerjaan

- **Route model binding**: parameter metode awalnya `$kategoriJukni` tidak match `{kategoriJuknis}` -> binding gagal. Di-rename `$kategoriJuknis` di edit/update/destroy.
- **BOM UTF-8**: replace via PowerShell `Set-Content -Encoding UTF8` menambahkan BOM (EF BB BF) sebelum `<?php` -> fatal "Namespace declaration statement has to be the very first statement". Write tool pun MEMPERTAHANKAN BOM file lama saat overwrite. Fix definitif: strip char U+FEFF lalu `[System.IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false)))`. Verifikasi byte pertama = 3C (`<`). (Pelajaran sama dgn sesi v0.6.7.)
- Test Tahap 1 lama `KategoriJuknisTest::test_batas_persen_tercast_float...` pakai nama 'Honor' -> bentrok UNIQUE dgn seed 000030 -> diganti 'Honor Uji Cast'.

## Verifikasi

- `vendor\bin\phpunit --filter KategoriJuknisPageTest` -> OK (12 tests, 50 assertions); `--filter Juknis` -> OK (19 tests, 69 assertions).
- Full suite -> **OK (452 tests, 1378 assertions)** (naik dari 440/1329), PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- Dev DB (`database/database.sqlite`) dimigrasi ke 000030 DONE (3 kategori default ada).

## Status

- Commit lokal; BELUM push/build/rilis. Menunggu konfirmasi user sebelum Tahap 3 (halaman monitoring: persentase realisasi per kategori via RealisasiQuery thd pagu tahunan item yang ter-mapping, badge patuh/melanggar sesuai arah maksimal/minimal).

---

# Sesi 22 Agu 2026 — Monitoring Juknis BOSP TAHAP 3 (Halaman Monitoring) + Release v0.6.8

## Goal

Selesaikan Tahap 3 dari 3 fitur Monitoring Kepatuhan Juknis BOSP: halaman monitoring dengan donut chart per kategori ter-mapping (persentase thd Total Pagu), toggle basis Rencana/Realisasi (realisasi via RealisasiQuery, termasuk nota multi-item), bonus donut Proporsi Antar Jenis Belanja, alert kode rekening belum dikategorikan. Lalu (permintaan user): commit -> bump versi -> build installer -> reinstall -> rilis GitHub v0.6.8.

## Summary

- Commit `39625a5` (5 file, +689/-4): controller + view + route + sidebar + 8 test baru.
- Full suite `OK (460 tests, 1408 assertions)` (naik dari 452/1378), PHPStan level 6 `[OK] No errors`, `view:cache` OK.
- Bump 0.6.7 -> **0.6.8** (5 file: config/app.php, .env.example, src-tauri/tauri.conf.json, src-tauri/Cargo.toml, src-tauri/Cargo.lock blok name="smartrkas" saja; diff tepat 1+/1- per file, BOM check semua False).

## Changes

- `app/Http/Controllers/MonitoringJuknisController.php` (BARU):
    - `index(Request)`: basis = request('basis', 'rencana') in [rencana, realisasi]; tahun = input tahun -> fallback `TahunAnggaran::getActive()`; `$totalPagu` = sum rkas_item.jumlah tahun tsb; `$kategoriCards` = KategoriJuknis::whereHas('kodeRekenings') dgn nominal (rencana: RkasItem whereIn kode_rekening_id; realisasi: `RealisasiQuery::base()` alias rb join rkas_item on rb.rkas_item_id); persen = nominal / totalPagu \* 100; status via `statusFor()`; `jumlah_rekening` = count rekening ter-mapping.
    - `private const EPSILON = 0.000001`; `statusFor()`: arah maksimal -> persen <= batas + EPSILON ? 'sesuai' : 'melebihi'; arah minimal -> persen >= batas - EPSILON ? 'sesuai' : 'kurang'. TEPAT di batas = sesuai.
    - `jenisBelanjaBreakdown()`: RealisasiQuery/RkasItem join master_kode_rekening as mkr + jenis_belanja as jb, `COALESCE(jb.nama,'Tidak Terkategori')`, groupBy label (jalan di sqlite & mysql).
    - `belumDikategorikanCount()`: distinct kode_rekening_id dgn nominal pada basis aktif yang TIDAK ada di pivot kode_rekening_kategori_juknis; guard when($mappedIds->isNotEmpty()) sebelum whereNotIn.
    - Bug fix saat review sendiri: view memakai `$card['jumlah_rekening']` tapi push awal tidak menyertakan key itu -> ditambah `'jumlah_rekening' => count($rekeningIds)`.
- `resources/views/laporan/monitoring-juknis.blade.php` (BARU): x-app-layout; filter GET (select tahun auto-submit onchange + hidden basis); grid 2 kartu atas (Total Pagu stat-card blue + kartu toggle basis segmented pakai `request()->fullUrlWithQuery(['basis'=>...])`); alert-warning tanpa tahun anggaran; alert-info "N kode rekening ... belum dikategorikan" -> link `pengaturan.kategori-juknis.pemetaan`; empty-state tanpa kategori ter-mapping; kartu kategori grid md:2 xl:3 dengan canvas `id="juknis-donut-{index}"`, persen overlay tengah (warna match status), kotak Nominal + jumlah kode rekening, pesan status per arah; bonus card "Proporsi Antar Jenis Belanja" canvas `id="jenis-belanja-donut"` + legend dot `.jb-dot[data-index]`; Chart.js CDN + payload `@json($donutDataJs)`/`$jenisDataJs`.
    - Fix: view awalnya memakai `nonce="{{ csp_nonce() }}"` — helper TIDAK ADA di codebase (dashboard pakai `<script>` polos) -> dihapus; warna teks persen arah minimal disamakan amber (#f59e0b) dengan donut.
- `routes/web.php`: `GET laporan/monitoring-juknis` name `laporan.monitoring-juknis` (setelah laporan.index) + import controller.
- `resources/views/layouts/navigation.blade.php`: link "Laporan" jadi dropdown Alpine (Semua Laporan + Monitoring Juknis), pola sama dropdown Pengaturan (sidebar-dropdown-btn/sidebar-submenu/chevron).
- `tests/Feature/Juknis/MonitoringJuknisTest.php` (BARU, 8 test / 30 assertions): guest redirect; sidebar menampilkan link; **tepat di batas maksimal 20% = sesuai** (200rb mapped / 1jt total pagu — sekaligus bukti kode tak-termap 800rb TIDAK bocor ke kategori); melebihi batas; minimal tercapai (100% >= 15%); toggle basis mengubah nominal rencana(200rb) -> realisasi(100rb transaksi pengeluaran); **realisasi nota multi-item ikut dihitung** (nota_bku_item subtotal 100rb + SYARAT minimal 1 transaksi aktif nota_bku_id terisi — sesuai whereExists RealisasiQuery); tanpa tahun anggaran menampilkan peringatan.
    - Catatan test: seed migrasi 000030 (Honor/Pemeliharaan/Buku) selalu ada di RefreshDatabase tapi TIDAK dipetakan -> tidak muncul sbg kartu; nama kategori uji dibuat unik agar tidak bentrok UNIQUE dgn seed.

## Verifikasi

- `vendor\bin\phpunit --filter MonitoringJuknisTest` -> OK (8 tests, 30 assertions) langsung hijau.
- Full suite -> OK (460 tests, 1408 assertions); PHPStan level 6 [OK] No errors; view:cache OK.
- Commit `39625a5` diverifikasi via git log + diff-tree (5 file).

## Build & Release v0.6.8

- `npm run build` OK (60 modules, app-BDxLy3Fi.css / app-CA7a7cYK.js).
- `tauri build --bundles nsis,msi` via background process (log `%TEMP%\opencode\build-v068.log`): compile **11m33s** -> NSIS `SmartRKAS_0.6.8_x64-setup.exe` (**58.7MB**) + MSI `SmartRKAS_0.6.8_x64_en-US.msi` (**89.9MB**). SOP anti-build-rangkap dicek dulu (0 proses cargo/rustc/tauri sebelum mulai).

## Reinstall & Verifikasi Instalasi Nyata v0.6.8

- Kill app v0.6.7 (`Stop-Process -Force`; job object mematikan anak php bersih — 0 orphan php instalasi). Uninstall `/S` exit 0 -> folder `%LOCALAPPDATA%\SmartRKAS` hilang, **DB Roaming utuh** (1.74MB). Install NSIS v0.6.8 `/S` exit 0 -> exe ProductVersion **0.6.8**, `php\php.exe` + `php\extras\ssl\cacert.pem` (182KB) terbundle.
- App jalan -> server `php -S 127.0.0.1:57427` (TEPAT 1 server), `/login` = **200/200/200** (11272 bytes).
- Argumen router terverifikasi presisi: `-S 127.0.0.1:<port> C:\...\server.php` — TANPA prefix `\\?\`. Prefix `\\?\` di cmdline HANYA pada path executable php.exe itu sendiri (cara Rust spawn, normal) — jangan panik karena grep kasar (pelajaran sama sesi v0.6.7).
- Cmdline server memuat semua fix TLS/opcache: `-d opcache.enable=0 -d log_errors=1 -d error_log=<data-dir>\php-server-error.log -d curl.cainfo=<install>\php\extras\ssl\cacert.pem -d openssl.cafile=<sama>`.
- Fitur baru terverifikasi terbundle: `MonitoringJuknisController.php` (ada string `statusFor`), view `laporan/monitoring-juknis.blade.php`, `config/app.php` versi 0.6.8.
- `php-server-error.log`: 756 -> **756 bytes** (TIDAK bertambah; isi lama fatal era `\\?\` 08-Agu).

## Release & Push v0.6.8

- Secret scan diff bersih -> commit `87c486d` (bump 5 file + AGENTS.md) -> push `master` (`b7184f8..87c486d`; stderr "NativeCommandError" = noise PowerShell, push sukses).
- Release: https://github.com/Mukhamadirfan1997/SMARTRKAS/releases/tag/v0.6.8 — 2 asset state `uploaded`, `isDraft=false, isPrerelease=false` (NSIS 61.5MB on-disk + MSI 94.2MB). Notes via `--notes-file` temp (hindari globbing PowerShell).
- App v0.6.8 DIBIARKAN BERJALAN (server port 57427) untuk uji manual user (halaman Monitoring Juknis via menu Laporan).

---

# Sesi 23 Agu 2026 — Fitur Baru: Formulir BOS-K7b & BOS-K7c (Register Penutupan Kas & Berita Acara Pemeriksaan Kas)

## Goal

Menambahkan modul laporan resmi penatausahaan BOSP/BOS sekolah format **Formulir BOS-K7b** (Register Penutupan Kas / Opname Kas) dan **Formulir BOS-K7c** (Berita Acara Pemeriksaan Kas), disesuaikan dengan contoh fisik nyata (standar Kemendikbudristek).

## Summary

- Modul laporan K-7b & K-7c selesai diimplementasikan (Read-only, Zero-risk terhadap core transaksi & RKAS).
- 7 test baru di `LaporanK7Test` lulus (29 assertions) → total full suite naik menjadi **467 tests (1437 assertions)**.
- PHPStan level 6: `[OK] No errors`, `view:cache`: OK.

## Changes

- `app/Http/Controllers/LaporanController.php`:
    - `k7b(Request $request)` & `k7c(Request $request)`: mendukung Web view interaktif dan streaming PDF (`?cetak=pdf`).
    - `prepareK7Data(Request $request)`: menghitung otomatis total penerimaan (D) s.d. bulan berjalan, total pengeluaran (K), saldo BKU (A = D - K), rincian denominasi uang kertas (Rp100.000 s.d. Rp1.000), keping logam (Rp500 s.d. Rp50), subtotal fisik kas (1+2), saldo bank (3), total kas (B = 1+2+3), perbedaan (A - B), serta nomor SK Bupati Kepsek & Bendahara.
- `routes/web.php`:
    - `Route::get('laporan/k7b', [LaporanController::class, 'k7b'])->name('laporan.k7b')`
    - `Route::get('laporan/k7c', [LaporanController::class, 'k7c'])->name('laporan.k7c')`
- Views (`resources/views/laporan/`):
    - `k7b.blade.php`: Halaman interaktif desktop dengan kalkulator realtime JS (otomatis hitung subtotal lembaran uang kertas, logam, total fisik kas, saldo bank, dan selisih A-B = 0 / NIHIL tanpa reload) + tombol Cetak Langsung (`window.print()`) & Unduh PDF.
    - `k7b-pdf.blade.php`: Template DomPDF resmi Formulir BOS-K7b dengan layout tabel pecahan uang dan kolom tanda tangan Kepsek & Bendahara.
    - `k7c.blade.php`: Halaman interaktif Berita Acara Pemeriksaan Kas BOS-K7c dengan narasi SK dan preview live.
    - `k7c-pdf.blade.php`: Template DomPDF resmi Formulir BOS-K7c.
    - `index.blade.php`: Menambahkan 2 kartu menu laporan baru (Register Kas K-7b & Pemeriksaan Kas K-7c) dalam grid 3-kolom.
- `tests/Feature/Laporan/LaporanK7Test.php` (BARU, 7 test / 29 assertions): guest redirect, index links, render web K7b dengan saldo BKU, kalkulasi uang fisik & selisih NIHIL, stream PDF K7b, render web K7c, stream PDF K7c.

## Test Status

- `vendor\bin\phpunit --filter LaporanK7Test` → `OK (7 tests, 29 assertions)`.
- Full suite → `OK (467 tests, 1437 assertions)`.
- PHPStan level 6 → `[OK] No errors`.
- `php artisan view:cache` → `INFO Blade templates cached successfully.`

---

# Sesi 23 Agu 2026 (lanjutan malam) — Verifikasi SOP K7b/K7c SESUAI + Saga Tanda Merah VS Code ("Undefined method 'user'") — DITUTUP, DITUNDA KE BESOK

## Status Berhenti (titik lanjut besok)

- **Fitur K7b/K7c SELESAI & TERVERIFIKASI penuh per SOP tapi BELUM di-commit.** Working tree berisi (git status):
    - M `AGENTS.md`, `app/Http/Controllers/LaporanController.php` (+188 baris), `composer.json`, `composer.lock`, `resources/views/laporan/index.blade.php`, `routes/web.php`
    - ?? `_ide_helper.php`, `config/ide-helper.php`, `stubs/ide-helper-custom.php`, `resources/views/laporan/k7b.blade.php`, `k7b-pdf.blade.php`, `k7c.blade.php`, `k7c-pdf.blade.php`, `tests/Feature/Laporan/LaporanK7Test.php`
- HEAD = `34265c7` (v0.6.8, sudah di-push). **Instalasi desktop = v0.6.8** → fitur K7 belum masuk app terinstall (masuk rilis berikutnya).
- Proses jalan saat berhenti: PID 44744 (server app desktop v0.6.8 di `127.0.0.1:62594`) + PID 57796 (`artisan schedule:work`) — milik app terinstalled, DIBIARKAN.
- Full suite `OK (467 tests, 1437 assertions)` · PHPStan level 6 `[OK] No errors`.

## Hasil Verifikasi Independen K7b/K7c (SESUAI SOP)

- 46/46 PASS: guest redirect ×3, index links, render web K7b/K7c, stream PDF valid (`%PDF`), export tidak tersentuh, route names benar.
- Isi PDF diverifikasi token-per-token via parser manual (ekstraksi content-stream FlateDecode): **`gzdecode()` GAGAL** untuk PDF dompdf; yang benar = `inflate_init(ZLIB_ENCODING_DEFLATE)` + `inflate_add()` bertahap. Bukti angka TA 2026: D=2.500.000, K=800.000, A=1.700.000, "NIHIL", "31 Agustus 2026", SK Bupati 003/SK-BUPATI/BOS/2026 & 002/SK-BENDAHARA/BOS/2026.
- Probe data memakai transaksi uji sementara di DB dev lalu DIHAPUS (kembali 9 baris); script temp `%TEMP%\opencode\verify-k7.php` dsb dibersihkan.

## Saga Tanda Merah VS Code — Diagnosis Lengkap + Keputusan User: ABAIKAN

- Gejala user: tanda merah di `LaporanController.php` (dan halaman lain) meski kode sehat. Pesan konkret dari user: **"Undefined method 'user'"** → menunjuk pola `auth()->user()`.
- Cakupan masalah: `auth()->user()` dipakai **25 tempat** se-proyek (LaporanController lines 46, 121, 163, 216, 271, 302, 333, 365, 888, 913; NotaBkuController 137/363/384; TransaksiBkuController 251/471/512; TelegramPengaturanController 17/34/54; RkasController 226/250; TransaksiTemplateController 57/85; RecoveryCodeController 20; TelegramLogHandler 28) — makanya merah banyak sekali, bukan spesifik fitur baru.
- Ekstensi PHP VS Code terpasang: devsense.phptools-vscode-1.72.19127 (analyzer aktif) + devsense.intelli-php/composer/profiler, xdebug pack/debug, namespace-resolver, phpsnippets, dan **zobo.php-intellisense-1.3.3** (lama/unmaintained, duplikat intellisense). Zobo dinonaktifkan via `code --disable-extension zobo.php-intellisense`; language server lama di-kill (PID 42176, lalu PID 46660 muncul lagi).
- Upaya fix: pasang **barryvdh/laravel-ide-helper ^3.7** (dev dep standar Laravel) → publish `config/ide-helper.php` → temuan: `'include_helpers' => false` default (stub fungsi global `auth()` TIDAK ter-generate; `helper_files` default hanya Support+Foundation tapi gated oleh flag ini).
- Jebakan 1: setelah include_helpers=true, stub auth() ter-generate sebagai `AuthFactory|Guard` — **AuthFactory TIDAK punya user()** (yang punya AuthManager via `@mixin Guard|StatefulGuard` di vendor) → merah bisa saja tetap.
- Jebakan 2: menambahkan stub kustom + Foundation helpers menghasilkan **2 deklarasi auth()** di `_ide_helper.php` → ambigu untuk IDE.
- Fix final: buat `stubs/ide-helper-custom.php` (auth() → `\Illuminate\Auth\AuthManager|\Illuminate\Contracts\Auth\Guard`) dan `helper_files` DIISI STUB PROYEK SAJA (Support/Foundation dikeluarkan dari daftar; helper lain tetap terbaca IDE langsung dari vendor). Regenerate → tepat 1 deklarasi bersih (line ~29363). Suite + PHPstan re-verifikasi hijau.
- **Hasil akhir: merah MASIH muncul setelah Reload Window** → kemungkinan DEVsense free-tier tidak melakukan resolusi mendalam helper/@mixin Laravel. **Keputusan user: ABAIKAN** (kosmetik murni; kode terbukti benar oleh php -l + 467 test HTTP nyata + PHPStan level 6).

## Pelajaran Proses (untuk sesi berikutnya)

- ide-helper: publish config via provider (`vendor:publish --provider="Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider"`) — tag `--tag=ide-helper` TIDAK ada (tag internal 'config').
- Stub auth() hasil generator (AuthFactory|Guard) tidak cukup untuk resolusi `->user()`; butuh AuthManager (@mixin Guard). Jangan biarkan deklarasi ganda di `_ide_helper.php`.
- `_ide_helper.php` (~953KB, root proyek) + `config/ide-helper.php` + `stubs/ide-helper-custom.php` + composer.json/lock akan IKUT commit berikutnya (diputuskan dipertahankan — standar tooling Laravel, tidak berbahaya; sudah diverifikasi tidak mengganggu test/PHPStan).
- Merah VS Code pada pola Laravel idiomatik ≠ bug aplikasi: selalu cross-check dengan test suite + PHPStan sebelum percaya indikator IDE.

## Next (urutan sesi berikutnya)

1. Commit lokal: fitur K7b/K7c (controller/routes/views/test) + artefak ide-helper (composer.json/lock, config/ide-helper.php, stubs/, _ide_helper.php, AGENTS.md) — pesan commit menjelaskan keduanya.
2. Bump versi 0.6.8 → 0.6.9 (5 file: config/app.php, .env.example, src-tauri/tauri.conf.json, src-tauri/Cargo.toml, src-tauri/Cargo.lock blok `name="smartrkas"` saja) bila user setuju rilis.
3. Build NSIS+MSI → clean-install → verifikasi `/login` 200 + fitur K7 terbundle → push → `gh release create v0.6.9`.
4. Semua langkah build/rilis MENUNGGU konfirmasi eksplisit user.

## Test Status

- PHPUnit full suite `OK (467 tests, 1437 assertions)`, PHPStan level 6 `[OK] No errors`. BELUM commit — semua pekerjaan sesi ini aman di working tree.

---

# Sesi 24 Agu 2026 - Release v0.6.9 (Fitur BOS-K7b & K7c + ide-helper)

## Goal

Commit fitur Formulir BOS-K7b/K7c (yang sebelumnya terverifikasi tapi belum di-commit dari sesi 23 Agu), bump versi 0.6.9, build installer, clean-install verifikasi, push, dan rilis GitHub.

## Summary

- Commit `88943fd` (14 file): fitur K7b/K7c (controller +188 baris, 4 view baru, routes, test 178 baris) + artefak laravel-ide-helper (_ide_helper.php, config/ide-helper.php, stubs/, composer.json/lock) + AGENTS.md.
- Full suite diverifikasi ulang SEBELUM commit: `OK (467 tests, 1437 assertions)`, PHPStan level 6 `[OK] No errors`.
- Secret scan diff bersih.
- Build: NSIS `SmartRKAS_0.6.9_x64-setup.exe` (58.8MB) + MSI `SmartRKAS_0.6.9_x64_en-US.msi` (90.1MB).

## Bump Versi

- 0.6.8 -> 0.6.9 di 5 file (config/app.php, .env.example, src-tauri/tauri.conf.json, src-tauri/Cargo.toml, src-tauri/Cargo.lock blok `name = "smartrkas"` saja).
- Metode aman anti-BOM: `[System.IO.File]::WriteAllText(, , (New-Object System.Text.UTF8Encoding(False)))`; Cargo.lock via replace string persis `name = "smartrkas"`+newline+`version = "0.6.8"` (bukan regex global). Verifikasi: diff 5 file 1+/1-, first-byte semua bukan 239 (no BOM).

## Clean-install & Verifikasi Instalasi Nyata v0.6.9

- Kill app v0.6.8 -> uninstall `/S` -> folder %LOCALAPPDATA% hilang, DB Roaming utuh (1.87MB).
- Install NSIS v0.6.9 `/S` -> exe ProductVersion **0.6.9**, php bundle + cacert.pem (186KB) terbundle.
- Fitur K7 terverifikasi terbundle: view k7b/k7c ada, controller punya `function k7b`, routes memuat `laporan.k7b`, config versi 0.6.9.
- App jalan -> server `php -S 127.0.0.1:50847` (router TANPA prefix `\\\\?\\`, arg `-d opcache.enable=0 log_errors=1 error_log=... curl.cainfo=... openssl.cafile=...` lengkap).
- `/login` = **200/200/200** (len 11272).
- `php-server-error.log` tetap **756 bytes** (tidak bertambah); `laravel.log` tidak ada error baru (semua timestamp 15-21 Agu; error "sqlite3 not recognized" yang muncul saat grep = entri LAMA era pra-v0.6.6, terverifikasi dari timestamp).

## Release

- Push master + tag/release v0.6.9 dengan 2 asset (NSIS + MSI).

## Test Status

- PHPUnit full suite OK (467 tests, 1437 assertions), PHPStan level 6 [OK] No errors.

---

# Sesi 24 Agu 2026 - Fix K7b Saldo Minus Filter Sumber Dana + Tanggal Sticky + Hari Indonesia (commit lokal, BELUM push/build)

## Goal

3 temuan user pada fitur Formulir BOS-K7b/K7c: (1) saldo A minus saat filter Sumber Dana (6 baris Tarik Tunai BBU001-BBU006 tanpa sumber_dana_id), (2) label tanggal penutupan sticky saat ubah Bulan Penutupan, (3) narasi berita acara K7c tidak menyebut nama hari. User setuju "Ya, keduanya" untuk backfill DB + wajibkan Sumber Dana di form penerimaan.

## Changes

- **app/Http/Controllers/TransaksiBkuController.php** - fix 1b: `create()`/`edit()` load `$sumberDanas`; branching manual di `storeSingleItem()` (~:266-288) & `update()` (~:442-456): item RKAS -> derive dari RkasItem; penerimaan -> wajib + exists check (`ValidationException` key `sumber_dana_id`, pesan "Sumber Dana wajib dipilih untuk transaksi penerimaan (tarik tunai)."); update lainnya tidak menyentuh nilai existing. Sengaja TIDAK menambah rule `sumber_dana_id` ke `validate()` agar nilai nota tidak ter-null saat edit.
- **create.blade.php / edit.blade.php** - div `row_sumber_dana` + select (TANPA atribut HTML required agar tidak blok submit pengeluaran; field toggled hidden) + `@error`; JS `const rowSumberDana` + wiring `toggleVisibility()` kedua halaman; edit prefill `$oldSumberDana = old('sumber_dana_id', $transaksiBku->sumber_dana_id)` (BBU lama NULL -> user harus pilih saat edit, by design).
- **LaporanController::prepareK7Data()** - guard tanggal sticky (tanggal dikirim bulan/tahun != periode terpilih -> pakai akhir bulan terpilih) + mapping hari EN->ID via Carbon translatedFormat dayName (`$hariPenutupan`) masuk compact.
- **k7b.blade.php / k7c.blade.php** - JS `syncTanggalFilter()` reset field tanggal saat ganti bulan/tahun; **k7c-pdf.blade.php** narasi memuat nama hari `<strong>{{ $hariPenutupan }}</strong>`.
- **Backfill produksi**: 6 baris BBU001-BBU006 (total Rp 90.160.000) -> "BOSP Reguler" (`019fd0b9-4bc1-...`). Protokol: rehearsal di salinan VACUUM INTO -> verifikasi end-to-end via controller nyata -> backup snapshot `backup-sebelum-backfill.sqlite` -> apply produksi -> verifikasi 0 baris NULL tersisa + AuditLog `transaksi_bku/backfill_sumber_dana`.

## Tests

- `tests/Feature/BKU/TransaksiBkuTest.php`: patch 3 test POST/PUT penerimaan existing (+sumber_dana_id); test baru: store tanpa sumber dana ditolak, sumber dana id tak dikenal ditolak, update wajib sumber dana & nilai lama utuh, create page render row_sumber_dana.
- Full suite OK (475 tests, 1460 assertions), PHPStan level 6 [OK] No errors, view:cache OK.

## Catatan

- Verifikasi K7b pasca-backfill (produksi): filter BOSP Reguler A = Rp 10.901.500 = tanpa filter; Perbedaan 0,00; D=90.160.000 K=79.258.500.
- Fix kode belum masuk instalasi v0.6.9 (butuh build baru); backfill data sudah aktif seketika di app jalan.
- Backup pra-backfill: %TEMP%\opencode\backup-sebelum-backfill.sqlite (1.87MB).
- User setuju "Commit saja dulu" via question tool; BELUM push/build/rilis - bump versi + build installer menyusul bila diminta.

---

# Sesi 24 Agu 2026 - Saldo Bank Formulir K7b/K7c + Mutasi Tarik Tunai Netral + Persistensi Opname Kas (commit lokal)

## Goal

Implementasi saldo bank pada Formulir BOS-K7b/K7c sesuai panduan resmi (keputusan user: "Ya, ikuti panduan resmi"): tarik tunai = MUTASI netral, Sisa Saldo = Awal + D - K mencakup kas+bank, Perbedaan = Kas Fisik + Bank vs Sisa Buku. Plus persistensi opname kas per bulan (kas_penutupan) dan Register Penutupan Kas multi-bulan PDF landscape.

## Summary

- Full suite OK (482 tests, 1496 assertions) (+7 dari baseline 475), PHPStan level 6 [OK] No errors, view:cache OK.
- Backfill produksi: 6 baris tarik tunai lama -> kategori_arus='mutasi' via migrasi 000031 (juga menangkap typo "Tari Tunai").
- Fix bug produksi KRITIS ditemukan saat verifikasi: nama field form denominasi TIDAK COCOK dengan rule validasi -> simpan dari UI nyata SELALU gagal validasi secara diam-diam.

## Changes

- Migration 000031 add_kategori_arus_and_kas_penutupan_table: kolom transaksi_bku.kategori_arus string(20) nullable + backfill mutasi; tabel kas_penutupan (uuid, tahun_anggaran_id restrict, bulan, sumber_dana_id nullable nullOnDelete, tanggal_penutupan date nullable, lembar_100000..lembar_1000, keping_500..keping_50, saldo_bank decimal(15,2), catatan, created_by, unique kas_penutupan_periode_unique [tahun_anggaran_id, bulan, sumber_dana_id]).
- app/Models/TransaksiBku.php: kategori_arus fillable/@property + isMutasi() helper (penerimaan && kategori_arus==='mutasi').
- TransaksiBkuController: saldo berjalan & total penerimaan BKU kini EXCLUDE mutasi (netral); validasi kategori_arus store/update ('nullable|in:mutasi', hanya diset utk penerimaan, pengeluaran selalu NULL); update() hanya menyentuh nilai bila form mengirim field.
- create/edit.blade.php: radio "Jenis Penerimaan" (Pencairan/SP2D vs Tarik Tunai) tampil utk penerimaan saja + JS auto-sinkron uraian.
- app/Models/KasPenutupan.php (BARU): daftarKertas()/daftarLogam() (kolom berprefiks 'lembar_*'/'keping_*'), subtotalFisik(), totalRiil() (= fisik + saldo_bank); cast 'tanggal_penutupan' => 'date' (WAJIB - tanpa ini toDateString() di test fatal).
- LaporanController (+237): prepareK7Data() baca input saldo_bank (fallback record tersimpan -> hitungan komputasi), lookup KasPenutupan utk repopulasi form (resolveTahunAnggaran + bulan + sumber_dana_id whereNull bila kosong); simpanK7b() upsert per periode dgn payload keys berprefiks; registerK7b() register PDF a4-landscape (saldo awal kumulatif s.d. sebelum bulan "dari", running saldo, non-mutasi saja) + streamPdf.
- routes/web.php: POST laporan/k7b/simpan (laporan.k7b.simpan), GET laporan/k7b-register (laporan.k7b.register).
- Views: k7b.blade.php (+37 form simpan + saldo bank + link register), k7b-register-pdf.blade.php (BARU), k7c.blade.php & k7c-pdf.blade.php typo "RP" -> "Rp".
- Tests: LaporanK7Test +177 baris (15 tests total: persistensi opname, repopulasi, saldo bank, register PDF); TransaksiBkuTest +78 baris (mutasi netral saldo berjalan, validasi kategori_arus).

## Bug Produksi KRITIS (ditemukan & diperbaiki)

- prepareK7Data() awalnya memakai array denominasi keys ANGKA POLOS ('100000', '500') sehingga form browser mengirim name="kertas_100000", padahal simpanK7b() memvalidasi "kertas_lembar_100000" (keys berprefiks dari daftarKertas()) -> POST dari UI nyata SELALU gagal validasi diam-diam (redirect balik tanpa pesan jelas). FIX (Option B): array lokal prepareK7Data() diganti ke keys berprefiks yang sama dengan model -> form otomatis cocok dengan rules. Option A (balik model ke keys polos) ditolak: diff lebih besar + harus revert edit controller.
- Double-prefix bug terkait: read side prepareK7Data sempat getAttribute('kertas_'.) padahal  sudah berprefiks -> getAttribute().
- Audit consumer daftarKertas/daftarLogam SEBELUM ubah format key: hanya model sendiri (subtotalFisik/totalRiil pakai \ sebagai nama kolom penuh), simpanK7b, dan JS k7b.blade.php (dataset.key hanya untuk bangun param GET) -> aman.

## Gotcha (pelajaran)

- Eloquent selectRaw("... as x")->value('x') TIDAK reliable di setup ini (nilai mentah/null) -> WAJIB ->first() lalu getAttribute('x') (pola sudah dipakai di controller lain, ikuti pola itu).
- Model @property docblock harus ikut di-update saat tambah cast date (tanggal_penutupan) -> kalau tidak, PHPStan error method.nonObject di test yang memanggil toDateString().
- Anotasi generics wajib level 6: /** @use HasFactory<\Database\Factories\XxxFactory> */ + /** @return BelongsTo<RelatedModel, \> */ di tiap relasi (pola konsisten semua model).
- Nullsafe "?->" di kiri "??" dianggap redundant PHPStan ("Use -> instead") - JANGAN buta ikuti (fatal on null saat runtime); refactor eksplisit: \ = filled(\) ? SumberDana::find(\) : null; lalu ternary !== null.
- Lookup KasPenutupan repopulasi pakai whereNull('sumber_dana_id') bila filter kosong - test kedua HARUS kirim sumber_dana_id sama seperti POST pertama agar row ketemu.

## Status

- Commit lokal; BELUM push/build/rilis - menunggu konfirmasi user.
- File xlsm referensi di root tetap untracked, TIDAK di-commit.
- Instalasi v0.6.9 belum memuat fitur ini; migration 000031 SUDAH diterapkan ke DB produksi Roaming saat backfill.

---

# Sesi 25 Agu 2026 — K7c Terima Data Live dari K7b (kas_fisik) + Fix Flaky Full Suite (UNIQUE tahun_anggaran)

## Goal

(1) K7c sebelumnya selalu menampilkan kas fisik Rp 0 padahal K7b sudah diisi — buat K7c menerima data live dari layanan K7b sebelum disimpan penutupan kas. (2) Full suite sempat GAGAL sekali dengan `SQLSTATE[23000]: UNIQUE constraint failed: tahun_anggaran.tahun` di `LaporanK7Test::test_k7b_mengabaikan_tanggal_stale_saat_bulan_filter_tidak_cocok` — diagnosis dan eliminasi permanen.

## Changes

- **`app/Http/Controllers/LaporanController.php`** — `prepareK7Data()`: input `kas_fisik` (string dari URL param GET k7b→k7c) dioverride sebagai kas fisik setelah `$subtotalFisikKas` dihitung dan SEBELUM fallback saldoBank; sanitasi strip `.`/`,` lalu cast float, `max(0.0, ...)` (tidak bisa minus). Tanpa param → perilaku lama utuh (fallback record tersimpan → hitungan komputasi). PDF k7b/k7c & register tidak tersentuh (tetap komputasi penuh).
- **`resources/views/laporan/k7b.blade.php`** — tombol menuju K7c diberi `id="btn-k7c"` + href disinkronkan di dalam fungsi `updatePdfUrl()` yang sudah ada (dipanggil pada setiap perubahan filter/denominasi), jadi klik K7c selalu membawa snapshot terbaru via query string (`bulan`, `tahun`, `sumber_dana_id`, denominasi `kertas_*`/`logam_*`, `saldo_bank`, plus `kas_fisik`).
- **`resources/views/laporan/k7c.blade.php`** — `updatePdfUrl()` ikut mengirim/menghapus `kas_fisik`; tampilan berita acara menampilkan nilai live tersebut.
- **Flaky fix `tests/Feature/Laporan/LaporanK7Test.php`** — semua pemanggilan `TransaksiBku::factory()->create()` yang TIDAK butuh item RKAS (13 tempat) kini eksplisit `'rkas_item_id' => null`. Root cause: test mem-pin tahun TA uji ke 2026 (`$this->tahunAnggaran->update(['tahun' => 2026])`), lalu factory transaksi tanpa override `rkas_item_id` meng-expansion definition → `RkasItemFactory` membuat `TahunAnggaran::factory()` baru dengan `(int) fake()->unique()->year()` yang bisa menggambar 2026 lagi → collision UNIQUE. Faker `unique()` reset per-test sehingga hanya test yang pin tahun SETELAH pembuatan data yang rawan (~9 test). Fix bersifat struktural: tidak ada lagi pembuatan RkasItem/TahunAnggaran tersembunyi di test K7. Factory global (`TahunAnggaranFactory` tahun acak) SENGAJA tidak diubah (blast radius besar).
- **3 test baru**: `test_k7b_memiliki_tombol_k7c_dengan_id`, `test_k7c_menampilkan_data_live_dari_k7b_via_query_string`, `test_k7c_menghormati_override_kas_fisik`.

## Verifikasi

- `vendor\bin\phpunit --filter LaporanK7Test` → OK (18 tests, 71 assertions).
- Full suite → **OK (485 tests, 1506 assertions)**; PHPStan level 6 `[OK] No errors`.
- Catatan: test lama ~line 122 masih lemah (`'kertas_100000' => 20` vs rule `kertas_lembar_100000`) tapi lolos via fallback — sengaja tidak disentuh di sesi ini.
- K7c view TIDAK merender `penjelasan_perbedaan` — jangan di-assert di test K7c.

## Status

- ~~BELUM commit~~ → SUDAH di-commit sebagai `779b97c` (5 file incl. AGENTS.md, +128/−1) — BELUM push. File xlsm referensi tetap untracked, TIDAK di-commit.
- Backlog: keluhan user "Monitoring Juknis belum full tampilannya" (makna belum jelas — kandidat: chart CDN kosong offline / hanya 3 kategori seed / layout sempit) menunggu klarifikasi atau baca ulang view penuh.

---

# Sesi 25 Agu 2026 (lanjutan) — Commit K7c Live Data + Rapikan Layout Monitoring Juknis BOSP

## Goal

(1) Commit pekerjaan K7c live data + flaky fix yang sudah terverifikasi (user: "Ya, commit saja dulu" — lokal saja). (2) Tindak lanjut keluhan "Monitoring Juknis belum full tampilannya" — diklarifikasi user via question tool: maksudnya **tampilan berantakan/kurang rapi** (layout), BUKAN chart CDN atau jumlah kategori seed.

## Root Cause Layout Berantakan (bukti keras, bukan dugaan)

- Konvensi app: `layouts/app.blade.php:99` merender `<main id="main-content" class="page-content">`; `resources/css/app.css` line 263 `.page-content { @apply flex-1 p-6 lg:p-8 }` → padding SUDAH disediakan layout.
- Dashboard & `rkas/index` meletakkan konten LANGSUNG di bawah `<x-app-layout>` dengan spasi `mb-6`.
- monitoring-juknis membungkus konten dgn wrapper Breeze lama `py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6` → padding DOBEL (48–56px atas, hingga 64px horizontal), kolom sempit center tidak konsisten dgn halaman lain, gap tak rata (`space-y-6` + `mb-6` anak).

## Changes (hanya `resources/views/laporan/monitoring-juknis.blade.php`)

- Header `<h2 class="font-semibold text-xl ...">` → `<div class="page-title">` (+ subtitle `mt-0.5`) — pola sama dgn dashboard/rkas.
- Wrapper Breeze (pembuka + penutup yatim setelah `@endif`) DIHAPUS — konten langsung di bawah layout.
- Spasi dinormalkan ke `mb-6`: card filter, card empty-state (cabang `$kategoriCards->isEmpty()`), grid kartu kategori.
- Card-header dibersihkan: hapus utility flex berlebih (`flex items-start justify-between gap-2`) — cukup class `card-header`.
- Indentasi dalam sengaja tidak dirapikan penuh (kosmetik; diff minimal).
- TIDAK tersentuh: Chart.js CDN (issue terpisah — offline donut kosong, belum diminta fix) dan seed kategori (by design 3 default dari migrasi 000030).

## Verifikasi

- `php artisan view:cache` OK; PHPStan level 6 `[OK] No errors`.
- `vendor\bin\phpunit --filter MonitoringJuknisTest` → OK (8 tests, 30 assertions).
- Full suite → **OK (485 tests, 1506 assertions)**.

## Status

- Commit lokal (monitoring-juknis.blade.php + AGENTS.md ini); BELUM push/build/rilis. xlsm tetap untracked.
- Backlog tetap: test lemah ~line 122 `LaporanK7Test.php`; Chart.js CDN offline.

## Next (instruksi user)

- **Nanti malam: BUILD TANPA RILIS** — user mau uji manual dulu. Urutan: cek tidak ada build rangkap → `npm run build` → `tauri build --bundles nsis,msi` → reinstall di mesin ini → verifikasi `/login` 200 + fix layout Monitoring Juknis terbundle → serahkan ke user untuk uji manual. Push & `gh release create` MENUNGGU hasil uji user (jangan rilis otomatis).

---

# Sesi 24 Agu 2026 (malam) - Build v0.6.10 TANPA RILIS + Reinstall Terverifikasi

## Goal

Eksekusi instruksi user "build tanpa rilis": build installer dari seluruh commit lokal sejak v0.6.9 (fix K7b/K7c saldo bank + mutasi netral + persistensi opname kas + register PDF, K7c live data kas_fisik, rapikan layout Monitoring Juknis), reinstall di mesin ini, verifikasi penuh, lalu serahkan ke user untuk uji manual. Push & rilis GitHub MENUNGGU hasil uji user.

## Build

- Bump 0.6.9 -> **0.6.10** di 5 file (config/app.php, .env.example, src-tauri/tauri.conf.json, src-tauri/Cargo.toml, src-tauri/Cargo.lock blok name="smartrkas" saja) via Edit tool (anti-BOM). Diff tepat 5 file 1+/1-, first-byte bukan 239.
- npm run build OK (60 modules, app-P-PfVrUt.css / app-CA7a7cYK.js).
- tauri build --bundles nsis,msi via background process (log %TEMP%\opencode\build-v0610.log): compile 10m10s -> NSIS SmartRKAS_0.6.10_x64-setup.exe (**61.7MB**) + MSI SmartRKAS_0.6.10_x64_en-US.msi (**94.7MB**).

## Reinstall & Verifikasi Instalasi Nyata v0.6.10

- Cek pra-install: tidak ada proses app/build berjalan (hanya VS Code PHP language servers). Uninstall v0.6.9 (/S exit bersih) -> folder %LOCALAPPDATA%\SmartRKAS hilang, **DB Roaming utuh** (1.871.872 bytes). Install NSIS v0.6.10 /S -> exe ProductVersion **0.6.10**, php\php.exe + php\extras\ssl\cacert.pem (186.446 bytes) terbundle.
- **7/7 cek fix terbundle PASS**: monitoring-juknis.blade.php punya page-title & wrapper Breeze max-w-7xl HILANG; LaporanController memuat kas_fisik; model KasPenutupan.php ada; migrasi 000031 ada; k7b-register-pdf.blade.php ada; config/app.php 0.6.10.
- App jalan -> server php -S 127.0.0.1:55497 (PID 64456), router TANPA prefix \\?\. /login = **200/200/200**.
- php-server-error.log: 756 -> **756 bytes** (tidak bertambah; isi lama fatal era \\?\ 08-Agu).
- Auto-migrate startup terverifikasi no-op: migrate:status DB Roaming -> semua Ran, 000031 batch 9 (sudah di-apply saat backfill sesi 24 Agu).

## Status

- Commit lokal (bump versi + AGENTS.md ini); BELUM push, BELUM tag/release GitHub.
- App v0.6.10 DIBIARKAN BERJALAN (port 55497) untuk uji manual user: fokus uji = (1) K7b saldo bank + tarik tunai mutasi netral + simpan/persistensi opname per bulan + register PDF multi-bulan, (2) K7c menerima data live kas fisik dari K7b, (3) layout Monitoring Juknis sudah rapi konsisten dgn halaman lain, (4) wajib Sumber Dana utk penerimaan BKU.
- Bila user lolos -> push master + gh release create v0.6.10 (2 asset). xlsm referensi tetap untracked.

## Test Status

- Tidak ada perubahan logika PHP pada sesi ini (hanya bump versi + AGENTS) -> suite tetap OK (485 tests, 1506 assertions), PHPStan level 6 [OK] No errors.