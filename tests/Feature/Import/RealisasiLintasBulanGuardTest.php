<?php

namespace Tests\Feature\Import;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\NotaBkuItem;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RealisasiLintasBulanGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TahunAnggaran $tahun;

    private SumberDana $sumberDana;

    private MasterProgram $program;

    private MasterKodeRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
        $this->sumberDana = SumberDana::factory()->create();
        $this->program = MasterProgram::factory()->create(['kode' => 'P.001.01']);
        $this->rekening = MasterKodeRekening::factory()->create(['kode' => '5.1.01.01.001']);

        PengaturanSekolah::create([
            'npsn' => '20519260',
            'nama' => 'SDN Contoh',
        ]);
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

    /** @param array<int, array<int, string>> $rows */
    private function makeUpload(string $name, array $rows): UploadedFile
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

        return new UploadedFile(
            Storage::disk('local')->path('uploads/' . $name),
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** @param array<int, array<int, string>> $rows
     *  @return \Illuminate\Testing\TestResponse<\Illuminate\Http\RedirectResponse>
     */
    private function postPergeseran(array $rows): \Illuminate\Testing\TestResponse
    {
        $upload = $this->makeUpload('bulan1.xlsx', array_merge([
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
        ], $rows));

        return $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);
    }

    public function test_a_realisasi_transaksi_biasa_ditolak(): void
    {
        $item = $this->makeItem('ATK Kantor');
        $this->makeRencana($item, 1, 100000);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 40000,
        ]);

        $this->postPergeseran([
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $this->assertDatabaseCount('rkas_revisi', 0);
    }

    public function test_b_realisasi_nota_ditolak(): void
    {
        $item = $this->makeItem('ATK Kantor');
        $this->makeRencana($item, 1, 100000);

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);

        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'urutan' => 1,
            'jumlah' => 4,
            'satuan' => 'buah',
            'harga_satuan' => 10000,
            'subtotal' => 40000,
        ]);

        TransaksiBku::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => null,
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 40000,
            'created_by' => $this->user->id,
        ]);

        $realisasi = (float) $item->realisasiTotal();
        $this->assertSame(40000.0, $realisasi);

        $this->postPergeseran([
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $this->assertDatabaseCount('rkas_revisi', 0);
    }

    public function test_d_celah_bulan_realisasi_lebih_akhir_dari_bulan_file(): void
    {
        $sumber = $this->makeItem('ATK Sumber');
        $target = $this->makeItem('ATK Target');
        $this->makeRencana($sumber, 1, 100000);
        $this->makeRencana($target, 1, 200000);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => $sumber->id,
            'tanggal' => '2026-03-10',
            'bulan' => 3,
            'no_bukti' => 'BPU001/20519260/03/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 40000,
            'created_by' => $this->user->id,
        ]);

        $this->assertSame(0.0, (float) $sumber->realisasiKumulatifSd(1));
        $this->assertSame(40000.0, (float) $sumber->realisasiKumulatifSd(3));
        $this->assertSame(40000.0, (float) $sumber->realisasiTotal());

        $this->postPergeseran([
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Sumber', '10', 'buah', '1000', '50000'],
            ['2', '5.1.01.01.001', 'P.001.01', 'ATK Target', '10', 'buah', '1000', '250000'],
        ]);

        $this->assertDatabaseCount('rkas_revisi', 0);
        $this->assertSame(100000.0, (float) RkasItemBulan::where('rkas_item_id', $sumber->id)->where('bulan', 1)->value('rencana'));
    }

    public function test_c_update_rkas_item_menurunkan_jumlah_item_ber_realisasi_ditolak(): void
    {
        $item = $this->makeItem('ATK Kantor');
        $item->update(['jumlah' => 100000, 'tarif' => 10000, 'volume' => 10, 'satuan' => 'buah']);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 40000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put('/rkas/' . $item->id, [
            'no_urut' => $item->no_urut,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'uraian' => 'ATK Kantor',
            'volume' => '10',
            'satuan' => 'buah',
            'tarif' => '10000',
            'jumlah' => '50000',
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertSessionHasErrors('jumlah');
        $response->assertRedirect();
        $this->assertSame(100000.0, (float) $item->fresh()->jumlah);
    }

    public function test_c2_update_rkas_item_menurunkan_jumlah_tanpa_realisasi_tetap_boleh(): void
    {
        $item = $this->makeItem('ATK Kantor');
        $item->update(['jumlah' => 100000, 'tarif' => 10000, 'volume' => 10, 'satuan' => 'buah']);

        $response = $this->actingAs($this->user)->put('/rkas/' . $item->id, [
            'no_urut' => $item->no_urut,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'uraian' => 'ATK Kantor',
            'volume' => '10',
            'satuan' => 'buah',
            'tarif' => '10000',
            'jumlah' => '50000',
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertRedirect(route('rkas.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(50000.0, (float) $item->fresh()->jumlah);
    }
}

