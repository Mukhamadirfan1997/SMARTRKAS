<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $tahunInput = $request->get('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', (int) $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }

        $tahunList = TahunAnggaran::orderByDesc('tahun')->get();
        $sumberDanas = SumberDana::orderBy('kode')->get();
        $programs = Cache::remember('master_programs', 86400, fn (): \Illuminate\Database\Eloquent\Collection => MasterProgram::all());
        $kodeRekenings = Cache::remember('master_kode_rekenings', 86400, fn (): \Illuminate\Database\Eloquent\Collection => MasterKodeRekening::all());
        $jenisBelanjas = Cache::remember('jenis_belanjas', 86400, fn (): \Illuminate\Database\Eloquent\Collection => JenisBelanja::all());

        $bulanInput = $request->input('bulan');
        $bulan = $request->filled('bulan') && is_numeric($bulanInput) ? (int) $bulanInput : null;
        $programId = $request->input('program_id');
        $kodeRekeningId = $request->input('kode_rekening_id');
        $sumberDanaId = $request->input('sumber_dana_id');
        $jenisBelanjaId = $request->input('jenis_belanja_id');

        $totalRencana = 0;
        $totalRealisasi = 0;
        $chartLabels = [];
        $chartValues = [];
        $trenBulanValues = array_fill(0, 12, 0);
        $transaksiBulanIni = 0;
        $importStatus = collect();
        $filteredIds = collect();
        $recentTransaksi = collect();

        if ($tahunAnggaranAktif) {
            $baseQuery = RkasItem::query()->where('tahun_anggaran_id', $tahunAnggaranAktif->id);

            if ($programId) {
                $baseQuery->where('program_id', $programId);
            }
            if ($kodeRekeningId) {
                $baseQuery->where('kode_rekening_id', $kodeRekeningId);
            }
            if ($sumberDanaId) {
                $baseQuery->where('sumber_dana_id', $sumberDanaId);
            }
            if ($jenisBelanjaId) {
                $baseQuery->whereHas('kodeRekening', function ($q) use ($jenisBelanjaId): void {
                    $q->where('jenis_belanja_id', $jenisBelanjaId);
                });
            }

            $filteredIds = $baseQuery->pluck('id');

            if ($filteredIds->isNotEmpty()) {
                if ($bulan) {
                    $totalRencana = (float) RkasItemBulan::whereIn('rkas_item_id', $filteredIds)
                        ->where('bulan', $bulan)
                        ->sum('rencana');
                    $totalRealisasi = (float) TransaksiBku::whereIn('rkas_item_id', $filteredIds)
                        ->where('jenis', 'pengeluaran')
                        ->where('bulan', $bulan)
                        ->sum('jumlah');
                } else {
                    $totalRencana = (float) RkasItem::whereIn('id', $filteredIds)->sum('jumlah');
                    $totalRealisasi = (float) TransaksiBku::whereIn('rkas_item_id', $filteredIds)
                        ->where('jenis', 'pengeluaran')
                        ->sum('jumlah');
                }

                $chartData = TransaksiBku::query()
                    ->whereIn('rkas_item_id', $filteredIds)
                    ->where('transaksi_bku.jenis', 'pengeluaran')
                    ->join('rkas_item', 'transaksi_bku.rkas_item_id', '=', 'rkas_item.id')
                    ->join('master_kode_rekening', 'rkas_item.kode_rekening_id', '=', 'master_kode_rekening.id')
                    ->join('jenis_belanja', 'master_kode_rekening.jenis_belanja_id', '=', 'jenis_belanja.id')
                    ->selectRaw('jenis_belanja.nama as label, sum(transaksi_bku.jumlah) as total')
                    ->groupBy('jenis_belanja.nama')
                    ->orderByDesc('total')
                    ->get()
                    ->toArray();

                foreach ($chartData as $d) {
                    $chartLabels[] = (string) ($d['label'] ?? '');
                    $chartValues[] = (float) ($d['total'] ?? 0);
                }

                $realisasiPerBulan = array_fill(1, 12, 0);
                $byBulan = TransaksiBku::query()
                    ->whereIn('rkas_item_id', $filteredIds)
                    ->where('jenis', 'pengeluaran')
                    ->selectRaw('bulan, sum(jumlah) as total')
                    ->whereNotNull('bulan')
                    ->groupBy('bulan')
                    ->pluck('total', 'bulan');

                foreach ($byBulan as $b => $t) {
                    if (isset($realisasiPerBulan[$b]) && is_numeric($t)) {
                        $realisasiPerBulan[$b] = (float) $t;
                    }
                }
                $trenBulanValues = array_values($realisasiPerBulan);

                $transaksiBulanIni = TransaksiBku::whereIn('rkas_item_id', $filteredIds)
                    ->where('bulan', (int) Carbon::now()->month)
                    ->count();

                $recentTransaksi = TransaksiBku::with(['rkasItem.program', 'rkasItem.kodeRekening.jenisBelanja'])
                    ->whereIn('rkas_item_id', $filteredIds)
                    ->where('jenis', 'pengeluaran')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get();

                $importLogs = ImportLog::where('tahun_anggaran_id', $tahunAnggaranAktif->id)->get();

                $importStatus = collect(range(1, 12))->map(function (int $m) use ($importLogs): object {
                    $latest = $importLogs->where('bulan', $m)->sortByDesc('created_at')->first();

                    return (object) [
                        'bulan' => $m,
                        'nama' => Carbon::createFromDate(null, $m, 1)->translatedFormat('F'),
                        'status' => $latest ? $latest->status : null,
                        'baris_berhasil' => $latest ? $latest->baris_berhasil : null,
                    ];
                });
            }
        }

        $totalSisa = $totalRencana - $totalRealisasi;
        $persentaseCapaian = $totalRencana > 0 ? round(($totalRealisasi / $totalRencana) * 100, 1) : 0;

        $rkasItems = collect();

        return view('dashboard', compact(
            'totalRencana',
            'totalRealisasi',
            'totalSisa',
            'persentaseCapaian',
            'chartLabels',
            'chartValues',
            'trenBulanValues',
            'transaksiBulanIni',
            'importStatus',
            'recentTransaksi',
            'rkasItems',
            'programs',
            'kodeRekenings',
            'jenisBelanjas',
            'sumberDanas',
            'sumberDanaId',
            'tahunAnggaranAktif',
            'tahunList',
            'bulan',
        ));
    }
}
