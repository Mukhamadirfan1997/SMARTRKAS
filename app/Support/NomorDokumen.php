<?php

namespace App\Support;

use App\Models\NotaBku;
use App\Models\PengaturanSekolah;
use App\Models\TransaksiBku;
use Carbon\Carbon;

/**
 * Generator nomor dokumen (no bukti BKU, no nota multi-item) di sisi server,
 * dengan pola yang identik dengan alur lama agar perilaku tidak berubah.
 */
final class NomorDokumen
{
    /**
     * No bukti transaksi BKU (BBU penerimaan / BPU pengeluaran),
     * format: {PREFIX}{seq:3} / {NPSN} / {MM} / {YYYY}, unik di tabel.
     */
    public static function noBukti(string $jenis, string $tanggal): string
    {
        $prefix = strtolower($jenis) === 'penerimaan' ? 'BBU' : 'BPU';
        $month = Carbon::parse($tanggal)->format('m');
        $year = Carbon::parse($tanggal)->format('Y');
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';

        $seq = TransaksiBku::where('jenis', $jenis)->count();

        do {
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT)
                . '/' . $npsn . '/' . $month . '/' . $year;
        } while (TransaksiBku::where('no_bukti', $candidate)->exists());

        return $candidate;
    }

    /**
     * No nota belanja (multi-item), format: NOTA-{seq:4} / {NPSN} / {MM} / {YYYY}.
     * Nota soft-deleted tetap dihitung agar nomor tidak terpakai ulang.
     */
    public static function noNota(string $tanggal): string
    {
        $month = Carbon::parse($tanggal)->format('m');
        $year = Carbon::parse($tanggal)->format('Y');
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';

        $seq = NotaBku::withTrashed()->count();

        do {
            $seq++;
            $candidate = 'NOTA-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT)
                . '/' . $npsn . '/' . $month . '/' . $year;
        } while (NotaBku::withTrashed()->where('no_nota', $candidate)->exists());

        return $candidate;
    }
}