<?php

namespace App\Http\Controllers;

use App\Models\KategoriJuknis;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Support\RealisasiQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring Kepatuhan Juknis BOSP (Tahap 3).
 *
 * Menghitung proporsi (rencana ATAU realisasi, sesuai toggle "basis") dari setiap
 * Kategori Juknis yang sudah dipetakan ke kode rekening terhadap Total Pagu
 * tahun anggaran terpilih, lalu membandingkannya dengan batas persen kategori
 * (arah maksimal = batas atas, arah minimal = batas bawah).
 */
class MonitoringJuknisController extends Controller
{
    /**
     * Toleransi perbandingan persentase (poin persen) agar boundary tepat di
     * batas tidak keliru dinilai melebihi/mencapai karena noise float.
     */
    private const EPSILON = 0.000001;

    public function index(Request $request): \Illuminate\View\View
    {
        $basis = $request->input('basis') === 'realisasi' ? 'realisasi' : 'rencana';

        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $tahunInput = $request->get('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', (int) $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();

        $totalPagu = 0.0;
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $kategoriCards */
        $kategoriCards = collect();
        /** @var \Illuminate\Support\Collection<int, array{label: string, total: float, persen: float}> $jenisBelanjaBreakdown */
        $jenisBelanjaBreakdown = collect();
        $belumDikategorikanCount = 0;

        if ($tahunAnggaranAktif) {
            $totalPagu = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaranAktif->id)->sum('jumlah');

            // Hanya kategori yang SUDAH dipetakan ke minimal satu kode rekening.
            $kategoriJuknis = KategoriJuknis::query()
                ->whereHas('kodeRekenings')
                ->with('kodeRekenings:id')
                ->orderBy('nama')
                ->get();

            foreach ($kategoriJuknis as $kategori) {
                $rekeningIds = $kategori->kodeRekenings->modelKeys();

                $nominal = match ($basis) {
                    'realisasi' => $this->realisasiNominal($tahunAnggaranAktif->id, $rekeningIds),
                    default => (float) RkasItem::query()
                        ->where('tahun_anggaran_id', $tahunAnggaranAktif->id)
                        ->whereIn('kode_rekening_id', $rekeningIds)
                        ->sum('jumlah'),
                };

                $persen = $totalPagu > 0 ? ($nominal / $totalPagu) * 100 : 0.0;

                $kategoriCards->push([
                    'id' => $kategori->id,
                    'nama' => $kategori->nama,
                    'arah' => $kategori->arah,
                    'batas' => (float) $kategori->batas_persen,
                    'nominal' => $nominal,
                    'persen' => round($persen, 2),
                    'status' => $this->statusFor($kategori, $persen),
                    'jumlah_rekening' => count($rekeningIds),
                ]);
            }

            $jenisBelanjaBreakdown = $this->jenisBelanjaBreakdown($tahunAnggaranAktif->id, $basis, $totalPagu);

            $belumDikategorikanCount = $this->belumDikategorikanCount($tahunAnggaranAktif->id, $basis);
        }

        return view('laporan.monitoring-juknis', compact(
            'basis',
            'tahunAnggaranAktif',
            'tahunList',
            'totalPagu',
            'kategoriCards',
            'jenisBelanjaBreakdown',
            'belumDikategorikanCount',
        ));
    }

    /**
     * @param  list<string> $rekeningIds
     */
    private function realisasiNominal(string $tahunAnggaranId, array $rekeningIds): float
    {
        if ($rekeningIds === []) {
            return 0.0;
        }

        return (float) RealisasiQuery::base()
            ->join('rkas_item', 'rkas_item.id', '=', 'rb.rkas_item_id')
            ->where('rkas_item.tahun_anggaran_id', $tahunAnggaranId)
            ->whereIn('rkas_item.kode_rekening_id', $rekeningIds)
            ->sum('rb.jumlah');
    }

    /**
     * Status kepatuhan satu kategori terhadap batasnya.
     *
     * Arah maksimal: sesuai bila persen <= batas (tepat di batas TETAP sesuai).
     * Arah minimal: sesuai bila persen >= batas.
     *
     * @return string sesuai|melebihi|kurang
     */
    private function statusFor(KategoriJuknis $kategori, float $persen): string
    {
        $batas = (float) $kategori->batas_persen;

        if ($kategori->arah === 'maksimal') {
            return $persen <= $batas + self::EPSILON ? 'sesuai' : 'melebihi';
        }

        return $persen >= $batas - self::EPSILON ? 'sesuai' : 'kurang';
    }

    /**
     * Breakdown informatif nominal per jenis belanja (dari kode rekening)
     * terhadap Total Pagu — mengikuti basis toggle.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, total: float, persen: float}>
     */
    private function jenisBelanjaBreakdown(string $tahunAnggaranId, string $basis, float $totalPagu): \Illuminate\Support\Collection
    {
        $labelExpr = "COALESCE(jb.nama, 'Tidak Terkategori')";

        if ($basis === 'realisasi') {
            $base = RealisasiQuery::base()
                ->join('rkas_item', 'rkas_item.id', '=', 'rb.rkas_item_id')
                ->selectRaw("{$labelExpr} as label, SUM(rb.jumlah) as total");
        } else {
            $base = RkasItem::query()
                ->selectRaw("{$labelExpr} as label, SUM(rkas_item.jumlah) as total")
                ->toBase();
        }

        $rows = $base
            ->leftJoin('master_kode_rekening as mkr', 'rkas_item.kode_rekening_id', '=', 'mkr.id')
            ->leftJoin('jenis_belanja as jb', 'mkr.jenis_belanja_id', '=', 'jb.id')
            ->where('rkas_item.tahun_anggaran_id', $tahunAnggaranId)
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        return collect($rows)
            ->filter(fn ($row): bool => (float) ($row->total ?? 0) > 0)
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'total' => (float) $row->total,
                'persen' => $totalPagu > 0 ? round(((float) $row->total / $totalPagu) * 100, 1) : 0.0,
            ])
            ->values();
    }

    /**
     * Jumlah kode rekening yang punya nominal (sesuai basis) TAPI belum
     * dipetakan ke kategori juknis mana pun.
     */
    private function belumDikategorikanCount(string $tahunAnggaranId, string $basis): int
    {
        $mappedIds = DB::table('kode_rekening_kategori_juknis')->pluck('kode_rekening_id');

        if ($basis === 'realisasi') {
            $query = RealisasiQuery::base()
                ->join('rkas_item', 'rkas_item.id', '=', 'rb.rkas_item_id')
                ->where('rkas_item.tahun_anggaran_id', $tahunAnggaranId)
                ->whereNotNull('rkas_item.kode_rekening_id');

            if ($mappedIds->isNotEmpty()) {
                $query->whereNotIn('rkas_item.kode_rekening_id', $mappedIds->all());
            }

            /** @var int $count */
            $count = $query->distinct()->count('rkas_item.kode_rekening_id');

            return $count;
        }

        $query = RkasItem::query()
            ->where('tahun_anggaran_id', $tahunAnggaranId)
            ->where('jumlah', '>', 0)
            ->whereNotNull('kode_rekening_id');

        if ($mappedIds->isNotEmpty()) {
            $query->whereNotIn('kode_rekening_id', $mappedIds->all());
        }

        return (int) $query->distinct()->count('kode_rekening_id');
    }
}
