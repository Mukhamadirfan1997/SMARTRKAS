<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ImportLog;
use App\Models\AuditLog;
use App\Imports\RkasImport;
use App\Imports\RkasImportHeaderDetector;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\RkasItem;
use Illuminate\Support\Facades\Log;

/**
 * Proses import file RKAS. Sengaja TIDAK mengimplementasikan ShouldQueue
 * sehingga berjalan sinkron lewat ::dispatch() (desktop offline tanpa worker).
 */
class ProcessRkasImport
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $importLogId;
    public string $filePath;

    public function __construct(string $importLogId, string $filePath)
    {
        $this->importLogId = $importLogId;
        $this->filePath = $filePath;
    }

    public function handle(): void
    {
        $log = ImportLog::find($this->importLogId);
        if ($log === null) {
            return;
        }

        if ($log->tahun_anggaran_id === '' || $log->bulan === null || $log->sumber_dana_id === null) {
            $log->update(['status' => 'failed', 'finished_at' => now()]);
            return;
        }

        $log->update(['status' => 'processing']);

        try {
            // Idempotensi: hapus Rencana bulan yang diimpor saja
            DB::transaction(function () use ($log): void {
                $rkasIds = RkasItem::where('tahun_anggaran_id', $log->tahun_anggaran_id)
                                    ->pluck('id');

                if ($rkasIds->isNotEmpty()) {
                    \App\Models\RkasItemBulan::whereIn('rkas_item_id', $rkasIds)
                                             ->where('bulan', $log->bulan)
                                             ->delete();
                }
            });

            // Deteksi baris header + posisi kolom otomatis (file PRD memakai 2 baris header)
            $header = RkasImportHeaderDetector::detectColumns($this->filePath);

            // Jalankan import
            Excel::import(new RkasImport(
                $log->tahun_anggaran_id,
                $log->bulan,
                $log->sumber_dana_id,
                $log->id,
                null,
                $header['columns'],
                $header['start_row']
            ), $this->filePath);

            $log->refresh();

            if ($log->baris_berhasil === 0 || $log->baris_berhasil === null) {
                $err = $log->error_detail ?? [];
                $err[] = "Tidak ada data yang berhasil diimpor. Periksa format file Excel — pastikan kolom sesuai template (No Urut, Kode Rekening, Kode Program, Uraian, Volume, Satuan, Tarif, Jumlah).";
                $log->update([
                    'status' => 'failed',
                    'error_detail' => $err,
                    'total_baris' => $log->baris_gagal,
                    'finished_at' => now(),
                ]);
                Log::error("Import gagal: 0 baris berhasil — format file tidak sesuai template untuk bulan " . $log->bulan);
            } else {
                $log->update([
                    'status' => 'success',
                    'total_baris' => $log->baris_berhasil + ($log->baris_gagal ?? 0),
                    'finished_at' => now(),
                ]);

                // Rapi otomatis: no_urut unik + jumlah = total rencana (tanpa intervensi user)
                RkasItem::renumber($log->tahun_anggaran_id);
                RkasItem::syncJumlah($log->tahun_anggaran_id);

                AuditLog::create([
                    'user_id' => $log->uploaded_by,
                    'tabel' => 'import_rkas',
                    'aksi' => 'import',
                    'data_baru' => [
                        'bulan' => $log->bulan,
                        'baris_berhasil' => $log->baris_berhasil,
                        'total_baris' => $log->total_baris,
                    ],
                ]);
            }

            $this->cleanupFile($log);

        } catch (\Exception $e) {
            Log::error("Import gagal: " . $e->getMessage());
            $err = $log->error_detail ?? [];
            $err[] = "System Error: " . $e->getMessage();
            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            $this->cleanupFile($log);
        }
    }

    protected function cleanupFile(ImportLog $log): void
    {
        if ($log->file_path) {
            Storage::disk('local')->delete($log->file_path);
            $log->updateQuietly(['file_path' => null]);
        }
    }
}
