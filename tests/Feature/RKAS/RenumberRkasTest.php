<?php

namespace Tests\Feature\RKAS;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenumberRkasTest extends TestCase
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

    private function makeItem(int $noUrut, string $uraian): RkasItem
    {
        return RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => $noUrut,
            'uraian' => $uraian,
        ]);
    }

    public function test_renumbers_to_sequential_unique_no_urut(): void
    {
        $itemC = $this->makeItem(4, 'Honor Pembina Tari');
        $itemA = $this->makeItem(4, 'Honor Pembina Pramuka');
        $itemB = $this->makeItem(8, 'Honor Pembina Qiroah');

        $this->artisan('rkas:renumber', [
            '--tahun' => (string) $this->tahun->id,
        ])->assertSuccessful();

        $noUruts = RkasItem::orderBy('id')->pluck('no_urut')->map(fn ($n): int => (int) $n)->values();
        $this->assertSame([1, 2, 3], $noUruts->all());
        $this->assertCount($noUruts->count(), $noUruts->unique());
    }

    public function test_renumbers_separately_per_tahun(): void
    {
        $tahun2 = TahunAnggaran::factory()->create();

        $this->makeItem(4, 'Honor Pembina Pramuka');
        $this->makeItem(8, 'Honor Pembina Tari');

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun2->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 9,
            'uraian' => 'Honor Pembina Qiroah',
        ]);

        $this->artisan('rkas:renumber')->assertSuccessful();

        $tahun1Nos = RkasItem::where('tahun_anggaran_id', $this->tahun->id)->orderBy('id')->pluck('no_urut')->all();
        $tahun2Nos = RkasItem::where('tahun_anggaran_id', $tahun2->id)->orderBy('id')->pluck('no_urut')->all();

        $this->assertSame([1, 2], array_map('intval', $tahun1Nos));
        $this->assertSame([1], array_map('intval', $tahun2Nos));
    }

    public function test_dry_run_does_not_change_data(): void
    {
        $this->makeItem(4, 'Honor Pembina Pramuka');
        $this->makeItem(4, 'Honor Pembina Tari');

        $this->artisan('rkas:renumber', [
            '--tahun' => (string) $this->tahun->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $noUruts = RkasItem::orderBy('id')->pluck('no_urut')->map(fn ($n): int => (int) $n)->all();
        $this->assertSame([4, 4], $noUruts);
    }
}
