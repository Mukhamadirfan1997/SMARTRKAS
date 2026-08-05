<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportRkasController;
use App\Http\Controllers\JenisBelanjaController;
use App\Http\Controllers\MasterKodeRekeningController;
use App\Http\Controllers\MasterProgramController;
use App\Http\Controllers\PengaturanSekolahController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RkasController;
use App\Http\Controllers\RkasItemController;
use App\Http\Controllers\SumberDanaController;
use App\Http\Controllers\TahunAnggaranController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('pengaturan-sekolah', [PengaturanSekolahController::class, 'edit'])->name('pengaturan-sekolah.edit');
    Route::put('pengaturan-sekolah', [PengaturanSekolahController::class, 'update'])->name('pengaturan-sekolah.update');

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
});

require __DIR__.'/auth.php';
