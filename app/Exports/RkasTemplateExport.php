<?php

namespace App\Exports;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasTemplateExport implements FromArray, WithHeadings, WithTitle
{
    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $programs = MasterProgram::orderBy('kode')->take(2)->get(['kode', 'nama']);
        $rekenings = MasterKodeRekening::orderBy('kode')->take(2)->get(['kode', 'nama']);

        $rows = [];
        for ($i = 0; $i < 2; $i++) {
            if (isset($programs[$i], $rekenings[$i])) {
                $rows[] = [
                    (string) ($i + 1),
                    $rekenings[$i]->kode,
                    $programs[$i]->kode,
                    'Contoh: ' . $rekenings[$i]->nama,
                    10,
                    'buah',
                    500000,
                    5000000,
                ];
            }
        }

        if (empty($rows)) {
            $rows[] = ['1', '5.1.01.01.001', 'P.001', 'Contoh Belanja', 10, 'buah', 500000, 5000000];
        }

        return $rows;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'No Urut',
            'Kode Rekening',
            'Kode Program',
            'Uraian',
            'Volume',
            'Satuan',
            'Tarif',
            'Jumlah',
        ];
    }

    public function title(): string
    {
        return 'Template RKAS';
    }
}
