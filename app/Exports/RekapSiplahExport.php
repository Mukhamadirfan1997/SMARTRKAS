<?php

namespace App\Exports;

use App\Models\TransaksiBku;
use App\Support\RealisasiQuery;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/** @implements WithMapping<TransaksiBku> */
class RekapSiplahExport implements FromCollection, WithHeadings, WithTitle, WithMapping, WithColumnFormatting, WithStrictNullComparison
{
    /** @var array<int, int> */
    protected array $months;
    protected string $periodeLabel;
    protected ?string $tahunAnggaranId;
    protected ?string $sumberDanaId;

    /** @param array<int, int> $months */
    public function __construct(array $months, string $periodeLabel = '', ?string $tahunAnggaranId = null, ?string $sumberDanaId = null)
    {
        $this->months = $months;
        $this->periodeLabel = $periodeLabel;
        $this->tahunAnggaranId = $tahunAnggaranId ?? (function () {
            $ta = \App\Models\TahunAnggaran::where('status', true)->first(['id']);

            return $ta ? (string) $ta->id : '';
        })();
        $this->sumberDanaId = $sumberDanaId;
    }

    /** @return Collection<int, TransaksiBku> */
    public function collection()
    {
        $rows = RealisasiQuery::base()
            ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
            ->join('master_kode_rekening as mkr_sub', 'mkr_sub.id', '=', 'ri_sub.kode_rekening_id')
            ->join('jenis_belanja as jb_sub', 'jb_sub.id', '=', 'mkr_sub.jenis_belanja_id')
            ->where('ri_sub.tahun_anggaran_id', $this->tahunAnggaranId)
            ->whereIn('rb.bulan', $this->months)
            ->when($this->sumberDanaId, fn ($q) => $q->where('ri_sub.sumber_dana_id', $this->sumberDanaId));

        $rows = $rows
            ->selectRaw("
                COALESCE(jb_sub.nama, 'Tidak Terkategori') as jenis_belanja,
                COALESCE(SUM(rb.jumlah), 0) as total,
                COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'siplah' THEN rb.jumlah ELSE 0 END), 0) as siplah,
                COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'non_siplah' THEN rb.jumlah ELSE 0 END), 0) as non_siplah
            ")
            ->groupBy('jb_sub.nama')
            ->orderBy('jb_sub.nama')
            ->get()
            ->map(function ($row) {
                $total = (float) $row->total;
                $siplah = (float) $row->siplah;
                $nonSiplah = (float) $row->non_siplah;
                $belumDiisi = $total - $siplah - $nonSiplah;
                $result = new TransaksiBku();
                $result->setAttribute('jenis_belanja', $row->jenis_belanja);
                $result->setAttribute('total', $total);
                $result->setAttribute('siplah', $siplah);
                $result->setAttribute('non_siplah', $nonSiplah);
                $result->setAttribute('belum_diisi', max(0, $belumDiisi));
                $result->setAttribute('persen_siplah', $total > 0 ? round(($siplah / $total) * 100, 1) : 0);
                $result->setAttribute('persen_non_siplah', $total > 0 ? round(($nonSiplah / $total) * 100, 1) : 0);

                return $result;
            });

        return $rows;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Jenis Belanja',
            'Total Pengeluaran',
            'SIPLAH',
            'Non-SIPLAH',
            'Belum Diisi',
            '% SIPLAH',
            '% Non-SIPLAH',
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return [
            $row->jenis_belanja,
            (int) round((float) $row->total),
            (int) round((float) $row->siplah),
            (int) round((float) $row->non_siplah),
            (int) round((float) $row->belum_diisi),
            $row->persen_siplah . '%',
            $row->persen_non_siplah . '%',
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'B' => '#,##0',
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
        ];
    }

    public function title(): string
    {
        return 'Rekap SIPLAH ' . ($this->periodeLabel ?: 'Periode');
    }
}
