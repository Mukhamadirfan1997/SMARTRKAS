<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRkasRevisiImport;
use App\Models\ImportLog;
use App\Models\RkasRevisi;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ImportRevisiController extends Controller
{
    public function index(): View
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $riwayat = RkasRevisi::with(['sumberDana', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('import-revisi.index', compact('tahunAnggaranAktif', 'riwayat'));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'nullable|file|mimes:xlsx,xls,csv',
            'sumber_dana_id' => 'required|exists:sumber_dana,id',
            'jenis' => 'required|in:pergeseran,pak',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $tahunAnggaranAktif = TahunAnggaran::getActive();
        if (!$tahunAnggaranAktif) {
            return back()->with('error', 'Tahun anggaran aktif tidak ditemukan.');
        }

        $uploadedFiles = [];
        $skippedFiles = [];

        /** @var array<int|string, \Illuminate\Http\UploadedFile|null> $files */
        $files = $request->file('files', []);

        foreach ($files as $bulan => $file) {
            if (!is_numeric($bulan) || (int) $bulan < 1 || (int) $bulan > 12) {
                continue;
            }
            if ($file === null || !$file->isValid()) {
                continue;
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                $skippedFiles[] = $file->getClientOriginalName() . ' (max 5MB)';
                continue;
            }

            $fileName = time() . '_' . $bulan . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('import_revisi', $fileName);

            $log = ImportLog::create([
                'tahun_anggaran_id' => $tahunAnggaranAktif->id,
                'sumber_dana_id' => $validated['sumber_dana_id'],
                'bulan' => (int) $bulan,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'status' => 'pending',
                'uploaded_by' => auth()->id(),
            ]);

            $uploadedFiles[] = [
                'log_id' => $log->id,
                'path' => Storage::disk('local')->path($filePath),
            ];
        }

        if ($uploadedFiles === []) {
            $message = 'Tidak ada file yang diproses. ';
            $message .= !empty($skippedFiles) ? 'Semua file terlalu besar (max 5MB): ' . implode(', ', $skippedFiles) . '.' : 'Tidak ada file yang dipilih/diupload.';

            return back()->with('error', $message);
        }

        $result = (new ProcessRkasRevisiImport(
            $tahunAnggaranAktif->id,
            $validated['sumber_dana_id'],
            $validated['jenis'],
            $validated['tanggal'],
            $validated['keterangan'] ?? null,
            $uploadedFiles,
            auth()->id(),
        ))->handle();

        $message = '';
        if (!empty($skippedFiles)) {
            $message .= ' File berikut dilewati karena terlalu besar: ' . implode(', ', $skippedFiles) . '.';
        }

        if (!$result['ok']) {
            $errors = implode('; ', array_slice($result['errors'], 0, 5));

            return back()->with('error', 'Revisi DITOLAK. Tidak ada perubahan yang diterapkan: ' . $errors . $message);
        }

        return back()->with(
            'success',
            'Revisi ' . $result['no_revisi'] . ' berhasil diterapkan. Rencana RKAS diperbarui dan item yang tidak ada di file dibiarkan.' . $message
        );
    }

    public function show(RkasRevisi $rkasRevisi): View
    {
        $rkasRevisi->load(['sumberDana', 'tahunAnggaran', 'createdBy', 'items.rkasItem']);

        return view('import-revisi.show', ['rkasRevisi' => $rkasRevisi]);
    }
}
