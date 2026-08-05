<?php

namespace App\Exports;

use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** @implements WithMapping<RkasItem> */
class RekapRekeningExport implements FromCollection, WithHeadings, WithTitle, WithMapping
{
    protected int $bulan;
    protected ?string $tahunAnggaranId;
    protected ?string $sumberDanaId;

    public function __construct(int $bulan, ?string $tahunAnggaranId = null, ?string $sumberDanaId = null)
    {
        $this->bulan = $bulan;
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->sumberDanaId = $sumberDanaId;
    }

    /** @return Collection<int, RkasItem> */
    public function collection()
    {
        $tahunAnggaranAktif = $this->tahunAnggaranId
            ? TahunAnggaran::find($this->tahunAnggaranId)
            : TahunAnggaran::getActive();
        if (!$tahunAnggaranAktif) {
            return collect();
        }

        $rencanaSub = RkasItemBulan::selectRaw('rkas_item_bulan.rkas_item_id, SUM(rkas_item_bulan.rencana) as total')
            ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rkas_item_bulan.rkas_item_id')
            ->where('rkas_item_bulan.bulan', $this->bulan)
            ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
            ->when($this->sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $this->sumberDanaId))
            ->groupBy('rkas_item_bulan.rkas_item_id');

        $realisasiSub = TransaksiBku::selectRaw('transaksi_bku.rkas_item_id, SUM(transaksi_bku.jumlah) as total')
            ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
            ->where('transaksi_bku.jenis', 'pengeluaran')
            ->where('transaksi_bku.bulan', $this->bulan)
            ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
            ->when($this->sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $this->sumberDanaId))
            ->groupBy('transaksi_bku.rkas_item_id');

        $query = RkasItem::with(['kodeRekening.jenisBelanja', 'program'])
            ->select('rkas_item.*')
            ->selectRaw('COALESCE(rib.total, 0) as rencana_bulan')
            ->selectRaw('COALESCE(tb.total, 0) as realisasi_bulan');
        $query = $query->leftJoinSub($rencanaSub, 'rib', fn(\Illuminate\Database\Query\JoinClause $j) => $j->on('rkas_item.id', '=', 'rib.rkas_item_id'));
        $query = $query->leftJoinSub($realisasiSub, 'tb', fn(\Illuminate\Database\Query\JoinClause $j) => $j->on('rkas_item.id', '=', 'tb.rkas_item_id'));
        $query->where('rkas_item.tahun_anggaran_id', $tahunAnggaranAktif->id);

        if ($this->sumberDanaId) {
            $query->where('rkas_item.sumber_dana_id', $this->sumberDanaId);
        }

        return $query->get()
            ->map(function (RkasItem $item) {
                $rencana = (float) ($item->rencana_bulan ?? 0);
                $realisasi = (float) ($item->realisasi_bulan ?? 0);
                $item->sisa_bulan = $rencana - $realisasi;
                $item->persen = $rencana > 0 ? round(($realisasi / $rencana) * 100, 1) : 0;
                return $item;
            });
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Jenis Belanja',
            'Kode Kegiatan',
            'Program',
            'Kode Rekening',
            'Nama Rekening',
            'Rencana',
            'Realisasi',
            'Sisa',
            '%'
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            $row->kodeRekening->jenisBelanja->nama ?? '-',
            $row->program->kode ?? '-',
            $row->program->nama ?? '-',
            $row->kodeRekening->kode ?? '-',
            $row->kodeRekening->nama ?? '-',
            number_format($row->rencana_bulan ?? 0, 0, ',', '.'),
            number_format($row->realisasi_bulan ?? 0, 0, ',', '.'),
            number_format($row->sisa_bulan ?? 0, 0, ',', '.'),
            ($row->persen ?? 0) . '%',
        ];
    }

    public function title(): string
    {
        return 'Rekap Rekening Bulan ' . \Carbon\Carbon::createFromDate(null, $this->bulan, 1)->translatedFormat('F');
    }
}
