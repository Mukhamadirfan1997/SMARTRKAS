<?php

namespace Tests\Feature\RKAS;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeduplicateRkasTest extends TestCase
{
    use RefreshDatabase;

    private TahunAnggaran $tahun;
    private SumberDana $sumber;
    private MasterProgram $program;
    private MasterKodeRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahun = TahunAnggaran::factory()->create();
        $this->sumber = SumberDana::factory()->create();
        $this->program = MasterProgram::factory()->create();
        $this->rekening = MasterKodeRekening::factory()->create();
    }

    private function makeItem(int $noUrut, string $uraian, float $jumlah): RkasItem
    {
        return RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => $noUrut,
            'uraian' => $uraian,
            'jumlah' => $jumlah,
        ]);
    }

    public function test_merges_duplicates_by_uraian_program_and_rekening(): void
    {
        $itemA = $this->makeItem(4, 'Honor  Pembina  Pramuka', 225000);
        RkasItemBulan::factory()->create(['rkas_item_id' => $itemA->id, 'bulan' => 1, 'rencana' => 225000]);

        $itemB = $this->makeItem(8, 'Honor Pembina Pramuka', 500000);
        RkasItemBulan::factory()->create(['rkas_item_id' => $itemB->id, 'bulan' => 2, 'rencana' => 500000]);

        $transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'rkas_item_id' => $itemB->id,
        ]);

        $this->artisan('rkas:dedup', [
            '--tahun' => (string) $this->tahun->id,
        ])->assertSuccessful();

        $this->assertCount(1, RkasItem::all());

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
            'bulan' => 2,
            'rencana' => 500000.0,
        ]);

        $transaksi->refresh();
        $this->assertSame($survivor->id, $transaksi->rkas_item_id);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'rkas_item',
            'aksi' => 'dedup_merge',
        ]);
    }

    public function test_does_not_merge_different_uraian(): void
    {
        $this->makeItem(4, 'Honor Pembina Pramuka', 225000);
        $this->makeItem(8, 'Honor Pembina Ekstra Tari', 300000);

        $this->artisan('rkas:dedup', [
            '--tahun' => (string) $this->tahun->id,
        ])->assertSuccessful();

        $this->assertCount(2, RkasItem::all());
    }

    public function test_does_not_merge_different_program(): void
    {
        $program2 = MasterProgram::factory()->create();

        $this->makeItem(4, 'Honor Pembina Pramuka', 225000);

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $program2->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 8,
            'uraian' => 'Honor Pembina Pramuka',
            'jumlah' => 225000,
        ]);

        $this->artisan('rkas:dedup', [
            '--tahun' => (string) $this->tahun->id,
        ])->assertSuccessful();

        $this->assertCount(2, RkasItem::all());
    }

    public function test_does_not_merge_different_sumber_dana(): void
    {
        $sumber2 = SumberDana::factory()->create();

        $this->makeItem(4, 'Honor Pembina Pramuka', 225000);

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber2->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 8,
            'uraian' => 'Honor Pembina Pramuka',
            'jumlah' => 225000,
        ]);

        $this->artisan('rkas:dedup', [
            '--tahun' => (string) $this->tahun->id,
        ])->assertSuccessful();

        $this->assertCount(2, RkasItem::all());
    }

    public function test_dry_run_does_not_change_data(): void
    {
        $this->makeItem(4, 'Honor Pembina Pramuka', 225000);
        $this->makeItem(8, 'Honor Pembina Pramuka', 500000);

        $this->artisan('rkas:dedup', [
            '--tahun' => (string) $this->tahun->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertCount(2, RkasItem::all());
        $this->assertSame(0, AuditLog::where('aksi', 'dedup_merge')->count());
    }
}
