<?php

namespace Tests\Feature\Import;

use App\Imports\RkasImportHeaderDetector;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RkasImportHeaderDetectorTest extends TestCase
{
    private const HEADER = ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function storeFile(array $rows, string $name): string
    {
        $export = new class($rows) implements FromArray {
            /** @var array<int, array<int, string>> */
            private array $rows;

            /**
             * @param array<int, array<int, string>> $rows
             */
            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            /** @return array<int, array<int, string>> */
            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, $name, 'local');

        return Storage::disk('local')->path($name);
    }

    public function test_detects_header_on_row_one(): void
    {
        $path = $this->storeFile([
            self::HEADER,
            ['1', '5.1.01.01.001', 'P.001', 'Alat Tulis', '10', 'buah', '1000', '10000'],
        ], 'header_row1.xlsx');

        $this->assertSame(1, RkasImportHeaderDetector::detect($path));
    }

    public function test_detects_header_on_row_two_when_title_above(): void
    {
        $path = $this->storeFile([
            ['JUDUL RKAS 2026'],
            self::HEADER,
            ['1', '5.1.01.01.001', 'P.001', 'Alat Tulis', '10', 'buah', '1000', '10000'],
        ], 'header_row2.xlsx');

        $this->assertSame(2, RkasImportHeaderDetector::detect($path));
    }

    public function test_detects_header_deeper_when_multiple_title_rows(): void
    {
        $path = $this->storeFile([
            ['RKAS'],
            ['NAMA SEKOLAH'],
            self::HEADER,
            ['1', '5.1.01.01.001', 'P.001', 'Alat Tulis', '10', 'buah', '1000', '10000'],
        ], 'header_row3.xlsx');

        $this->assertSame(3, RkasImportHeaderDetector::detect($path));
    }

    public function test_defaults_to_one_when_no_header_found(): void
    {
        $path = $this->storeFile([
            ['sesuatu', 'lain'],
            ['1', '2'],
        ], 'no_header.xlsx');

        $this->assertSame(1, RkasImportHeaderDetector::detect($path));
    }

    public function test_detect_columns_sequential_single_header(): void
    {
        $path = $this->storeFile([
            self::HEADER,
            ['1', '5.1.01.01.001', 'P.001', 'Alat Tulis', '10', 'buah', '1000', '10000'],
        ], 'columns_row1.xlsx');

        $result = RkasImportHeaderDetector::detectColumns($path);

        $this->assertSame(2, $result['start_row']);
        $this->assertSame([
            'no_urut' => 1,
            'kode_rekening' => 2,
            'kode_program' => 3,
            'uraian' => 4,
            'volume' => 5,
            'satuan' => 6,
            'tarif' => 7,
            'jumlah' => 8,
        ], $result['columns']);
    }

    public function test_detect_columns_maps_prd_two_row_header(): void
    {
        $path = $this->storeFile($this->prdRows(), 'prd_header.xlsx');

        $result = RkasImportHeaderDetector::detectColumns($path);

        $this->assertSame(10, $result['start_row']);
        $this->assertSame([
            'no_urut' => 1,
            'kode_rekening' => 2,
            'kode_program' => 6,
            'uraian' => 10,
            'jumlah' => 20,
            'volume' => 15,
            'satuan' => 17,
            'tarif' => 18,
        ], $result['columns']);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function prdRows(): array
    {
        $rows = [];

        $rows[] = ['RINCIAN KERTAS KERJA PERBULAN'];
        $rows[] = ['TAHUN ANGGARAN : 2026'];
        $rows[] = [' '];
        $rows[] = ['A. PENERIMAAN'];
        $rows[] = ['B. BELANJA'];
        $rows[] = [' '];
        $rows[] = [' '];

        $mainHeader = array_fill(0, 20, '');
        $mainHeader[0] = 'No. Urut';
        $mainHeader[1] = 'Kode Rekening';
        $mainHeader[5] = 'Kode Program';
        $mainHeader[9] = 'Uraian';
        $mainHeader[14] = 'Rincian Perhitungan';
        $mainHeader[19] = 'Jumlah';
        $rows[] = $mainHeader;

        $subHeader = array_fill(0, 20, '');
        $subHeader[14] = 'Volume';
        $subHeader[16] = 'Satuan';
        $subHeader[17] = 'Tarif Harga';
        $rows[] = $subHeader;

        $data = array_fill(0, 20, '');
        $data[0] = '4';
        $data[1] = '5.1.02.04.01.0003';
        $data[5] = '02. 06. 01.';
        $data[9] = 'Perjalanan Dinas dalam Daerah';
        $data[14] = '45';
        $data[16] = 'orang / kali';
        $data[17] = '30000';
        $data[19] = '1350000';
        $rows[] = $data;

        return $rows;
    }
}
