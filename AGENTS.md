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
