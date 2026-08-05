<?php

namespace Tests\Feature\Import;

use App\Imports\RkasImport;
use App\Models\ImportLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\TahunAnggaran;
use App\Models\SumberDana;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasImportUnitTest extends TestCase
{
    use RefreshDatabase;

    private RkasImport $import;
    private TahunAnggaran $tahunAnggaran;
    private SumberDana $sumberDana;
    private ImportLog $importLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahunAnggaran = TahunAnggaran::factory()->create();
        $this->sumberDana = SumberDana::factory()->create();
        MasterProgram::factory()->create(['kode' => '1.1.01']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.02.01.01.0001']);
        $this->importLog = ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'bulan' => 1,
            'sumber_dana_id' => $this->sumberDana->id,
            'status' => 'pending',
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
        ]);

        $this->import = new RkasImport(
            $this->tahunAnggaran->id,
            1,
            $this->sumberDana->id,
            $this->importLog->id,
        );
    }

    public function test_parse_number_with_integer(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['500000']);
        $this->assertSame(500000.0, $result);
    }

    public function test_parse_number_with_float(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['500000.50']);
        $this->assertSame(500000.50, $result);
    }

    public function test_parse_number_with_indonesian_format(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['500.000,50']);
        $this->assertSame(500000.50, $result);
    }

    public function test_parse_number_indonesian_thousands_single_dot(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['2.500']);
        $this->assertSame(2500.0, $result);
    }

    public function test_parse_number_indonesian_thousands_multiple_dots(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['20.993.500']);
        $this->assertSame(20993500.0, $result);
    }

    public function test_parse_number_keeps_us_decimal_with_single_dot(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['500000.50']);
        $this->assertSame(500000.50, $result);
    }

    public function test_model_accepts_indonesian_formatted_jumlah(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => '20.993.500',
        ]);

        $this->assertNull($result);

        $this->assertDatabaseHas('rkas_item', [
            'no_urut' => 1,
            'jumlah' => 20993500.0,
        ]);
    }

    public function test_parse_number_with_comma_decimal(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['500000,50']);
        $this->assertSame(500000.50, $result);
    }

    public function test_parse_number_returns_zero_for_null(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', [null]);
        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function test_parse_number_returns_zero_for_empty_string(): void
    {
        $result = $this->callProtected($this->import, 'parseNumber', ['']);
        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function test_parse_number_rejects_negative_values(): void
    {
        // parseNumber doesn't validate sign, it just parses. Validation happens in model().
        $result = $this->callProtected($this->import, 'parseNumber', ['-5000']);
        $this->assertSame(-5000.0, $result);
    }

    public function test_model_skips_row_without_no_urut(): void
    {
        $result = $this->import->model([
            'no_urut' => '',
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_without_uraian(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => '',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_non_numeric_jumlah(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 'ABC',
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_without_kode_rekening(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_invalid_program(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '99.99.99',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_invalid_kode_rekening(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '99.99.99.99.99.9999',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_negative_jumlah(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => -500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_negative_volume(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => -10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_skips_row_with_negative_tarif(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => -50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);
    }

    public function test_model_creates_rkas_item_and_bulan(): void
    {
        $result = $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertNull($result);

        $this->assertDatabaseHas('rkas_item', [
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'volume' => 10.0,
            'satuan' => 'buah',
            'tarif' => 50000.0,
            'jumlah' => 500000.0,
        ]);

        $rkasItem = RkasItem::where('no_urut', 1)->first();

        $this->assertNotNull($rkasItem);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $rkasItem->id,
            'bulan' => 1,
            'rencana' => 500000.0,
        ]);
    }

    public function test_model_updates_existing_item_with_same_uraian_different_no_urut(): void
    {
        $program = MasterProgram::where('kode', '1.1.01')->first();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0001')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($rekening);

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'no_urut' => 1,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'uraian' => 'Belanja ATK',
            'volume' => 5,
            'jumlah' => 100000,
        ]);

        $this->import->model([
            'no_urut' => 7,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->assertEquals(1, RkasItem::count());
        $this->assertDatabaseHas('rkas_item', [
            'no_urut' => 7,
            'uraian' => 'Belanja ATK',
            'volume' => 10.0,
            'jumlah' => 500000.0,
        ]);
    }

    public function test_model_creates_separate_items_for_different_uraian_same_no_urut(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Item A',
            'volume' => 1,
            'satuan' => 'buah',
            'tarif' => 1000,
            'jumlah' => 100000,
        ]);

        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Item B',
            'volume' => 1,
            'satuan' => 'buah',
            'tarif' => 1000,
            'jumlah' => 200000,
        ]);

        $this->assertEquals(2, RkasItem::count());
    }

    public function test_model_merges_same_item_when_no_urut_shifts_between_months(): void
    {
        $this->import->model([
            'no_urut' => 4,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Honor Pembina Pramuka',
            'volume' => 3,
            'satuan' => 'orang',
            'tarif' => 75000,
            'jumlah' => 225000,
        ]);

        $importBulan2 = new RkasImport(
            $this->tahunAnggaran->id,
            2,
            $this->sumberDana->id,
            $this->importLog->id,
        );

        $importBulan2->model([
            'no_urut' => 8,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Honor Pembina Pramuka',
            'volume' => 3,
            'satuan' => 'orang',
            'tarif' => 75000,
            'jumlah' => 500000,
        ]);

        $this->assertEquals(1, RkasItem::count());

        $item = RkasItem::first();
        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(725000.0, (float) $item->jumlah, 0.001);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 225000.0,
        ]);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 500000.0,
        ]);
    }

    public function test_model_normalizes_uraian_with_double_spaces(): void
    {
        $program = MasterProgram::where('kode', '1.1.01')->first();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0001')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($rekening);

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'no_urut' => 4,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'uraian' => 'Honor  Pembina  Pramuka',
        ]);

        $this->import->model([
            'no_urut' => 4,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Honor Pembina Pramuka',
            'volume' => 3,
            'satuan' => 'orang',
            'tarif' => 75000,
            'jumlah' => 225000,
        ]);

        $this->assertEquals(1, RkasItem::count());
    }

    public function test_model_consolidates_preexisting_duplicates_on_import(): void
    {
        $program = MasterProgram::where('kode', '1.1.01')->first();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0001')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($rekening);

        $itemA = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'no_urut' => 4,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'uraian' => 'Honor Pembina Pramuka',
        ]);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $itemA->id,
            'bulan' => 1,
            'rencana' => 225000,
        ]);

        $itemB = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'no_urut' => 8,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'uraian' => 'Honor Pembina Pramuka',
        ]);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $itemB->id,
            'bulan' => 3,
            'rencana' => 500000,
        ]);

        $this->import->model([
            'no_urut' => 4,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Honor Pembina Pramuka',
            'volume' => 3,
            'satuan' => 'orang',
            'tarif' => 75000,
            'jumlah' => 225000,
        ]);

        $this->assertEquals(1, RkasItem::count());

        $survivor = RkasItem::first();
        $this->assertNotNull($survivor);
        $this->assertSame($itemA->id, $survivor->id);
        $this->assertEqualsWithDelta(725000.0, (float) $survivor->jumlah, 0.001);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $survivor->id,
            'bulan' => 1,
            'rencana' => 225000.0,
        ]);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $survivor->id,
            'bulan' => 3,
            'rencana' => 500000.0,
        ]);
    }

    public function test_model_logs_warning_for_data_row_without_kode_rekening(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '',
            'kode_program' => '1.1.01',
            'uraian' => 'Baris dengan volume tapi tanpa kode rekening',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->importLog->refresh();
        $errors = $this->importLog->error_detail ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('kode rekening kosong', implode(' ', $errors));
    }

    public function test_model_updates_existing_rkas_item_bulan(): void
    {
        $program = MasterProgram::where('kode', '1.1.01')->first();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0001')->first();
        $this->assertNotNull($program);
        $this->assertNotNull($rekening);

        $existing = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'no_urut' => 1,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'uraian' => 'Update Bulan',
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $existing->id,
            'bulan' => 1,
            'rencana' => 100000,
        ]);

        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Update Bulan',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 750000,
        ]);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $existing->id,
            'bulan' => 1,
            'rencana' => 750000.0,
        ]);
        $this->assertEquals(1, RkasItem::count());
    }

    public function test_logs_error_when_program_not_found(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '99.99.99',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->importLog->refresh();
        $this->assertGreaterThan(0, $this->importLog->baris_gagal);
    }

    public function test_increments_berhasil_on_success(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 500000,
        ]);

        $this->importLog->refresh();
        $this->assertEquals(1, $this->importLog->baris_berhasil);
    }

    public function test_start_row_returns_two(): void
    {
        $this->assertSame(2, $this->import->startRow());
    }

    public function test_chunk_size_returns_one_hundred(): void
    {
        $this->assertSame(100, $this->import->chunkSize());
    }

    public function test_model_accumulates_jumlah_across_months(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 250000,
        ]);

        $importBulan2 = new RkasImport(
            $this->tahunAnggaran->id,
            2,
            $this->sumberDana->id,
            $this->importLog->id,
        );

        $importBulan2->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 350000,
        ]);

        $rkasItem = RkasItem::where('no_urut', 1)->first();

        $this->assertNotNull($rkasItem);
        $this->assertEqualsWithDelta(600000.0, (float) $rkasItem->jumlah, 0.001);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $rkasItem->id,
            'bulan' => 1,
            'rencana' => 250000.0,
        ]);
        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $rkasItem->id,
            'bulan' => 2,
            'rencana' => 350000.0,
        ]);
    }

    public function test_model_reimport_same_month_is_idempotent(): void
    {
        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 350000,
        ]);

        $this->import->model([
            'no_urut' => 1,
            'kode_rekening' => '5.1.02.01.01.0001',
            'kode_program' => '1.1.01',
            'uraian' => 'Belanja ATK',
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 50000,
            'jumlah' => 350000,
        ]);

        $rkasItem = RkasItem::where('no_urut', 1)->first();

        $this->assertNotNull($rkasItem);
        $this->assertEqualsWithDelta(350000.0, (float) $rkasItem->jumlah, 0.001);

        $this->assertDatabaseHas('rkas_item_bulan', [
            'rkas_item_id' => $rkasItem->id,
            'bulan' => 1,
            'rencana' => 350000.0,
        ]);
    }

    public function test_model_reads_by_column_index_when_columns_provided(): void
    {
        MasterProgram::factory()->create(['kode' => '02.06.01.']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.02.04.01.0003']);

        $import = new RkasImport(
            $this->tahunAnggaran->id,
            1,
            $this->sumberDana->id,
            $this->importLog->id,
            null,
            [
                'no_urut' => 1,
                'kode_rekening' => 2,
                'kode_program' => 6,
                'uraian' => 10,
                'volume' => 15,
                'satuan' => 17,
                'tarif' => 18,
                'jumlah' => 20,
            ],
            10,
        );

        $row = array_fill(0, 20, '');
        $row[0] = '4';
        $row[1] = '5.1.02.04.01.0003';
        $row[5] = '02. 06. 01.';
        $row[9] = 'Perjalanan Dinas dalam Daerah';
        $row[14] = '45';
        $row[16] = 'orang / kali';
        $row[17] = '30000';
        $row[19] = '1350000';

        $result = $import->model($row);

        $this->assertNull($result);

        $this->assertDatabaseHas('rkas_item', [
            'no_urut' => 4,
            'uraian' => 'Perjalanan Dinas dalam Daerah',
            'volume' => 45.0,
            'satuan' => 'orang / kali',
            'tarif' => 30000.0,
            'jumlah' => 1350000.0,
        ]);
    }

    /** @param array<mixed> $args */
    private function callProtected(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }
}
