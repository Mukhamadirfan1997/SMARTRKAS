<?php

namespace App\Imports;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterKodeRekeningImport implements WithMultipleSheets
{
    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            0 => new class implements ToModel, WithHeadingRow {
                /** @var array<string, string> */
                private array $rules = [
                    '5.1.02.01.01.0026' => 'Belanja Cetak',
                    '5.1.02.01' => 'Belanja Barang Persediaan',
                    '5.1.02.02' => 'Belanja Jasa',
                    '5.1.02.03' => 'Belanja Jasa Pemeliharaan',
                    '5.1.02.04' => 'Belanja Perjalanan Dinas',
                    '5.2.02.10' => 'Belanja Modal Peralatan & Mesin',
                    '5.2.05' => 'Belanja Modal Buku',
                    '5.2.02.05' => 'Belanja Modal Aset Tetap Lainnya',
                    '5.2' => 'Belanja Modal Peralatan & Mesin',
                    '5.1' => 'Belanja Lainnya',
                ];

                /** @var array<string, int> */
                private array $jenisMapCache = [];

                /**
                 * @param array<string, mixed> $row
                 */
                public function model(array $row): ?Model
                {
                    $kode = trim((string) ($row['kode_barang'] ?? $row['kode_rekening'] ?? ''));
                    $nama = trim((string) ($row['rincian_objek'] ?? $row['nama_rekening'] ?? ''));

                    if ($kode === '' || $nama === '') {
                        return null;
                    }

                    $namaJenis = 'Belanja Lainnya';

                    $prefixes = array_keys($this->rules);
                    usort($prefixes, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

                    foreach ($prefixes as $prefix) {
                        if (str_starts_with($kode, $prefix)) {
                            $namaJenis = $this->rules[$prefix];
                            break;
                        }
                    }
                    if (!isset($this->jenisMapCache[$namaJenis])) {
                        $jenisObj = JenisBelanja::firstOrCreate(['nama' => $namaJenis]);
                        $this->jenisMapCache[$namaJenis] = $jenisObj->id;
                    }

                    return new MasterKodeRekening([
                        'kode' => $kode,
                        'nama' => $nama,
                        'jenis_belanja_id' => $this->jenisMapCache[$namaJenis],
                    ]);
                }
            },
        ];
    }
}
