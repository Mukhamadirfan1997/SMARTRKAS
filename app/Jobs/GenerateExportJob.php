<?php

namespace App\Jobs;

use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $exportParams
     */
    public function __construct(
        protected int $exportJobId,
        protected string $exportClass,
        protected array $exportParams,
        protected string $filename,
    ) {}

    public function handle(): void
    {
        $exportJob = ExportJob::findOrFail($this->exportJobId);

        try {
            set_time_limit(0);
            ini_set('memory_limit', -1);

            $instance = new $this->exportClass(...$this->exportParams);

            $relativePath = 'exports/' . $this->exportJobId . '_' . $this->filename;
            Excel::store($instance, $relativePath, 'public');

            $exportJob->update([
                'status' => 'completed',
                'filename' => $this->filename,
                'file_path' => $relativePath,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
