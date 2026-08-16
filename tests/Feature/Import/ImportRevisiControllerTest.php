<?php

namespace Tests\Feature\Import;

use App\Models\ImportLog;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportRevisiControllerTest extends TestCase
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

    /**
     * Buat file excel bulanan di disk 'local' fake dan kembalikan UploadedFile.
     *
     * @param array<int, array<int, mixed>> $rows
     */
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/import-revisi')->assertRedirect('/login');
    }

    public function test_index_shows_page(): void
    {
        $response = $this->actingAs($this->user)->get('/import-revisi');

        $response->assertOk();
        $response->assertSee('Import Revisi Anggaran');
        $response->assertSee('Riwayat Revisi');
    }

    public function test_index_warns_when_no_active_tahun(): void
    {
        $this->tahun->update(['status' => false]);

        $response = $this->actingAs($this->user)->get('/import-revisi');

        $response->assertOk();
        $response->assertSee('Tahun anggaran belum diaktifkan');
        $response->assertDontSee('Upload File Revisi');
    }

    public function test_store_requires_files(): void
    {
        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors('files');
    }

    public function test_store_returns_error_when_no_active_tahun(): void
    {
        $this->tahun->update(['status' => false]);

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_store_net_zero_success_creates_revisi_and_applies_changes(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $itemB = $this->makeItem('ATK Ruang Kelas');
        $this->makeRencana($itemA, 1, 100000);
        $this->makeRencana($itemB, 1, 200000);

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
            ['2', '5.1.01.01.001', 'P.001.01', 'ATK Ruang Kelas', '10', 'buah', '1000', '250000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
            'keterangan' => 'Pergeseran Januari',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rkas_revisi', [
            'no_revisi' => 'PGS-0001/20519260/01/2026',
            'jenis' => 'pergeseran',
            'sebelum_total' => 300000,
            'sesudah_total' => 300000,
        ]);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $itemA->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $itemB->id,
            'bulan' => 1,
            'rencana' => 250000,
        ]);

        $this->assertSame(2, RkasRevisi::firstOrFail()->items()->count());

        $log = ImportLog::where('bulan', 1)->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertNull($log->file_path);
    }

    public function test_store_net_zero_imbalance_rejects_everything(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $this->makeRencana($itemA, 1, 100000);

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseCount('rkas_revisi', 0);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $itemA->id,
            'bulan' => 1,
            'rencana' => 100000,
        ]);

        $log = ImportLog::where('bulan', 1)->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->error_detail);
        $this->assertNull($log->file_path);
    }

    public function test_store_rejects_source_item_with_realisasi(): void
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

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseCount('rkas_revisi', 0);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $itemA->id,
            'bulan' => 1,
            'rencana' => 100000,
        ]);
    }

    public function test_store_creates_new_item_when_not_found(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $this->makeRencana($itemA, 1, 200000);

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
            ['2', '5.1.01.01.001', 'P.001.01', 'ATK Baru Belum Ada', '10', 'buah', '1000', '150000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rkas_item', [
            'uraian' => 'ATK Baru Belum Ada',
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
        ]);

        $item = RkasItem::where('uraian', 'ATK Baru Belum Ada')->firstOrFail();

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 150000,
        ]);

        $this->assertSame(150000, (int) $item->fresh()->jumlah);
    }

    public function test_store_pak_generates_pak_no_revisi(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $itemB = $this->makeItem('ATK Ruang Kelas');
        $this->makeRencana($itemA, 1, 100000);
        $this->makeRencana($itemB, 1, 200000);

        $upload = $this->makeUpload('bulan1.xlsx', [
            ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
            ['1', '5.1.01.01.001', 'P.001.01', 'ATK Kantor', '10', 'buah', '1000', '50000'],
            ['2', '5.1.01.01.001', 'P.001.01', 'ATK Ruang Kelas', '10', 'buah', '1000', '250000'],
        ]);

        $response = $this->actingAs($this->user)->post('/import-revisi', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
            'jenis' => 'pak',
            'tanggal' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rkas_revisi', [
            'no_revisi' => 'PAK-0001/20519260/01/2026',
            'jenis' => 'pak',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'rkas_revisi',
            'aksi' => 'import_pak',
        ]);
    }

    public function test_show_renders_detail_page(): void
    {
        $itemA = $this->makeItem('ATK Kantor');
        $this->makeRencana($itemA, 1, 100000);

        $revisi = RkasRevisi::factory()->create([
            'no_revisi' => 'PGS-0001/20519260/01/2026',
            'jenis' => 'pergeseran',
            'tanggal' => '2026-01-15',
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'sebelum_total' => 100000,
            'sesudah_total' => 50000,
            'data_perubahan' => [1 => ['jumlah_item' => 1, 'selisih' => -50000]],
            'created_by' => $this->user->id,
        ]);

        $revisi->items()->create([
            'rkas_item_id' => $itemA->id,
            'bulan' => 1,
            'arah' => 'turun',
            'sebelum' => 100000,
            'sesudah' => 50000,
            'delta' => -50000,
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/import-revisi/' . $revisi->id);

        $response->assertOk();
        $response->assertSee('PGS-0001/20519260/01/2026');
        $response->assertSee('ATK Kantor');
        $response->assertSee('TURUN');
        $response->assertSee('Rp 100.000');
    }
}
