<?php

namespace Tests\Feature\Dashboard;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahunAnggaran;
    private SumberDana $sumberDana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahunAnggaran = TahunAnggaran::factory()->create(['status' => true]);
        $this->sumberDana = SumberDana::factory()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function makeItem(array $attributes = []): RkasItem
    {
        return RkasItem::factory()->create(array_merge([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'jumlah' => 1000000,
            'tarif' => 100000,
            'satuan' => 'buah',
        ], $attributes));
    }

    // =================== ACCESS CONTROL ===================

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    // =================== STAT CARDS ===================

    public function test_dashboard_shows_stat_card_values(): void
    {
        $item = $this->makeItem();

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 400000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rp 1.000.000')
            ->assertSee('Rp 400.000')
            ->assertSee('Rp 600.000')
            ->assertSee('40%');
    }

    // =================== DYNAMIC ITEM TABLE ===================

    public function test_dashboard_shows_dynamic_item_table(): void
    {
        $program = MasterProgram::factory()->create(['kode' => 'P.0001', 'nama' => 'Sarana Prasarana']);
        $kodeRekening = MasterKodeRekening::factory()->create(['kode' => '5.1.2.01.0001']);
        $item = $this->makeItem([
            'program_id' => $program->id,
            'kode_rekening_id' => $kodeRekening->id,
            'uraian' => 'Pembelian ATK',
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 1000000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 400000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pembelian ATK')
            ->assertSee('P.0001')
            ->assertSee('Sarana Prasarana')
            ->assertSee('5.1.2.01.0001')
            ->assertSee('10 buah')
            ->assertSee('4 buah')
            ->assertSee('Normal (40%)');
    }

    public function test_dashboard_bulan_filter_affects_item_rencana(): void
    {
        $item = $this->makeItem(['jumlah' => 3000000]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 1000000,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 2000000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 400000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard?bulan=1')
            ->assertOk()
            ->assertSee('Rp 1.000.000')
            ->assertDontSee('Rp 3.000.000');
    }

    public function test_dashboard_bulan_filter_hides_items_without_plan_for_that_month(): void
    {
        $withPlan = $this->makeItem(['uraian' => 'ATK Terencana Januari']);
        $withoutPlan = $this->makeItem(['uraian' => 'ATK Tanpa Rencana Januari']);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $withPlan->id,
            'bulan' => 1,
            'rencana' => 500000,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $withoutPlan->id,
            'bulan' => 2,
            'rencana' => 500000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard?bulan=1')
            ->assertOk()
            ->assertSee('ATK Terencana Januari')
            ->assertDontSee('ATK Tanpa Rencana Januari');
    }

    // =================== STATUS BADGE ===================

    public function test_dashboard_shows_over_budget_status(): void
    {
        $item = $this->makeItem();

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 100000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 150000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard?bulan=1')
            ->assertOk()
            ->assertSee('Over Budget (150%)');
    }

    // =================== PAGINATION ===================

    public function test_dashboard_paginates_items(): void
    {
        $program = MasterProgram::factory()->create();
        $kodeRekening = MasterKodeRekening::factory()->create();

        RkasItem::factory()->count(55)->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $kodeRekening->id,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('page=2');
    }
}
