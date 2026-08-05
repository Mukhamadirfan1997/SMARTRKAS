<?php

namespace App\Http\Controllers;

use App\Models\ExportJob;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    /** @return \Symfony\Component\HttpFoundation\StreamedResponse */
    public function download(ExportJob $exportJob)
    {
        if ($exportJob->user_id !== auth()->id()) {
            abort(403);
        }

        if ($exportJob->status !== 'completed' || !$exportJob->file_path) {
            abort(404, 'Export belum selesai diproses.');
        }

        if (!Storage::disk('public')->exists($exportJob->file_path)) {
            abort(404, 'File export tidak ditemukan.');
        }

        return Storage::disk('public')->download($exportJob->file_path, $exportJob->filename);
    }

    public function status(ExportJob $exportJob): \Illuminate\Http\JsonResponse
    {
        if ($exportJob->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json([
            'id' => $exportJob->id,
            'status' => $exportJob->status,
            'filename' => $exportJob->filename,
            'error_message' => $exportJob->error_message,
            'completed_at' => $exportJob->completed_at?->toISOString(),
        ]);
    }
}
