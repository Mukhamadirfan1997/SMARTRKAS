<?php

namespace Tests\Feature\Import;

use App\Imports\ImportRevisiImport;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\RkasRevisi;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use App\Support\NomorDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportRevisiImportTest extends TestCase
{
    use RefreshDatabase;

    private TahunAnggaran $tahun;

    private SumberDana $sumberDana;

    private MasterProgram $program;

    private MasterKodeRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
        $this->sumberDana = SumberDana::factory()->create();
        $this->program = MasterProgram::factory()->create(['kode' => 'P.001.01']);
        $this->rekening = MasterKodeRekening::factory()->create(['kode' => '5.1.01.01.001']);
    }

    /**
     * Buat file excel bulanan di disk 'local' fake dan kembalikan path-nya.
     *
     * @param array<int, array<int, mixed>> $rows
     */
    private function makeFile(string $name, array $rows): string
    {
        Storage::fake('local');

        $export = new class($rows) implements FromArray {
            /** @var array<int, array<int, mixed>> */
            private array $rows;

            /** @param array<int, array<int, mixed>> $rows */
            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            /** @return array<int, array<int, mixed>> */
            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, 'uploads/' . $name, 'local');

        return Storage::disk('local')->path('uploads/' . $name);
    }

    private function makeItem(string $uraian): RkasItem
    {
        return RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'uraian' => $uraian,
        ]);
    }

    private function makeRencana(RkasItem $item, int $bulan, float $rencana): void
    {
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => $bulan,
            'rencana' => $rencana,
        ]);
    }

    private function makeParser(string $jenis = 'pergeseran'): ImportRevisiImport
    {
        return new ImportRevisiImport($this->tahun->id, $this->sumberDana->id, $jenis);
    }

    public function test_validate_passes_when_net_zero_lolos(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $itemB = $this->makeItem('ATK Ruang Kelas');
        $this->makeRencana($itemA, 1, 100000);
        $this->makeRencana($itemB, 1, 200000);

        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
            ['2', '5.1.01.01.001', 'P.001.01', 'ATK Ruang Kelas', '10', 'buah', '1000', '250000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $this->assertSame([], $diff['errors']);
        $this->assertCount(2, $diff['rows']);

        $this->assertSame(-50000.0, (float) $diff['rows'][0]['delta']);
        $this->assertSame('turun', $diff['rows'][0]['arah']);
        $this->assertSame(50000.0, (float) $diff['rows'][1]['delta']);
        $this->assertSame('naik', $diff['rows'][1]['arah']);

        $result = $this->makeParser()->validate($diff['rows']);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
    }

    public function test_validate_fails_when_net_zero_tidak_seimbang(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $this->makeRencana($itemA, 1, 100000);

        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $result = $this->makeParser()->validate($diff['rows']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Net-zero tidak seimbang', $result['errors'][0] ?? '');
    }

    public function test_validate_menolak_item_sumber_yang_sudah_ber_realisasi(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $this->makeRencana($itemA, 1, 100000);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => $itemA->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 40000,
        ]);

        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $this->assertSame(40000.0, (float) $diff['rows'][0]['realisasi']);

        $result = $this->makeParser()->validate($diff['rows']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('menjadi SUMBER', $result['errors'][0] ?? '');
    }

    public function test_item_yang_tidak_ada_di_file_dibiarkan(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $itemB = $this->makeItem('ATK Ruang Kelas');
        $this->makeRencana($itemA, 1, 100000);
        $this->makeRencana($itemB, 1, 200000);

        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $this->assertCount(1, $diff['rows']);
        $this->assertSame('ATK Kantor', $diff['rows'][0]['uraian']);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $itemB->id,
            'bulan' => 1,
            'rencana' => 200000,
        ]);
    }

    public function test_diff_memproduksi_item_baru_saat_item_tidak_ditemukan(): void
    {
        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Baru Belum Ada', '10', 'buah', '1000', '150000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $this->assertSame([], $diff['errors']);
        $this->assertCount(1, $diff['rows']);

        $this->assertNull($diff['rows'][0]['rkas_item_id']);
        $this->assertSame(0.0, (float) $diff['rows'][0]['sebelum']);
        $this->assertSame(150000.0, (float) $diff['rows'][0]['sesudah']);
        $this->assertSame(150000.0, (float) $diff['rows'][0]['delta']);
        $this->assertSame('naik', $diff['rows'][0]['arah']);
        $this->assertSame($this->rekening->jenis_belanja_id, $diff['rows'][0]['jenis_belanja_id']);
    }

    public function test_diff_menolak_jumlah_negatif(): void
    {
        $path = $this->makeFile('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '-50000'],
        ]);

        $diff = $this->makeParser()->diff($path, 1);

        $this->assertCount(0, $diff['rows']);
        $this->assertStringContainsString('tidak boleh negatif', $diff['errors'][0] ?? '');
    }

    public function test_no_revisi_format_pergeseran_dan_pak(): void
    {
        PengaturanSekolah::create([
            'npsn' => '20519260',
            'nama' => 'SDN Contoh',
        ]);

        $this->assertSame(
            'PGS-0001/20519260/01/2026',
            NomorDokumen::noRevisi('pergeseran', '2026-01-15')
        );
        $this->assertSame(
            'PAK-0001/20519260/01/2026',
            NomorDokumen::noRevisi('pak', '2026-01-15')
        );

        RkasRevisi::factory()->create([
            'no_revisi' => 'PGS-0001/20519260/01/2026',
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertSame(
            'PGS-0002/20519260/01/2026',
            NomorDokumen::noRevisi('pergeseran', '2026-01-20')
        );
    }
}
