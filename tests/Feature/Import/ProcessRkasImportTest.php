<?php

namespace Tests\Feature\Import;

use App\Jobs\ProcessRkasImport;
use App\Models\AuditLog;
use App\Models\ImportLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProcessRkasImportTest extends TestCase
{
    use RefreshDatabase;

    private TahunAnggaran $tahunAnggaran;
    private SumberDana $sumberDana;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahunAnggaran = TahunAnggaran::factory()->create();
        $this->sumberDana = SumberDana::factory()->create();
        $this->user = User::factory()->create();
    }

    public function test_does_nothing_when_import_log_not_found(): void
    {
        Excel::fake();

        ProcessRkasImport::dispatch('999', 'import_rkas/nonexistent.xlsx');

        $this->assertDatabaseMissing('import_log', ['id' => '999']);
    }

    public function test_deletes_existing_rkas_item_bulan_before_import(): void
    {
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $item2 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item2->id,
            'bulan' => 1,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
        ]);

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'status' => 'pending',
            'file_path' => 'import_rkas/test.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        // bulan 1 items should be deleted
        $this->assertEquals(0, RkasItemBulan::where('rkas_item_id', $item->id)
            ->where('bulan', 1)
            ->count());
        $this->assertEquals(0, RkasItemBulan::where('rkas_item_id', $item2->id)
            ->where('bulan', 1)
            ->count());

        // bulan 2 should remain
        $this->assertEquals(1, RkasItemBulan::where('rkas_item_id', $item->id)
            ->where('bulan', 2)
            ->count());
    }

    public function test_sets_failed_when_zero_berhasil(): void
    {
        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'status' => 'pending',
            'file_path' => 'import_rkas/test.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertStringContainsString(
            'Tidak ada data yang berhasil diimpor',
            ($log->error_detail ?? [''])[0] ?? ''
        );
    }

    public function test_sets_success_and_creates_audit_log_when_berhasil_positive(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('import_rkas/valid_test.xlsx', 'dummy');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 3,
            'status' => 'pending',
            'file_path' => 'import_rkas/valid_test.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 5,
            'baris_gagal' => 1,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertEquals(6, $log->total_baris);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'import_rkas',
            'aksi' => 'import',
        ]);
    }

    public function test_cleans_up_file_on_success(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('import_rkas/to_clean.xlsx', 'content');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'status' => 'pending',
            'file_path' => 'import_rkas/to_clean.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 3,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        Storage::disk('local')->assertMissing('import_rkas/to_clean.xlsx');

        $log->refresh();
        $this->assertNull($log->file_path);
    }

    public function test_cleans_up_file_on_failure(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('import_rkas/fail_clean.xlsx', 'content');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 4,
            'status' => 'pending',
            'file_path' => 'import_rkas/fail_clean.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        Storage::disk('local')->assertMissing('import_rkas/fail_clean.xlsx');

        $log->refresh();
        $this->assertNull($log->file_path);
    }

    public function test_handles_exception_gracefully(): void
    {
        Storage::fake('local');
        $filePath = 'import_rkas/exception_test.xlsx';
        Storage::disk('local')->put($filePath, 'content');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 5,
            'status' => 'pending',
            'file_path' => $filePath,
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        $mock = $this->createMock(\Maatwebsite\Excel\Fakes\ExcelFake::class);
        $mock->method('import')->willThrowException(new \Exception('Simulated error'));
        Excel::swap($mock);

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertNotNull($log->finished_at);

        Storage::disk('local')->assertMissing($filePath);
        $this->assertNull($log->file_path);
    }

    public function test_imports_file_with_title_row_above_header(): void
    {
        MasterProgram::factory()->create(['kode' => 'P.001.01', 'nama' => 'Program Kegiatan']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.01.01.001', 'nama' => 'Belanja Alat Tulis']);

        Storage::fake('local');

        $export = new class implements FromArray {
            /** @return array<int, array<int, string>> */
            public function array(): array
            {
                return [
                    ['JUDUL RKAS 2026'],
                    ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
                    ['1', '5.1.01.01.001', 'P.001.01', 'Alat Tulis Kantor', '10', 'buah', '1000', '10000'],
                ];
            }
        };

        Excel::store($export, 'import_rkas/titled.xlsx', 'local');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'status' => 'pending',
            'file_path' => 'import_rkas/titled.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        ProcessRkasImport::dispatch($log->id, Storage::disk('local')->path($log->file_path));

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(1, $log->baris_berhasil);

        $item = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals('Alat Tulis Kantor', $item->uraian);

        $bulan = RkasItemBulan::where('rkas_item_id', $item->id)
            ->where('bulan', 2)
            ->first();

        $this->assertNotNull($bulan);
        $this->assertEquals(10000, (float) $bulan->rencana);
    }

    public function test_imports_prd_file_with_two_row_header_and_sparse_columns(): void
    {
        MasterProgram::factory()->create(['kode' => '02.06.01.', 'nama' => 'Program 02']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.02.04.01.0003', 'nama' => 'Perjalanan Dinas']);

        Storage::fake('local');

        $export = new class implements FromArray {
            /** @return array<int, array<int, string>> */
            public function array(): array
            {
                $mainHeader = array_fill(0, 20, '');
                $mainHeader[0] = 'No. Urut';
                $mainHeader[1] = 'Kode Rekening';
                $mainHeader[5] = 'Kode Program';
                $mainHeader[9] = 'Uraian';
                $mainHeader[14] = 'Rincian Perhitungan';
                $mainHeader[19] = 'Jumlah';

                $subHeader = array_fill(0, 20, '');
                $subHeader[14] = 'Volume';
                $subHeader[16] = 'Satuan';
                $subHeader[17] = 'Tarif Harga';

                $data = array_fill(0, 20, '');
                $data[0] = '4';
                $data[1] = '5.1.02.04.01.0003';
                $data[5] = '02. 06. 01.';
                $data[9] = 'Perjalanan Dinas dalam Daerah';
                $data[14] = '45';
                $data[16] = 'orang / kali';
                $data[17] = '30000';
                $data[19] = '1350000';

                return [
                    ['RINCIAN KERTAS KERJA PERBULAN'],
                    ['TAHUN ANGGARAN : 2026'],
                    [' '],
                    ['A. PENERIMAAN'],
                    ['B. BELANJA'],
                    [' '],
                    [' '],
                    $mainHeader,
                    $subHeader,
                    $data,
                ];
            }
        };

        Excel::store($export, 'import_rkas/prd.xlsx', 'local');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'status' => 'pending',
            'file_path' => 'import_rkas/prd.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        ProcessRkasImport::dispatch($log->id, Storage::disk('local')->path($log->file_path));

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(1, $log->baris_berhasil);

        $item = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->where('uraian', 'Perjalanan Dinas dalam Daerah')
            ->first();

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(45.0, (float) $item->volume, 0.001);
        $this->assertEquals('orang / kali', $item->satuan);
        $this->assertEqualsWithDelta(30000.0, (float) $item->tarif, 0.001);
        $this->assertEqualsWithDelta(1350000.0, (float) $item->jumlah, 0.001);
        $this->assertEquals(1, (int) $item->no_urut);
    }

    public function test_merges_same_item_when_no_urut_shifts_between_monthly_files_and_imports_tari(): void
    {
        MasterProgram::factory()->create(['kode' => '03.03.06.', 'nama' => 'Pelaksanaan Ekstrakurikuler Kepramukaan']);
        MasterProgram::factory()->create(['kode' => '03.03.07.', 'nama' => 'Pelaksanaan Kegiatan Ekstrakurikuler (diluar Kepramukaan)']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.02.02.01.0003', 'nama' => 'Honorarium']);

        Storage::fake('local');

        $buildExport = function (array $dataRows): FromArray {
            return new class($dataRows) implements FromArray {
                /** @var array<int, array<int, string>> */
                private array $dataRows;

                /**
                 * @param array<int, array<int, string>> $dataRows
                 */
                public function __construct(array $dataRows)
                {
                    $this->dataRows = $dataRows;
                }

                /** @return array<int, array<int, string>> */
                public function array(): array
                {
                    $mainHeader = array_fill(0, 20, '');
                    $mainHeader[0] = 'No. Urut';
                    $mainHeader[1] = 'Kode Rekening';
                    $mainHeader[5] = 'Kode Program';
                    $mainHeader[9] = 'Uraian';
                    $mainHeader[14] = 'Rincian Perhitungan';
                    $mainHeader[19] = 'Jumlah';

                    $subHeader = array_fill(0, 20, '');
                    $subHeader[14] = 'Volume';
                    $subHeader[16] = 'Satuan';
                    $subHeader[17] = 'Tarif Harga';

                    $rows = [
                        ['RINCIAN KERTAS KERJA PERBULAN'],
                        ['TAHUN ANGGARAN : 2026'],
                        [' '],
                        ['A. PENERIMAAN'],
                        ['B. BELANJA'],
                        [' '],
                        [' '],
                        $mainHeader,
                        $subHeader,
                    ];

                    return array_merge($rows, $this->dataRows);
                }
            };
        };

        $row = function (string $noUrut, string $uraian, string $jumlah): array {
            $data = array_fill(0, 20, '');
            $data[0] = $noUrut;
            $data[1] = '5.1.02.02.01.0003';
            $data[5] = '03. 03. 06.';
            $data[9] = $uraian;
            $data[14] = '3';
            $data[16] = 'orang kegiatan';
            $data[17] = '75000';
            $data[19] = $jumlah;

            return $data;
        };

        // Bulan 1: Pramuka di no_urut 4
        $exportBulan1 = $buildExport([
            $row('4', 'Honor Pembina Pramuka', '225000'),
        ]);
        Excel::store($exportBulan1, 'import_rkas/bulan1.xlsx', 'local');

        $logBulan1 = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'status' => 'pending',
            'file_path' => 'import_rkas/bulan1.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        ProcessRkasImport::dispatch($logBulan1->id, Storage::disk('local')->path($logBulan1->file_path));

        $logBulan1->refresh();
        $this->assertEquals('success', $logBulan1->status);

        // Bulan 2: Pramuka yang sama bergeser ke no_urut 8 + item baru Tari (no_urut 9)
        $exportBulan2 = $buildExport([
            $row('8', 'Honor Pembina Pramuka', '500000'),
            $row('9', 'Honor Pembina Ekstra Tari', '300000'),
        ]);
        Excel::store($exportBulan2, 'import_rkas/bulan2.xlsx', 'local');

        $logBulan2 = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'status' => 'pending',
            'file_path' => 'import_rkas/bulan2.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        ProcessRkasImport::dispatch($logBulan2->id, Storage::disk('local')->path($logBulan2->file_path));

        $logBulan2->refresh();
        $this->assertEquals('success', $logBulan2->status);

        $pramuka = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->where('uraian', 'Honor Pembina Pramuka')
            ->get();

        $this->assertCount(1, $pramuka);
        $pramukaItem = $pramuka->first();
        $this->assertNotNull($pramukaItem);
        $this->assertEqualsWithDelta(725000.0, (float) $pramukaItem->jumlah, 0.001);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $pramukaItem->id,
            'bulan' => 1,
            'rencana' => 225000.0,
        ]);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $pramukaItem->id,
            'bulan' => 2,
            'rencana' => 500000.0,
        ]);

        $tari = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->where('uraian', 'Honor Pembina Ekstra Tari')
            ->get();

        $this->assertCount(1, $tari);

        // Renumber otomatis: no_urut unik berurutan (1..N) untuk tahun ini
        $noUruts = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->orderBy('no_urut')
            ->pluck('no_urut')
            ->map(fn ($n): int => (int) $n)
            ->values()
            ->all();

        $this->assertSame(range(1, count($noUruts)), $noUruts);
    }

    public function test_renumbers_and_syncs_jumlah_after_successful_import(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('import_rkas/renumber_test.xlsx', 'dummy');

        // Dua item no_urut sama + jumlah salah di tahun ini
        $itemA = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'no_urut' => 4,
            'jumlah' => 0,
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $itemA->id, 'bulan' => 1, 'rencana' => 100000]);

        $itemB = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'no_urut' => 4,
            'jumlah' => 0,
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $itemB->id, 'bulan' => 1, 'rencana' => 50000]);

        // Tahun lain dengan no_urut sama -> tidak boleh tersentuh
        $tahunLain = TahunAnggaran::factory()->create();
        $itemLain = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahunLain->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'no_urut' => 4,
            'jumlah' => 0,
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $itemLain->id, 'bulan' => 1, 'rencana' => 100000]);

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 3,
            'status' => 'pending',
            'file_path' => 'import_rkas/renumber_test.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 5,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        $log->refresh();
        $this->assertEquals('success', $log->status);

        // no_urut tahun ini unik berurutan 1..N
        $noUruts = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaran->id)
            ->orderBy('id')
            ->pluck('no_urut')
            ->map(fn ($n): int => (int) $n)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([1, 2], $noUruts);
        $this->assertCount(2, array_unique($noUruts));

        // jumlah di-sync = sum rencana
        $itemA->refresh();
        $itemB->refresh();
        $this->assertEqualsWithDelta(100000.0, (float) $itemA->jumlah, 0.001);
        $this->assertEqualsWithDelta(50000.0, (float) $itemB->jumlah, 0.001);

        // tahun lain tidak tersentuh
        $itemLain->refresh();
        $this->assertEquals(4, (int) $itemLain->no_urut);
        $this->assertEqualsWithDelta(0.0, (float) $itemLain->jumlah, 0.001);
    }

    public function test_creates_audit_log_entry(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('import_rkas/audit_test.xlsx', 'dummy');

        $log = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 6,
            'status' => 'pending',
            'file_path' => 'import_rkas/audit_test.xlsx',
            'uploaded_by' => $this->user->id,
            'baris_berhasil' => 2,
            'baris_gagal' => 0,
        ]);

        Excel::fake();

        ProcessRkasImport::dispatch($log->id, $log->file_path);

        $audit = AuditLog::where('tabel', 'import_rkas')->first();
        $this->assertNotNull($audit);
        $this->assertSame('import', $audit->aksi);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertSame(6, $audit->data_baru['bulan'] ?? null);
    }
}
