<?php

namespace App\Support;

use App\Models\NotaBku;
use App\Models\PengaturanSekolah;
use App\Models\RkasRevisi;
use App\Models\TransaksiBku;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generator nomor dokumen (no bukti BKU, no nota multi-item) di sisi server,
 * dengan pola yang identik dengan alur lama agar perilaku tidak berubah.
 */
final class NomorDokumen
{
    /**
     * No bukti transaksi BKU (BBU penerimaan / BPU pengeluaran),
     * format: {PREFIX}{seq:3} / {NPSN} / {MM} / {YYYY}.
     *
     * Nomor berjalan PER BULAN: meneruskan dari nomor tertinggi yang pernah
     * dipakai di bulan tsb (aktif + soft-deleted). Nomor teratas yang dihapus
     * (soft-delete) boleh dipakai ulang; nomor yang tidak pernah ada di tengah
     * TIDAK diisi — mencegah "BPU001 muncul lagi" setelah BPU011-017.
     */
    public static function noBukti(string $jenis, string $tanggal): string
    {
        $prefix = strtolower($jenis) === 'penerimaan' ? 'BBU' : 'BPU';
        $month = Carbon::parse($tanggal)->format('m');
        $year = Carbon::parse($tanggal)->format('Y');
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';

        $seq = self::nextSeq($prefix, $npsn, $month, $year);

        do {
            $candidate = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT)
                . '/' . $npsn . '/' . $month . '/' . $year;
            $seq++;
        } while (TransaksiBku::where('no_bukti', $candidate)->exists());

        return $candidate;
    }

    /**
     * Nomor urut berikutnya utk tiap bulan 1..12 (tahun berjalan), dipakai view
     * create sbg preview field no_bukti agar konsisten dengan yang disimpan server.
     *
     * @return array<int, int> bulan => seq berikutnya (1..12)
     */
    public static function nextSeqPerBulan(string $jenis): array
    {
        $prefix = strtolower($jenis) === 'penerimaan' ? 'BBU' : 'BPU';
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';
        $year = (string) Carbon::now()->year;
        $result = [];

        for ($month = 1; $month <= 12; $month++) {
            $result[$month] = self::nextSeq(
                $prefix,
                $npsn,
                str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                $year
            );
        }

        return $result;
    }

    /**
     * Nomor urut terkecil "berikutnya" utk kombinasi (prefix, npsn, bulan, tahun).
     * Jika nomor teratas masih aktif -> max + 1; jika nomor teratas soft-deleted
     * -> reuse nomor tersebut. Gap nomor yang tidak pernah ada tidak pernah diisi.
     */
    private static function nextSeq(string $prefix, string $npsn, string $month, string $year): int
    {
        $pattern = $prefix . '%/' . $npsn . '/' . $month . '/' . $year;

        /** @var Collection<int, string> $noBuktis */
        $noBuktis = TransaksiBku::withTrashed()
            ->where('no_bukti', 'like', $pattern)
            ->pluck('no_bukti');

        $maxAll = $noBuktis
            ->map(static fn (string $b): int => (int) substr($b, 3, 3))
            ->max();

        if ($maxAll === null) {
            return 1;
        }

        $maxStr = $prefix . str_pad((string) $maxAll, 3, '0', STR_PAD_LEFT)
            . '/' . $npsn . '/' . $month . '/' . $year;

        return TransaksiBku::where('no_bukti', $maxStr)->exists() ? $maxAll + 1 : $maxAll;
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

    /**
     * No revisi pergeseran/PAK, format: {PREFIX}-{seq:4} / {NPSN} / {MM} / {YYYY}.
     * PREFIX = PGS- (pergeseran) atau PAK- (pak). Nomor berjalan PER BULAN:
     * meneruskan dari nomor tertinggi yang pernah dipakai di bulan tsb
     * (aktif + soft-deleted) — nomor dokumen formal TIDAK dipakai ulang.
     */
    public static function noRevisi(string $jenis, string $tanggal): string
    {
        $prefix = strtolower($jenis) === 'pak' ? 'PAK-' : 'PGS-';
        $month = Carbon::parse($tanggal)->format('m');
        $year = Carbon::parse($tanggal)->format('Y');
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';

        $seq = self::nextRevisiSeq($prefix, $npsn, $month, $year);

        do {
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT)
                . '/' . $npsn . '/' . $month . '/' . $year;
            $seq++;
        } while (RkasRevisi::withTrashed()->where('no_revisi', $candidate)->exists());

        return $candidate;
    }

    /**
     * Nomor urut tertinggi+1 utk kombinasi (prefix, npsn, bulan, tahun) pada
     * tabel rkas_revisi (termasuk soft-deleted, agar nomor tidak terpakai ulang).
     */
    private static function nextRevisiSeq(string $prefix, string $npsn, string $month, string $year): int
    {
        $pattern = $prefix . '%/' . $npsn . '/' . $month . '/' . $year;

        /** @var Collection<int, string> $noRevisis */
        $noRevisis = RkasRevisi::withTrashed()
            ->where('no_revisi', 'like', $pattern)
            ->pluck('no_revisi');

        $maxAll = $noRevisis
            ->map(static fn (string $r): int => (int) substr($r, strlen($prefix), 4))
            ->max();

        return $maxAll === null ? 1 : $maxAll + 1;
    }
}
