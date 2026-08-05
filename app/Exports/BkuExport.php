<?php

namespace App\Exports;

use App\Models\TransaksiBku;
use App\Models\TahunAnggaran;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** @implements WithMapping<TransaksiBku> */
class BkuExport implements FromCollection, WithHeadings, WithTitle, WithMapping
{
    protected int $bulan;
    protected string $profil;
    protected ?string $tahunAnggaranId;
    protected ?string $sumberDanaId;

    public function __construct(int $bulan, string $profil, ?string $tahunAnggaranId = null, ?string $sumberDanaId = null)
    {
        $this->bulan = $bulan;
        $this->profil = $profil;
        $this->tahunAnggaranId = $tahunAnggaranId ?? (function (): ?string {
            $ta = TahunAnggaran::where('status', true)->first(['id']);

            return $ta ? $ta->id : null;
        })();
        $this->sumberDanaId = $sumberDanaId;
    }

    /** @return Collection<int, TransaksiBku> */
    public function collection()
    {
        $query = TransaksiBku::with('rkasItem.program', 'rkasItem.kodeRekening.jenisBelanja')
            ->where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('bulan', $this->bulan)
            ->when($this->sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $this->sumberDanaId))
            ->orderBy('tanggal')
            ->orderBy('id');

        $transaksis = $query->get();

        $saldoRecord = TransaksiBku::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('bulan', '<', $this->bulan)
            ->when($this->sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $this->sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' THEN jumlah ELSE -jumlah END), 0) as saldo")
            ->first();
        $saldoAwal = $saldoRecord ? (float) $saldoRecord->getAttribute('saldo') : 0;

        $saldo = $saldoAwal;
        foreach ($transaksis as $t) {
            $saldo += strtolower($t->jenis) == 'penerimaan' ? $t->jumlah : -$t->jumlah;
            $t->saldo_berjalan = $saldo;
        }

        $t = new TransaksiBku();
        $t->setAttribute('kode', '');
        $t->setAttribute('uraian', 'Saldo Awal');
        $t->setAttribute('penerimaan', '');
        $t->setAttribute('pengeluaran', '');
        $t->setAttribute('saldo', $saldoAwal);
        $t->setAttribute('is_header', true);
        $transaksis->prepend($t);

        $t2 = new TransaksiBku();
        $t2->setAttribute('kode', '');
        $t2->setAttribute('uraian', 'Saldo Akhir');
        $t2->setAttribute('penerimaan', (float) TransaksiBku::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('bulan', $this->bulan)
            ->when($this->sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $this->sumberDanaId))
            ->where('jenis', 'penerimaan')->sum('jumlah'));
        $t2->setAttribute('pengeluaran', (float) TransaksiBku::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('bulan', $this->bulan)
            ->when($this->sumberDanaId, fn ($q) => $q->where('sumber_dana_id', $this->sumberDanaId))
            ->where('jenis', 'pengeluaran')->sum('jumlah'));
        $t2->setAttribute('saldo', $saldo);
        $t2->setAttribute('is_header', true);
        $transaksis->push($t2);

        return $transaksis;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Tanggal',
            'No Bukti',
            'Kode Kegiatan',
            'Kode Rekening',
            'Uraian',
            'Penerimaan',
            'Pengeluaran',
            'Saldo',
        ];
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        if (isset($row->is_header)) {
            $label = $row->uraian;
            $saldo = number_format((float) $row->getAttribute('saldo'), 0, ',', '.');

            return ['', '', '', '', $label, '', '', $saldo];
        }

        return [
            $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') : '',
            $row->no_bukti ?? '',
            $row->rkasItem && $row->rkasItem->program ? $row->rkasItem->program->kode : '',
            $row->rkasItem && $row->rkasItem->kodeRekening ? $row->rkasItem->kodeRekening->kode : '',
            $row->uraian ?? '',
            strtolower($row->jenis) == 'penerimaan' ? number_format($row->jumlah, 0, ',', '.') : '',
            strtolower($row->jenis) == 'pengeluaran' ? number_format($row->jumlah, 0, ',', '.') : '',
            isset($row->saldo_berjalan) ? number_format($row->saldo_berjalan, 0, ',', '.') : '',
        ];
    }

    public function title(): string
    {
        return 'BKU Bulan ' . \Carbon\Carbon::createFromDate(null, $this->bulan, 1)->translatedFormat('F');
    }
}
