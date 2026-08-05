<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MasterKodeRekeningTemplateExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['5.1.02.01.01.0001', 'Belanja Bahan Bangunan'],
            ['5.1.02.01.01.0026', 'Belanja Cetak & Fotokopi'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Kode Rekening',
            'Nama Rekening',
        ];
    }

    public function title(): string
    {
        return 'Master Kode Rekening';
    }
}
