<?php

namespace Tests\Feature\Export;

use App\Exports\BkuExport;
use App\Exports\RekapRekeningExport;
use App\Exports\RekapSiplahExport;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class ExportAmountFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
        $sumber = SumberDana::factory()->create();
        $program = MasterProgram::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'no_urut' => 1,
            'uraian' => 'Belanja Alat Tulis Kantor',
            'jumlah' => 225000,
        ]);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 225000,
        ]);
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber->id,
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 225000,
            'no_bukti' => 'BPU001/00000000/01/2026',
            'metode_pengadaan' => 'siplah',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_bku_export_writes_amount_as_numeric_with_number_format(): void
    {
        Storage::fake('local');
        Excel::store(new BkuExport(1, 'Sekolah_Test', $this->tahun->id), 'bku.xlsx', 'local');

        $sheet = $this->loadSheet('bku.xlsx');
        $this->assertNumericAmountCell($sheet, 225000.0);
        $this->assertNumericAmountCell($sheet, -225000.0);
        $this->assertNumericAmountCell($sheet, 0.0);
        $this->assertNoStringAmount($sheet, '225.000');
    }

    public function test_rekap_rekening_export_writes_amount_as_numeric_with_number_format(): void
    {
        Storage::fake('local');
        Excel::store(new RekapRekeningExport(1, $this->tahun->id), 'rekap.xlsx', 'local');

        $sheet = $this->loadSheet('rekap.xlsx');
        $this->assertNumericAmountCell($sheet, 225000.0);
        $this->assertNumericAmountCell($sheet, 0.0);
        $this->assertNoStringAmount($sheet, '225.000');
    }

    public function test_rekap_siplah_export_writes_amount_as_numeric_with_number_format(): void
    {
        Storage::fake('local');
        Excel::store(new RekapSiplahExport([1], 'Januari 2026', $this->tahun->id), 'siplah.xlsx', 'local');

        $sheet = $this->loadSheet('siplah.xlsx');
        $this->assertNumericAmountCell($sheet, 225000.0);
        $this->assertNumericAmountCell($sheet, 0.0);
        $this->assertNoStringAmount($sheet, '225.000');
    }

    private function loadSheet(string $file): Worksheet
    {
        return IOFactory::load(Storage::disk('local')->path($file))->getActiveSheet();
    }

    private function assertNumericAmountCell(Worksheet $sheet, float $expected): void
    {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ($cell->getDataType() === DataType::TYPE_NUMERIC && (float) $cell->getValue() === $expected) {
                    $this->assertStringContainsString('#,##0', $cell->getStyle()->getNumberFormat()->getFormatCode());

                    return;
                }
            }
        }

        $this->fail("Sel numerik {$expected} tidak ditemukan di file export.");
    }

    private function assertNoStringAmount(Worksheet $sheet, string $forbidden): void
    {
        $bad = [];
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ($cell->getDataType() === DataType::TYPE_STRING && $cell->getValue() === $forbidden) {
                    $bad[] = $forbidden;
                }
            }
        }

        $this->assertSame([], $bad, "Masih ada string nominal '{$forbidden}' di file export.");
    }
}
