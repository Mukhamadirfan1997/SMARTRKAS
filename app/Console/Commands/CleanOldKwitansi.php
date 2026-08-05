<?php

namespace App\Console\Commands;

use App\Models\Kwitansi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOldKwitansi extends Command
{
    protected $signature = 'kwitansi:clean {years=2 : Hapus kwitansi lebih dari N tahun}';

    protected $description = 'Hapus kwitansi dan file PDF yang lebih lama dari N tahun';

    public function handle(): void
    {
        $years = (int) $this->argument('years');
        $cutoff = now()->subYears($years);

        $oldKwitansi = Kwitansi::where('created_at', '<', $cutoff)->get();

        if ($oldKwitansi->isEmpty()) {
            $this->info('Tidak ada kwitansi yang perlu dibersihkan.');

            return;
        }

        $deletedFiles = 0;
        $deletedRecords = 0;

        foreach ($oldKwitansi as $kwitansi) {
            if ($kwitansi->file_pdf_path && Storage::disk('public')->exists($kwitansi->file_pdf_path)) {
                Storage::disk('public')->delete($kwitansi->file_pdf_path);
                $deletedFiles++;
            }
            $kwitansi->delete();
            $deletedRecords++;
        }

        $this->info("Dibersihkan: {$deletedRecords} record kwitansi, {$deletedFiles} file PDF (>{$years} tahun).");
    }
}
