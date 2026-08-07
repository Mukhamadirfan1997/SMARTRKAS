<?php

namespace App\Exports;

use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapKuartalExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnWidths
{
    protected int $kuartal;
    protected string $namaSekolah;
    protected ?string $tahunAnggaranId;
    protected ?string $sumberDanaId;
    protected ?string $programId;
    protected ?string $search;
    /** @var array<int, string> */
    protected array $bulanNames;
    /** @var array<int, int> */
    protected array $bulanMonths;

    public function __construct(int $kuartal, string $namaSekolah, ?string $tahunAnggaranId = null, ?string $sumberDanaId = null, ?string $programId = null, ?string $search = null)
    {
        $this->kuartal = $kuartal;
        $this->namaSekolah = $namaSekolah;
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->sumberDanaId = $sumberDanaId;
        $this->programId = $programId;
        $this->search = $search;

        $startMonth = ($kuartal - 1) * 3 + 1;
        $this->bulanMonths = [$startMonth, $startMonth + 1, $startMonth + 2];
        $this->bulanNames = array_map(
            fn($m) => \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F'),
            $this->bulanMonths
        );
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $tahunAnggaran = $this->tahunAnggaranId
            ? TahunAnggaran::find($this->tahunAnggaranId)
            : TahunAnggaran::getActive();
        if (!$tahunAnggaran) {
            return [[]];
        }

        $months = $this->bulanMonths;

        $cases = [];
        foreach ($months as $i => $b) {
            $cases[] = "SUM(CASE WHEN transaksi_bku.bulan = {$b} THEN transaksi_bku.jumlah ELSE 0 END) as m{$i}";
        }
        $casesSql = implode(', ', $cases);

        $realisasiSub = TransaksiBku::selectRaw("transaksi_bku.rkas_item_id, {$casesSql}, SUM(transaksi_bku.jumlah) as total_all")
            ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
            ->where('transaksi_bku.jenis', 'pengeluaran')
            ->whereIn('transaksi_bku.bulan', $months)
            ->where('ri_sub.tahun_anggaran_id', $tahunAnggaran->id)
            ->when($this->sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $this->sumberDanaId))
            ->when($this->programId, fn($q) => $q->where('ri_sub.program_id', $this->programId))
            ->groupBy('transaksi_bku.rkas_item_id');

        $query = RkasItem::with(['kodeRekening.jenisBelanja', 'program'])
            ->select('rkas_item.*')
            ->selectRaw('COALESCE(tb.m0, 0) as m0, COALESCE(tb.m1, 0) as m1, COALESCE(tb.m2, 0) as m2, COALESCE(tb.total_all, 0) as total_all');
        $query->leftJoinSub($realisasiSub, 'tb', fn(\Illuminate\Database\Query\JoinClause $j) => $j->on('rkas_item.id', '=', 'tb.rkas_item_id'));
        $query->where('rkas_item.tahun_anggaran_id', $tahunAnggaran->id);

        if ($this->sumberDanaId) {
            $query->where('rkas_item.sumber_dana_id', $this->sumberDanaId);
        }

        if ($this->search) {
            $query->where('rkas_item.uraian', 'like', "%{$this->search}%");
        }

        $rkasItems = $query->get()
            ->map(function (RkasItem $item) use ($months) {
                $realisasiPerBulan = [];
                $totalRealisasi = 0;
                $fields = [$item->getAttribute('m0'), $item->getAttribute('m1'), $item->getAttribute('m2')];
                foreach ($months as $i => $bulan) {
                    $r = isset($fields[$i]) ? (float) $fields[$i] : 0.0;
                    $realisasiPerBulan[$bulan] = $r;
                    $totalRealisasi += $r;
                }
                $item->realisasi_per_bulan = $realisasiPerBulan;
                $item->total_realisasi = $totalRealisasi;
                return $item;
            });

        $grouped = $rkasItems->groupBy(
            fn(RkasItem $item): string => $item->kodeRekening->jenisBelanja->nama ?? 'Tidak Terkategori'
        );

        $rows = [];

        $periodeLabel = implode(' s.d. ', $this->bulanNames);
        $tahun = $tahunAnggaran->tahun ?? '-';

        $rows[] = [$this->namaSekolah, '', '', '', '', '', ''];
        $rows[] = ['Rekap Realisasi Anggaran Per Kode Rekening', '', '', '', '', '', ''];
        $rows[] = ['Tribulan ' . $this->kuartal . ' (' . $periodeLabel . ') ' . $tahun, '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', ''];

        $no = 1;
        $grandTotalPerBulan = array_fill_keys($months, 0);
        $grandTotalAll = 0;

        $groupLabels = range('A', 'Z');

        foreach ($grouped as $jenisBelanja => $items) {
            $groupIdx = array_search($jenisBelanja, array_keys($grouped->toArray()));
            $groupPrefix = ($groupIdx !== false && $groupIdx < 26) ? $groupLabels[$groupIdx] . '. ' : '';

            $rows[] = [$groupPrefix . strtoupper($jenisBelanja), '', '', '', '', '', ''];

            $subTotalPerBulan = array_fill_keys($months, 0);
            $subTotalAll = 0;

            foreach ($items->sortBy('kodeRekening.kode') as $item) {
                $row = [
                    $no,
                    $item->kodeRekening->kode ?? '-',
                    $item->uraian,
                ];
                foreach ($months as $bulan) {
                    $row[] = $item->realisasi_per_bulan[$bulan] ?? 0;
                }
                $row[] = $item->total_realisasi;
                $rows[] = $row;

                foreach ($months as $bulan) {
                    $subTotalPerBulan[$bulan] += isset($item->realisasi_per_bulan[$bulan]) ? (float) $item->realisasi_per_bulan[$bulan] : 0.0;
                }
                $subTotalAll += $item->total_realisasi;
                $no++;
            }

            $subRow = ['', '', 'SUBTOTAL ' . strtoupper($jenisBelanja)];
            foreach ($months as $bulan) {
                $subRow[] = $subTotalPerBulan[$bulan];
            }
            $subRow[] = $subTotalAll;
            $rows[] = $subRow;

            foreach ($months as $bulan) {
                $grandTotalPerBulan[$bulan] += $subTotalPerBulan[$bulan];
            }
            $grandTotalAll += $subTotalAll;

            $rows[] = ['', '', '', '', '', '', ''];
        }

        $gtRow = ['', '', 'TOTAL KESELURUHAN'];
        foreach ($months as $bulan) {
            $gtRow[] = $grandTotalPerBulan[$bulan];
        }
        $gtRow[] = $grandTotalAll;
        $rows[] = $gtRow;

        return $rows;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $cols = ['No', 'Kode Rekening', 'Uraian Anggaran'];
        foreach ($this->bulanNames as $name) {
            $cols[] = 'Realisasi ' . $name;
        }
        $cols[] = 'Total Tribulan ' . $this->kuartal;
        return $cols;
    }

    public function title(): string
    {
        return 'Rekap Tribulan ' . $this->kuartal;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 18,
            'C' => 35,
            'D' => 18,
            'E' => 18,
            'F' => 18,
            'G' => 18,
        ];
    }
}
