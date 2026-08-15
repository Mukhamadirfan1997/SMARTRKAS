# Laporan Perubahan Fitur & Dampaknya

> Tanggal: 13 Agu 2026 · Commit: `8c96eb0`, `b748a4b`, `7076b98` (lokal, BELUM push)
> Cakupan: seluruh pekerjaan revisi form BKU + nota multi-item + pencarian ala picker (dashboard → laporan, semua cetak).

> **Tambahan 15 Agu 2026** — Ringkasan Capaian & Realisasi per Jenis Belanja di halaman Data RKAS (lihat bagian 6).

---

## 1. Ringkasan Perubahan

| # | Perubahan | Commit | Status |
|---|-----------|--------|--------|
| 1 | Revisi besar penyatuan form BKU pengeluaran + nota multi-item (1 nota = 1 transaksi total, atribusi via `nota_bku_item`) | `8c96eb0` + sebelumnya | Sudah rilis batch sebelumnya |
| 2 | Form penerimaan sederhana (tanpa picker item + kalkulator) · Riwayat/Detail Nota menampilkan No. Bukti · Edit Nota menampilkan daftar item | `8c96eb0` | OK |
| 3 | Header tabel edit sejajar (CSS) · tombol Riwayat Nota · isi kolom Kode Kegiatan/Rekening/Jenis Belanja/Volume/Satuan untuk baris nota di index BKU | `b748a4b` | OK |
| 4 | Pencarian Kegiatan & Rekening diganti **picker ala item RKAS** (input teks + dropdown + hidden input) di create & edit BKU | `7076b98` | OK |

### Catatan penting revisi (acuan keputusan user)
- **1 nota = 1 transaksi `TransaksiBku` (total)**: realisasi per item ditelusuri lewat `nota_bku_item` (`RealisasiQuery` UNION transaksi + nota). Transaksi nota ber-`rkas_item_id` = NULL; kolom kegiatan/program/sub-program/kode rekening di cetak diambil dari **nota** (fallback `notaBku->kegiatan`/`notaBku->kodeRekening`).
- **Tepat 1 item dicentang** → transaksi single-item (override tetap tersedia, kwitansi terkunci saat over-budget).
- **2+ item dicentang** → `NotaBku` + guard all-or-nothing (tanpa override).
- Route/view `nota-bku/create` DIHAPUS (semua masuk via `/transaksi-bku/create`). Halaman index/show/cetak nota tetap ada.

---

## 2. Verifikasi yang Dijalankan (dari kondisi final)

### 2.1 Suite otomatis
- PHPUnit full: **`OK (365 tests, 1059 assertions)`**
- PHPStan level 6: **`[OK] No errors`**
- `php artisan view:cache`: **OK**
- `git status` setelah semua commit: **bersih** (tidak ada perubahan menggantung)

### 2.2 Probe render + cetak + export (69 cek, semua PASS) — terhadap DB dev nyata `database/database.sqlite`
Lingkup probe meniru alur HTTP server (login `admin@sekolah.test`, render view/controller, `Http::call` untuk AJAX, `Excel::store` untuk export, PDF via stream).

| Area | Cek yang Lolos |
|------|----------------|
| **BKU index** | judul, tombol Riwayat Nota (btn), tombol "Tambah Pembelanjaan" dihapus, no_bukti nota tampil |
| **BKU create** | form+jumlah, picker kegiatan & rekening (search+hidden+results), `initEntityPicker`+event, `<select>` lama dihapus, rkas picker (penerimaan) tetap ada, override, no_invoice_siplah, hint format angka |
| **BKU edit single** | picker ter-render, hidden value preset, tanpa panel nota |
| **BKU edit nota** | panel read-only nota, tabel rincian item, tanpa picker (tidak dirender) |
| **Nota index** | tombol Kembali, kolom No. Bukti, tombol Tambah Transaksi |
| **Nota show** | No. Bukti di Informasi, card "Transaksi BKU Terkait" dihapus, KPI Total Belanja, Rincian Item Belanja |
| **Cetak nota PDF** | HTTP 200 + `%PDF` valid; judul "Rincian Belanja", field No. BPU |
| **Kwitansi** | single PDF valid, batch PDF valid |
| **Dashboard** | render OK |
| **Laporan (4)** | web render + **PDF valid** untuk BKU, Rekap Rekening, Rekap Kuartal, Rekap SIPLAH |
| **Export Excel (4)** | file `.xlsx` valid (`PK` header, >1KB) untuk BkuExport, RekapRekeningExport, RekapKuartalExport, RekapSiplahExport |
| **AJAX** | `/nota-bku/items` (filter kegiatan+rekening+bulan) → `{results:[...]}`; `/rkas-items/select2` → `{results:[...]}` |
| **Master & pengaturan (21 halaman)** | rkas index/edit, master-program, master-kode-rekening, sumber-dana, tahun-anggaran, jenis-belanja (index+create), pengaturan-sekolah, backup, riwayat aktivitas, telegram, kode-pemulihan, tentang, import-rkas — semua render tanpa error |

---

## 3. Dampak per Fungsi/Halaman

| Halaman/Fungsi | Dampak perubahan | Risiko |
|----------------|------------------|--------|
| **Tambah Transaksi (BKU)** | Penerimaan = isi nominal langsung (tanpa item/kalkulator). Pengeluaran = Kegiatan → Rekening → checklist item (1 vs 2+). Pencarian kegiatan/rekening pakai picker baru | Tidak ada |
| **Edit Transaksi** | Disamakan dengan pola create; nota → panel read-only + rincian item | Tidak ada |
| **Index BKU** | Baris nota kini menampilkan Kode Kegiatan/Rekening/Jenis Belanja/Volume (total qty)/Satuan (`-` bila campur) | Tidak ada |
| **Riwayat/Detail Nota** | No. Bukti tampil di header; kolom no_bukti di index; card transaksi terkait dihapus | Tidak ada |
| **Cetak kwitansi** | Program/Sub Program/Kode Rekening terisi dari nota bila transaksi nota; field Uraian dihapus (kotak "Untuk" saja) | Tidak ada |
| **Cetak nota (Rincian Belanja)** | Judul baru; field No. BPU (bukan No. Nota); baris Program/Sub Program/Kode Rekening | Tidak ada |
| **Dashboard & Laporan** | Realisasi memakai `RealisasiQuery` (transaksi + nota). Total realisasi ≡ total pengeluaran BKU (invariant teruji) | Rendah — perubahan di `LaporanController`/export memakai query baru |
| **Export Excel** | Bku/RekapRekening/RekapKuartal/RekapSiplah — output valid, pola cast fallback sempat tercatat sebagai temuan inkonsistensi (dipertahankan, lihat catatan) | Rendah |

---

## 4. Keterbatasan / Yang BELUM diverifikasi (jujur sesuai SOP)

1. **Belum ada uji manual di browser nyata** untuk alur klik penuh picker (dropdown, debounce, Enter, klik-luar, tombol Bersihkan). Verifikasi saat ini: render HTML + logika JS direview baris per baris + HTTP live di sesi sebelumnya untuk alur nota.
2. **`npm run build` TIDAK dijalankan ulang** sesi ini — tidak ada perubahan aset CSS/JS baru (perubahan `app.css` sudah di-build & di-commit pada `b748a4b`).
3. **Export Excel diuji via class langsung** (`Excel::store`), bukan via alur controller async (`ExportJob` + `GenerateExportJob`). Alur async sudah dicover test suite (`ExportTest`) dan kerja synchron (tanpa worker) sejak v0.3.1.
4. **Probe memakai DB dev** (bukan DB produksi Roaming); data dev berisi 139 program / 276 rekening / nota 3 / transaksi 9 — cukup representatif untuk render, tapi kombinasi data ekstrem (halaman kosong, pagination banyak) hanya dicover test suite.
5. Temuan lama "pola penulisan kolom nominal export tidak seragam" dipertahankan tanpa perubahan (sudah dicatat AGENTS).

---

## 5. Status Rilis

- Semua commit **lokal saja** (`8c96eb0`, `b748a4b`, `7076b98` di atas `master` HEAD). **Belum push, belum build installer, belum rilis GitHub** — menunggu konfirmasi user sesuai SOP.
- Versi app: **0.4.2** (belum di-bump).

---

## 6. Tambahan 15 Agu — Ringkasan Capaian & Realisasi per Jenis Belanja di Data RKAS

Menambahkan blok "Ringkasan Capaian" + "Realisasi per Jenis Belanja" (sebelumnya hanya ada di Dashboard) ke halaman **Data RKAS**, di atas card filter "Daftar RKAS". Perhitungan **identik dengan Dashboard** (keputusan user: "hitungannya tetap seperti itu").

### Perubahan kode
- `app/Http/Controllers/RkasController.php` — `index()`:
  - `$filteredIdsNoBulan = (clone $baseQuery)->pluck('id')` diambil SEBELUM `whereHas('bulanRencana')` → dipakai breakdown per jenis belanja secara **kumulatif** (sama seperti chart dashboard yang tidak memfilter bulan).
  - `$persentaseCapaian = totalRealisasi / totalJumlah * 100` (bulan-aware, mengikuti total halaman RKAS).
  - `$jenisBelanjaRealisasi` = `RealisasiQuery::base()` + join `rkas_item`/`master_kode_rekening`/`jenis_belanja`, `groupBy('jenis_belanja.nama')`, `orderByDesc('total')` — pola persis `DashboardController::chartData` (baris 95-103), termasuk nota multi-item.
  - Compact list baru: `persentaseCapaian`, `jenisBelanjaRealisasi`.
- `resources/views/rkas/index.blade.php` — blok `<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">`:
  - Card **"Ringkasan Capaian"**: progress bar gradient (indigo→emerald) + tile Rencana/Realisasi/Sisa (sisa merah bila negatif).
  - Card **"Realisasi per Jenis Belanja"**: daftar label + `Rp … (persen%)` + progress bar biru (persen dari total realisasi); empty-state "Belum ada realisasi".
  - Kondisi tampil: `$totalJumlah > 0 || $jenisBelanjaRealisasi->isNotEmpty()`.
- `resources/views/pengaturan/tentang.blade.php` — "Petunjuk Penggunaan Singkat" diperbarui: item Data RKAS menyebut filter + pantauan capaian per jenis belanja; item BKU menyebut alur pengeluaran Kegiatan→Rekening→centang item dan Nota Multi-Item.

### Verifikasi
- **Suite**: PHPUnit full `OK (389 tests, 1150 assertions)` (baru `test_index_menampilkan_ringkasan_capaian_dan_realisasi_per_jenis_belanja` di `RkasControllerTest`), PHPStan level 6 `[OK] No errors`, `php artisan view:cache` OK.
- **HTTP live** terhadap salinan DB (port 8027, route temp `/__shot/rkas`, sudah dihapus): blok tampil, capaian 6.9% (12.388.000/180.320.000), breakdown per jenis: Belanja Modal Peralatan & Mesin 7.500.000 (60.5%), Barang Persediaan 1.987.000 (16%), Jasa 1.275.000 (10.3%), Cetak 1.176.000 (9.5%), Perjalanan Dinas 450.000 (3.6%) — **jumlah = total realisasi 12.388.000** (selisih 0).
- **Tanpa bulan** (`?bulan=` kosong): rencana tahunan `jumlah` + realisasi kumulatif; **dengan bulan**: rencana per bulan `rkas_item_bulan` + realisasi bulan tsb, dan item tanpa rencana di bulan tsb disembunyikan.

### Dampak & risiko
| Halaman/Fungsi | Dampak | Risiko |
|----------------|--------|--------|
| Data RKAS | Ringkasan capaian + breakdown per jenis belanja di atas filter; perhitungan sama dengan dashboard | Rendah |
| Dashboard | Tidak diubah oleh sesi ini (hanya basis perhitungan yang sama) | Tidak ada |
| Halaman lain | Tidak ada perubahan (controller/view lain tidak tersentuh) | Tidak ada |

### Keterbatasan
- Breakdown per jenis belanja **kumulatif** (tanpa filter bulan) — konsisten dengan chart dashboard; ringkasan capaian tetap bulan-aware mengikuti total halaman. Ini disengaja sesuai keputusan user.
- Verifikasi di atas memakai salinan DB (bukan DB produksi Roaming); DB produksi tidak diubah.
