<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImportLog;
use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Support\RealisasiQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $rkasItems = collect();

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
                    $totalRealisasi = (float) RealisasiQuery::base()
                        ->whereIn('rb.rkas_item_id', $filteredIds)
                        ->where('rb.bulan', $bulan)
                        ->sum('rb.jumlah');
                } else {
                    $totalRencana = (float) RkasItem::whereIn('id', $filteredIds)->sum('jumlah');
                    $totalRealisasi = (float) RealisasiQuery::base()
                        ->whereIn('rb.rkas_item_id', $filteredIds)
                        ->sum('rb.jumlah');
                }

                $chartData = RealisasiQuery::base()
                    ->whereIn('rb.rkas_item_id', $filteredIds)
                    ->join('rkas_item', 'rkas_item.id', '=', 'rb.rkas_item_id')
                    ->join('master_kode_rekening', 'rkas_item.kode_rekening_id', '=', 'master_kode_rekening.id')
                    ->join('jenis_belanja', 'master_kode_rekening.jenis_belanja_id', '=', 'jenis_belanja.id')
                    ->selectRaw('jenis_belanja.nama as label, sum(rb.jumlah) as total')
                    ->groupBy('jenis_belanja.nama')
                    ->orderByDesc('total')
                    ->get();

                foreach ($chartData as $d) {
                    $chartLabels[] = (string) ($d->label ?? '');
                    $chartValues[] = (float) ($d->total ?? 0);
                }

                $realisasiPerBulan = array_fill(1, 12, 0);
                $byBulan = RealisasiQuery::base()
                    ->whereIn('rb.rkas_item_id', $filteredIds)
                    ->selectRaw('rb.bulan, sum(rb.jumlah) as total')
                    ->whereNotNull('rb.bulan')
                    ->groupBy('rb.bulan')
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

                    $dataBerubah = false;
                    $diubahTerakhir = null;

                    if ($latest) {
                        $importTime = $latest->finished_at ?? $latest->created_at;
                        if ($importTime) {
                            $change = AuditLog::query()
                                ->where('created_at', '>', $importTime)
                                ->where('tabel', 'rkas_item')
                                ->orderByDesc('created_at')
                                ->first();
                            if ($change) {
                                $dataBerubah = true;
                                $diubahTerakhir = $change->created_at;
                            }
                        }
                    }

                    return (object) [
                        'bulan' => $m,
                        'nama' => Carbon::createFromDate(null, $m, 1)->translatedFormat('F'),
                        'status' => $latest ? $latest->status : null,
                        'baris_berhasil' => $latest ? $latest->baris_berhasil : null,
                        'diimport_pada' => $latest ? ($latest->finished_at ?? $latest->created_at) : null,
                        'data_berubah' => $dataBerubah,
                        'diubah_terakhir' => $diubahTerakhir,
                    ];
                });
            }

            $rkasItems = (clone $baseQuery)
                ->when($bulan, fn ($q) => $q->whereHas('bulanRencana', function ($q2) use ($bulan): void {
                    $q2->where('bulan', $bulan);
                }))
                ->with([
                    'program',
                    'kodeRekening.jenisBelanja',
                    'transaksiBkus' => function (Relation $q) use ($bulan): void {
                        $q->where('jenis', 'pengeluaran');
                        if ($bulan) {
                            $q->where('bulan', $bulan);
                        }
                    },
                    'notaBkuItems' => function (Relation $q) use ($bulan): void {
                        $q->whereHas('notaBku', function ($q2) use ($bulan): void {
                            $q2->whereNull('deleted_at');
                            if ($bulan) {
                                $q2->where('bulan', $bulan);
                            }
                        });
                    },
                    'bulanRencana' => function (Relation $q) use ($bulan): void {
                        if ($bulan) {
                            $q->where('bulan', $bulan);
                        }
                    },
                ])
                ->orderBy('no_urut')
                ->paginate(50)
                ->withQueryString();

            foreach ($rkasItems as $item) {
                $_sum = $item->transaksiBkus->sum('jumlah') + $item->notaBkuItems->sum('subtotal');
                $item->dynamic_realisasi = is_numeric($_sum) ? (float) $_sum : 0.0;

                if ($bulan) {
                    $rencanaItem = $item->bulanRencana->first();
                    $item->dynamic_rencana = $rencanaItem ? (float) $rencanaItem->rencana : 0.0;
                } else {
                    $item->dynamic_rencana = (float) $item->jumlah;
                }

                $item->dynamic_sisa = $item->dynamic_rencana - $item->dynamic_realisasi;
                $item->persentase = $item->dynamic_rencana > 0 ? ($item->dynamic_realisasi / $item->dynamic_rencana) * 100 : 0;

                $item->dynamic_rencana_volume = $item->tarif > 0 ? round($item->dynamic_rencana / $item->tarif, 2) : 0;
                $item->dynamic_realisasi_volume = $item->tarif > 0 ? round($item->dynamic_realisasi / $item->tarif, 2) : 0;
                $item->dynamic_sisa_volume = $item->dynamic_rencana_volume - $item->dynamic_realisasi_volume;
            }
        }

        $totalSisa = $totalRencana - $totalRealisasi;
        $persentaseCapaian = $totalRencana > 0 ? round(($totalRealisasi / $totalRencana) * 100, 1) : 0;

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
