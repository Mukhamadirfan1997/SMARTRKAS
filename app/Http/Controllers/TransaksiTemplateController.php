<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\TransaksiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransaksiTemplateController extends Controller
{
    public function index(): View
    {
        $templates = TransaksiTemplate::with('kodeRekening', 'kegiatan', 'sumberDana', 'createdByUser')
            ->orderBy('nama_template')
            ->get();

        return view('transaksi-template.index', compact('templates'));
    }

    /**
     * Simpan template baru dari baris transaksi BKU.
     * Hanya boleh dari transaksi single-item (ada rkas_item_id).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaksi_bku_id' => 'required|string|exists:transaksi_bku,id',
            'nama_template' => 'required|string|max:255',
        ], [
            'transaksi_bku_id.required' => 'Transaksi tidak valid.',
            'transaksi_bku_id.exists' => 'Transaksi tidak ditemukan.',
            'nama_template.required' => 'Nama template wajib diisi.',
            'nama_template.max' => 'Nama template maksimal 255 karakter.',
        ]);

        $transaksi = TransaksiBku::with('rkasItem')->findOrFail($validated['transaksi_bku_id']);

        if ($transaksi->rkas_item_id === null) {
            return redirect()->route('transaksi-bku.index')
                ->with('error', 'Template hanya bisa dibuat dari transaksi single-item (bukan nota multi-item).');
        }

        $rkasItem = $transaksi->rkasItem;
        if ($rkasItem === null) {
            return redirect()->route('transaksi-bku.index')
                ->with('error', 'Item RKAS asal tidak ditemukan.');
        }

        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        TransaksiTemplate::create([
            'nama_template' => trim($validated['nama_template']),
            'kode_rekening_id' => $rkasItem->kode_rekening_id,
            'kegiatan_id' => $rkasItem->program_id,
            'uraian_item_snapshot' => $rkasItem->uraian,
            'toko_penerima' => $transaksi->toko_penerima,
            'metode_pengadaan' => $transaksi->metode_pengadaan,
            'uraian_dasar' => $transaksi->uraian,
            'sumber_dana_id' => $transaksi->sumber_dana_id,
            'created_by' => $user->id,
        ]);

        AuditLog::record('transaksi_template', 'create', [
            'nama_template' => trim($validated['nama_template']),
            'transaksi_asal' => $transaksi->no_bukti,
        ], null, $user->id);

        return redirect()->route('transaksi-bku.index')
            ->with('success', 'Template "' . trim($validated['nama_template']) . '" berhasil disimpan.');
    }

    public function destroy(TransaksiTemplate $transaksiTemplate): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $nama = $transaksiTemplate->nama_template;

        AuditLog::record('transaksi_template', 'delete', [
            'nama_template' => $nama,
        ], null, $user->id);

        $transaksiTemplate->delete();

        return redirect()->route('transaksi-template.index')
            ->with('success', 'Template "' . $nama . '" berhasil dihapus.');
    }

    /**
     * AJAX: Cari item RKAS di tahun anggaran aktif berdasarkan template.
     * Mengembalikan data item yang cocok (untuk prefill form), atau null
     * bila tidak ada yang cocok (form tetap bisa dipakai manual).
     */
    public function apply(TransaksiTemplate $transaksiTemplate): JsonResponse
    {
        $item = $transaksiTemplate->cariItemDiTahunAktif();

        return response()->json([
            'template_id' => $transaksiTemplate->id,
            'nama_template' => $transaksiTemplate->nama_template,
            'kegiatan_id' => $transaksiTemplate->kegiatan_id,
            'kegiatan_nama' => $transaksiTemplate->kegiatan
                ? $transaksiTemplate->kegiatan->kode . ' - ' . $transaksiTemplate->kegiatan->nama
                : '',
            'kode_rekening_id' => $transaksiTemplate->kode_rekening_id,
            'kode_rekening_nama' => $transaksiTemplate->kodeRekening
                ? $transaksiTemplate->kodeRekening->kode . ' - ' . $transaksiTemplate->kodeRekening->nama
                : '',
            'item_ditemukan' => $item !== null,
            'item' => $item ? [
                'id' => $item->id,
                'text' => $item->no_urut . '. ' . $item->uraian,
                'uraian' => $item->uraian,
                'satuan' => $item->satuan,
            ] : null,
            'toko_penerima' => $transaksiTemplate->toko_penerima,
            'metode_pengadaan' => $transaksiTemplate->metode_pengadaan,
            'uraian_dasar' => $transaksiTemplate->uraian_dasar,
        ]);
    }
}
