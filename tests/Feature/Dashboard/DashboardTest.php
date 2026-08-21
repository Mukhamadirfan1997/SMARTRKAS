<?php

namespace Tests\Feature\Dashboard;

use App\Models\AuditLog;
use App\Models\ImportLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\NotaBkuItem;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_layout_shows_realtime_clock(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('id="realtime-clock"', false)
            ->assertSee('id="realtime-date"', false);
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

    // =================== IMPORT STATUS ===================

    public function test_dashboard_menampilkan_badge_rencana_berubah_setelah_import(): void
    {
        $this->makeItem();

        ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'status' => 'success',
            'finished_at' => now()->subMinutes(10),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'tabel' => 'rkas_item',
            'aksi' => 'update',
            'data_baru' => ['jumlah' => 2000000],
            'created_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rencana berubah');
    }

    public function test_dashboard_tanpa_perubahan_tidak_menampilkan_badge(): void
    {
        $this->makeItem();

        ImportLog::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'status' => 'success',
            'finished_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Rencana berubah');
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

    /**
     * Item dengan 2 bulan: Jan rencana 103500 realisasi 0, Feb rencana 103500 realisasi 203500.
     * Per-bulan Feb: realisasi 203500 > rencana 103500 (197%).
     * Kumulatif: rencana 207000 - realisasi 203500 = sisa 3500 (98%).
     * Badge harus "Normal", BUKAN "Over Budget" — sama dgn guard BKU yang lolos.
     */
    public function test_dashboard_sisa_dan_badge_pakai_kumulatif_bukan_per_bulan(): void
    {
        $item = $this->makeItem(['tarif' => 10000, 'satuan' => 'kwh']);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 103500,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 103500,
        ]);

        // Bulan 1: tidak ada realisasi
        // Bulan 2: realisasi 203500 (lebih besar drpd rencana Feb 103500)
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'jumlah' => 203500,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard?bulan=2');
        $response->assertOk();

        // Realisasi per-bulan Feb tetap tampil: Rp 203.500
        $response->assertSee('Rp 203.500');

        // Sisa kumulatif: Rp 3.500 (bukan −Rp 100.000 per-bulan)
        $response->assertSee('Rp 3.500');
        $response->assertDontSee('Rp -100.000');

        // Badge kumulatif: Hampir Habis 98%, BUKAN Over Budget 197%
        $response->assertSee('Hampir Habis (98%)');
        $response->assertDontSee('Over Budget');
    }

    // =================== RECENT TRANSACTIONS (incl. NOTA) ===================

    public function test_dashboard_transaksi_terkini_menampilkan_transaksi_nota(): void
    {
        $kegiatanNota = MasterProgram::factory()->create(['nama' => 'Kegiatan Unik Nota Dashboard']);
        $kodeRekening = MasterKodeRekening::factory()->create();
        $itemA = $this->makeItem(['uraian' => 'Item Nota A']);
        $itemB = $this->makeItem(['uraian' => 'Item Nota B']);

        $nota = NotaBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'kegiatan_id' => $kegiatanNota->id,
            'kode_rekening_id' => $kodeRekening->id,
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'bulan' => 1,
            'tanggal' => '2026-01-05',
        ]);

        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $itemA->id,
            'subtotal' => 100000,
            'urutan' => 1,
        ]);
        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $itemB->id,
            'subtotal' => 200000,
            'urutan' => 2,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => null,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'no_bukti' => 'BPU901/20519260/01/2026',
            'jumlah' => 300000,
            'uraian' => 'Nota belanja NOTA-0001',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Nota belanja NOTA-0001')
            ->assertSee('Kegiatan Unik Nota Dashboard')
            ->assertSee('Item Nota A, Item Nota B')
            ->assertSee('Rp 300.000');
    }

    public function test_dashboard_alert_transaksi_bulan_ini_menyertakan_transaksi_nota(): void
    {
        $program = MasterProgram::factory()->create();
        $kodeRekening = MasterKodeRekening::factory()->create();
        $item = $this->makeItem();

        $nota = NotaBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'kegiatan_id' => $program->id,
            'kode_rekening_id' => $kodeRekening->id,
            'bulan' => (int) Carbon::now()->month,
        ]);

        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'subtotal' => 50000,
            'urutan' => 1,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => null,
            'bulan' => (int) Carbon::now()->month,
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Belum ada transaksi BKU bulan');
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
