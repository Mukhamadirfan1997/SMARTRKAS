<?php

namespace App\Http\Controllers;

use App\Imports\MasterProgramImport;
use App\Models\AuditLog;
use App\Models\MasterProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MasterProgramController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $query = MasterProgram::with('parent');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('kode', 'LIKE', "%{$search}%");
            });
        }

        $masterPrograms = $query->orderBy('kode')->paginate(50)->withQueryString();

        return view('master-program.index', compact('masterPrograms'));
    }

    public function import(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $import = new MasterProgramImport;
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('Gagal import master program: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->with('error', 'Gagal membaca file. Pastikan file yang diupload adalah file Excel yang valid.');
        }

        Cache::forget('master_programs');

        $msg = "Import selesai: {$import->importedCount} data berhasil diimport.";

        if ($import->skippedCount > 0) {
            $msg .= " {$import->skippedCount} baris dilewati (kosong/error).";
        }

        $errors = $import->getAllErrors();
        if (!empty($errors)) {
            return back()
                ->with('warning', $msg)
                ->with('import_errors', array_slice($errors, 0, 10));
        }

        AuditLog::record('master_program', 'import', [
            'baris_berhasil' => $import->importedCount,
            'baris_dilewati' => $import->skippedCount,
        ]);

        return back()->with('success', $msg);
    }

    public function create(): \Illuminate\View\View
    {
        $parentPrograms = MasterProgram::all();

        return view('master-program.create', compact('parentPrograms'));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'kode' => 'required|unique:master_program,kode',
            'nama' => 'required',
            'program' => 'nullable|string',
            'sub_program' => 'nullable|string',
            'parent_id' => 'nullable|exists:master_program,id',
            'level' => 'required|integer',
        ]);

        $masterProgram = MasterProgram::create($validated);

        AuditLog::record('master_program', 'create', [
            'kode' => $masterProgram->kode,
            'nama' => $masterProgram->nama,
            'level' => $masterProgram->level,
        ]);

        Cache::forget('master_programs');

        return redirect()->route('master-program.index')->with('success', 'Master Program berhasil ditambahkan.');
    }

    public function edit(MasterProgram $masterProgram): \Illuminate\View\View
    {
        $parentPrograms = MasterProgram::where('id', '!=', $masterProgram->id)->get();

        return view('master-program.edit', compact('masterProgram', 'parentPrograms'));
    }

    public function update(Request $request, MasterProgram $masterProgram): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'kode' => 'required|unique:master_program,kode,' . $masterProgram->id,
            'nama' => 'required',
            'program' => 'nullable|string',
            'sub_program' => 'nullable|string',
            'parent_id' => 'nullable|exists:master_program,id',
            'level' => 'required|integer',
        ]);

        $dataLama = $masterProgram->only(['kode', 'nama', 'level']);

        $masterProgram->update($validated);

        AuditLog::record('master_program', 'update', $masterProgram->only(['kode', 'nama', 'level']), $dataLama);

        Cache::forget('master_programs');

        return redirect()->route('master-program.index')->with('success', 'Master Program berhasil diupdate.');
    }

    public function destroy(MasterProgram $masterProgram): \Illuminate\Http\RedirectResponse
    {
        $data = $masterProgram->only(['kode', 'nama', 'level']);

        $masterProgram->delete();

        AuditLog::record('master_program', 'delete', $data);

        Cache::forget('master_programs');

        return back()->with('success', 'Master Program berhasil dihapus.');
    }

    public function destroyAll(Request $request): \Illuminate\Http\RedirectResponse
    {
        $query = MasterProgram::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('kode', 'LIKE', "%{$search}%");
            });
        }

        $count = $query->count();
        if ($count === 0) {
            return back()->with('error', 'Tidak ada Master Program yang cocok untuk dihapus.');
        }

        $query->delete();

        AuditLog::record('master_program', 'delete_bulk', ['jumlah_item' => $count]);

        Cache::forget('master_programs');

        return back()->with('success', "{$count} Master Program berhasil dihapus.");
    }
}
