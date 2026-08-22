<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportRkasController;
use App\Http\Controllers\ImportRevisiController;
use App\Http\Controllers\JenisBelanjaController;
use App\Http\Controllers\KategoriJuknisController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MasterKodeRekeningController;
use App\Http\Controllers\MasterProgramController;
use App\Http\Controllers\MonitoringJuknisController;
use App\Http\Controllers\NotaBkuController;
use App\Http\Controllers\PengaturanSekolahController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecoveryCodeController;
use App\Http\Controllers\RkasController;
use App\Http\Controllers\RkasItemController;
use App\Http\Controllers\SumberDanaController;
use App\Http\Controllers\TahunAnggaranController;
use App\Http\Controllers\TelegramPengaturanController;
use App\Http\Controllers\TransaksiBkuController;
use App\Http\Controllers\TransaksiTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/monitoring-juknis', [MonitoringJuknisController::class, 'index'])->name('laporan.monitoring-juknis');

    Route::get('exports/{exportJob}/download', [ExportController::class, 'download'])->name('exports.download');
    Route::get('exports/{exportJob}/status', [ExportController::class, 'status'])->name('exports.status');

    Route::get('pengaturan-sekolah', [PengaturanSekolahController::class, 'edit'])->name('pengaturan-sekolah.edit');
    Route::put('pengaturan-sekolah', [PengaturanSekolahController::class, 'update'])->name('pengaturan-sekolah.update');

    Route::get('pengaturan/backup', [BackupController::class, 'index'])->name('pengaturan.backup.index');
    Route::post('pengaturan/backup/now', [BackupController::class, 'run'])->name('pengaturan.backup.now');
    Route::get('pengaturan/backup/download/{file}', [BackupController::class, 'download'])->name('pengaturan.backup.download');
    Route::get('pengaturan/riwayat-aktivitas', [AuditLogController::class, 'index'])->name('pengaturan.audit.index');

    Route::get('pengaturan/kode-pemulihan', [RecoveryCodeController::class, 'index'])->name('pengaturan.recovery-code.index');
    Route::post('pengaturan/kode-pemulihan/regenerate', [RecoveryCodeController::class, 'regenerate'])->name('pengaturan.recovery-code.regenerate');

    Route::get('pengaturan/telegram', [TelegramPengaturanController::class, 'index'])->name('pengaturan.telegram.index');
    Route::put('pengaturan/telegram', [TelegramPengaturanController::class, 'update'])->name('pengaturan.telegram.update');
    Route::post('pengaturan/telegram/test', [TelegramPengaturanController::class, 'test'])->name('pengaturan.telegram.test');

    Route::get('tentang', [AboutController::class, 'index'])->name('tentang.index');
    Route::get('tentang/cek-pembaruan', [AboutController::class, 'check'])->name('tentang.check');

    Route::get('rkas-items/select2', [RkasItemController::class, 'select2'])->name('rkas-items.select2');

    Route::get('/rkas', [RkasController::class, 'index'])->name('rkas.index');
    Route::post('/rkas/hapus-semua', [RkasController::class, 'destroyAll'])->name('rkas.hapus-semua');
    Route::get('/rkas/{rkasItem}/edit', [RkasController::class, 'edit'])->name('rkas.edit');
    Route::put('/rkas/{rkasItem}', [RkasController::class, 'update'])->name('rkas.update');
    Route::delete('/rkas/{rkasItem}', [RkasController::class, 'destroy'])->name('rkas.destroy');

    Route::get('import-rkas', [ImportRkasController::class, 'index'])->name('import-rkas.index');
    Route::post('import-rkas', [ImportRkasController::class, 'store'])->name('import-rkas.store');
    Route::get('import-rkas/download-template', [ImportRkasController::class, 'downloadTemplate'])->name('import-rkas.download-template');
    Route::get('import-rkas/status', [ImportRkasController::class, 'status'])->name('import-rkas.status');

    Route::get('import-revisi', [ImportRevisiController::class, 'index'])->name('import-revisi.index');
    Route::post('import-revisi', [ImportRevisiController::class, 'store'])->name('import-revisi.store');
    Route::get('import-revisi/{rkasRevisi}', [ImportRevisiController::class, 'show'])->name('import-revisi.show');

    Route::get('transaksi-bku', [TransaksiBkuController::class, 'index'])->name('transaksi-bku.index');
    Route::get('transaksi-bku/create', [TransaksiBkuController::class, 'create'])->name('transaksi-bku.create');
    Route::post('transaksi-bku', [TransaksiBkuController::class, 'store'])->name('transaksi-bku.store');
    Route::get('transaksi-bku/{transaksiBku}/edit', [TransaksiBkuController::class, 'edit'])->name('transaksi-bku.edit');
    Route::put('transaksi-bku/{transaksiBku}', [TransaksiBkuController::class, 'update'])->name('transaksi-bku.update');
    Route::delete('transaksi-bku/{transaksiBku}', [TransaksiBkuController::class, 'destroy'])->name('transaksi-bku.destroy');
    Route::post('transaksi-bku/hapus-semua', [TransaksiBkuController::class, 'destroyAll'])->name('transaksi-bku.hapus-semua');
    Route::get('transaksi-bku/{transaksiBku}/cetak-kwitansi', [TransaksiBkuController::class, 'cetakKwitansi'])->name('transaksi-bku.cetak-kwitansi');
    Route::post('transaksi-bku/cetak-kwitansi-batch', [TransaksiBkuController::class, 'cetakKwitansiBatch'])->name('transaksi-bku.cetak-kwitansi-batch');

    Route::get('transaksi-template', [TransaksiTemplateController::class, 'index'])->name('transaksi-template.index');
    Route::post('transaksi-template', [TransaksiTemplateController::class, 'store'])->name('transaksi-template.store');
    Route::delete('transaksi-template/{transaksiTemplate}', [TransaksiTemplateController::class, 'destroy'])->name('transaksi-template.destroy');
    Route::get('transaksi-template/{transaksiTemplate}/apply', [TransaksiTemplateController::class, 'apply'])->name('transaksi-template.apply');

    Route::get('nota-bku', [NotaBkuController::class, 'index'])->name('nota-bku.index');
    Route::get('nota-bku/items', [NotaBkuController::class, 'items'])->name('nota-bku.items');
    Route::get('nota-bku/{notaBku}', [NotaBkuController::class, 'show'])->name('nota-bku.show');
    Route::delete('nota-bku/{notaBku}', [NotaBkuController::class, 'destroy'])->name('nota-bku.destroy');
    Route::get('nota-bku/{notaBku}/cetak', [NotaBkuController::class, 'cetak'])->name('nota-bku.cetak');

    Route::get('laporan/bku', [LaporanController::class, 'bku'])->name('laporan.bku');
    Route::get('laporan/bku/export-excel', [LaporanController::class, 'bkuExportExcel'])->name('laporan.bku.export-excel');
    Route::get('laporan/rekap-rekening', [LaporanController::class, 'rekapRekening'])->name('laporan.rekap-rekening');
    Route::get('laporan/rekap-rekening/export-excel', [LaporanController::class, 'rekapRekeningExportExcel'])->name('laporan.rekap-rekening.export-excel');
    Route::get('laporan/rekap-kuartal', [LaporanController::class, 'rekapKuartal'])->name('laporan.rekap-kuartal');
    Route::get('laporan/rekap-kuartal/export-excel', [LaporanController::class, 'rekapKuartalExportExcel'])->name('laporan.rekap-kuartal.export-excel');
    Route::get('laporan/rekap-siplah', [LaporanController::class, 'rekapSiplah'])->name('laporan.rekap-siplah');
    Route::get('laporan/rekap-siplah/export-excel', [LaporanController::class, 'rekapSiplahExportExcel'])->name('laporan.rekap-siplah.export-excel');
    Route::get('laporan/bku/preview', [LaporanController::class, 'bkuWeb'])->name('laporan.bku.preview');
    Route::get('laporan/rekap-rekening/preview', [LaporanController::class, 'rekapRekeningWeb'])->name('laporan.rekap-rekening.preview');
    Route::get('laporan/rekap-kuartal/preview', [LaporanController::class, 'rekapKuartalWeb'])->name('laporan.rekap-kuartal.preview');
    Route::get('laporan/rekap-siplah/preview', [LaporanController::class, 'rekapSiplahWeb'])->name('laporan.rekap-siplah.preview');

    Route::resource('tahun-anggaran', TahunAnggaranController::class)->except(['show']);
    Route::post('/tahun-anggaran/{tahunAnggaran}/set-active', [TahunAnggaranController::class, 'setActive'])->name('tahun-anggaran.set-active');

    Route::resource('sumber-dana', SumberDanaController::class)->except(['show']);
    Route::resource('jenis-belanja', JenisBelanjaController::class)->except(['show']);

    Route::resource('master-program', MasterProgramController::class)->except(['show']);
    Route::post('master-program/import', [MasterProgramController::class, 'import'])->name('master-program.import');
    Route::post('master-program/hapus-semua', [MasterProgramController::class, 'destroyAll'])->name('master-program.hapus-semua');

    Route::get('master-kode-rekening/download-template', [MasterKodeRekeningController::class, 'downloadTemplate'])->name('master-kode-rekening.download-template');
    Route::post('master-kode-rekening/import', [MasterKodeRekeningController::class, 'import'])->name('master-kode-rekening.import');
    Route::post('master-kode-rekening/hapus-semua', [MasterKodeRekeningController::class, 'destroyAll'])->name('master-kode-rekening.hapus-semua');
    Route::resource('master-kode-rekening', MasterKodeRekeningController::class)->except(['show']);

    // Kategori Juknis BOSP (route statis "pemetaan" DI ATAS route param agar tidak tertelan {kategoriJuknis})
    Route::prefix('pengaturan/kategori-juknis')->name('pengaturan.kategori-juknis.')->group(function (): void {
        Route::get('pemetaan', [KategoriJuknisController::class, 'pemetaan'])->name('pemetaan');
        Route::post('pemetaan', [KategoriJuknisController::class, 'simpanPemetaan'])->name('simpan-pemetaan');
        Route::get('/', [KategoriJuknisController::class, 'index'])->name('index');
        Route::post('/', [KategoriJuknisController::class, 'store'])->name('store');
        Route::get('/{kategoriJuknis}/edit', [KategoriJuknisController::class, 'edit'])->name('edit');
        Route::put('/{kategoriJuknis}', [KategoriJuknisController::class, 'update'])->name('update');
        Route::delete('/{kategoriJuknis}', [KategoriJuknisController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
