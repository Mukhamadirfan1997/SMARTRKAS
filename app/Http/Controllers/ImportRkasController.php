<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ImportLog;
use App\Models\TahunAnggaran;
use App\Jobs\ProcessRkasImport;
use App\Exports\RkasTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class ImportRkasController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $logs = ImportLog::with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('import-rkas.index', compact('tahunAnggaranAktif', 'logs'));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'nullable|file|mimes:xlsx,xls,csv',
            'sumber_dana_id' => 'required|exists:sumber_dana,id',
        ]);

        $tahunAnggaranAktif = TahunAnggaran::getActive();
        if (!$tahunAnggaranAktif) {
            return back()->with('error', 'Tahun anggaran aktif tidak ditemukan.');
        }

        $uploadedCount = 0;
        $skippedFiles = [];

        /** @var array<int, \Illuminate\Http\UploadedFile|null> $files */
        $files = $request->file('files', []);

        foreach ($files as $bulan => $file) {
            if ($file === null || !$file->isValid()) {
                continue;
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                $skippedFiles[] = $file->getClientOriginalName() . ' (max 5MB)';
                continue;
            }

            $fileName = time() . '_' . $bulan . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('import_rkas', $fileName);

            $log = ImportLog::create([
                'tahun_anggaran_id' => $tahunAnggaranAktif->id,
                'sumber_dana_id' => $request->input('sumber_dana_id'),
                'bulan' => $bulan,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'status' => 'pending',
                'uploaded_by' => auth()->id(),
            ]);

            ProcessRkasImport::dispatch($log->id, Storage::disk('local')->path($filePath));
            $uploadedCount++;
        }

        $message = $uploadedCount . ' file RKAS berhasil diupload dan diproses.';
        if (!empty($skippedFiles)) {
            $message .= ' File berikut dilewati karena terlalu besar: ' . implode(', ', $skippedFiles) . '.';
        }

        if ($uploadedCount == 0) {
            return back()->with('error', 'Tidak ada file yang diproses. ' . (!empty($skippedFiles) ? 'Semua file terlalu besar (max 5MB).' : 'Tidak ada file yang dipilih/diupload.'));
        }

        return back()->with('success', $message);
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new RkasTemplateExport, 'template_import_rkas.xlsx');
    }

    public function status(): \Illuminate\Http\JsonResponse
    {
        $logs = ImportLog::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json($logs);
    }
}
