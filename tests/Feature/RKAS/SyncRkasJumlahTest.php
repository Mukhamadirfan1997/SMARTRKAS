<?php

namespace Tests\Feature\RKAS;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncRkasJumlahTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_updates_jumlah_from_sum_of_rencana(): void
    {
        $tahun = TahunAnggaran::factory()->create();
        $sumber = SumberDana::factory()->create();
        $program = MasterProgram::factory()->create();
        $kodeRekening = MasterKodeRekening::factory()->create();

        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'sumber_dana_id' => $sumber->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $kodeRekening->id,
            'jumlah' => 100000,
        ]);

        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 250000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 2, 'rencana' => 350000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 3, 'rencana' => 400000]);

        $this->artisan('rkas:sync-jumlah')->assertSuccessful();

        $this->assertDatabaseHas('rkas_item', [
            'id' => $item->id,
            'jumlah' => 1000000.0,
        ]);
    }

    public function test_sync_includes_soft_deleted_items(): void
    {
        $tahun = TahunAnggaran::factory()->create();
        $sumber = SumberDana::factory()->create();
        $program = MasterProgram::factory()->create();
        $kodeRekening = MasterKodeRekening::factory()->create();

        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'sumber_dana_id' => $sumber->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $kodeRekening->id,
            'jumlah' => 50000,
        ]);
        $item->delete();

        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 75000]);

        $this->artisan('rkas:sync-jumlah')->assertSuccessful();

        $this->assertDatabaseHas('rkas_item', [
            'id' => $item->id,
            'jumlah' => 75000.0,
        ]);
    }
}
