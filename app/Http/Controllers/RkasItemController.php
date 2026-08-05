<?php

namespace App\Http\Controllers;

use App\Models\RkasItem;
use Illuminate\Http\Request;

class RkasItemController extends Controller
{
    public function select2(Request $request): \Illuminate\Http\JsonResponse
    {
        $searchRaw = $request->input('q');
        $search = is_string($searchRaw) ? $searchRaw : '';
        $excludeIds = $request->input('exclude', []);
        $bulanRaw = $request->input('bulan');
        $bulan = is_numeric($bulanRaw) ? (int) $bulanRaw : null;

        $query = RkasItem::with('program', 'kodeRekening', 'sumberDana');

        if ($bulan !== null) {
            $query->with([
                'bulanRencana' => fn (\Illuminate\Database\Eloquent\Relations\Relation $q) => $q->where('bulan', '<=', $bulan),
                'transaksiBkus' => fn (\Illuminate\Database\Eloquent\Relations\Relation $q) => $q->where('jenis', 'pengeluaran')->where('bulan', '<=', $bulan),
            ]);
        } else {
            $query->withSum(['transaksiBkus as realisasi_sum' => fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('jenis', 'pengeluaran')], 'jumlah');
        }

        $query->orderBy('no_urut');

        if ($search !== '') {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($search): void {
                $q->where('no_urut', 'LIKE', "%{$search}%")
                  ->orWhere('uraian', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', (array) $excludeIds);
        }

        $items = $query->paginate(20);

        $results = $items->map(function (RkasItem $item) use ($bulan): array {
            if ($bulan !== null) {
                $rencana = (float) $item->bulanRencana->sum('rencana');
                $realisasi = (float) $item->transaksiBkus->sum('jumlah');
                $sisa = $rencana - $realisasi;
                $labelSisa = 'Sisa s.d. bulan ' . $bulan . ': Rp ' . number_format($sisa, 0, ',', '.');
            } else {
                $sisa = (float) $item->jumlah - (float) $item->realisasi_sum;
                $labelSisa = 'Sisa: Rp ' . number_format($sisa, 0, ',', '.');
            }

            return [
                'id' => $item->id,
                'text' => $item->no_urut . '. ' . $item->uraian . ' — ' . (optional($item->sumberDana)->kode ?? '-') . ' (' . $labelSisa . ')',
                'tarif' => $item->tarif,
                'program' => optional($item->program)->nama ?? '-',
                'kode' => optional($item->kodeRekening)->kode ?? '-',
                'satuan' => $item->satuan ?? '',
                'sisa' => $sisa,
            ];
        });

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $items->hasMorePages(),
            ],
        ]);
    }
}
