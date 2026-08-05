<?php

namespace Tests\Feature\Laporan;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahunAnggaran;
    private SumberDana $sumberDana;
    private RkasItem $rkasItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahunAnggaran = TahunAnggaran::factory()->create(['status' => true]);
        $this->sumberDana = SumberDana::factory()->create();
        $this->rkasItem = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $this->rkasItem->id,
            'bulan' => 1,
            'rencana' => 1000000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $this->rkasItem->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'uraian' => 'Belanja ATK',
            'metode_pengadaan' => 'siplah',
            'tanggal' => '2026-01-15',
        ]);

        PengaturanSekolah::create([
            'nama' => 'SD Negeri Contoh',
            'npsn' => '20519260',
            'nama_kepsek' => 'Drs. Contoh',
            'nip_kepsek' => '196001011990031001',
            'nama_bendahara' => 'Siti Aminah',
            'nip_bendahara' => '197502152005012002',
        ]);
    }

    // =================== ACCESS CONTROL ===================

    public function test_guest_cannot_access_laporan_index(): void
    {
        $this->get('/laporan')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_laporan_bku(): void
    {
        $this->get('/laporan/bku')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_exports(): void
    {
        $this->get('/exports/1/download')->assertRedirect('/login');
        $this->get('/exports/1/status')->assertRedirect('/login');
    }

    public function test_user_can_access_laporan_index(): void
    {
        $this->actingAs($this->user)->get('/laporan')->assertStatus(200);
    }

    // =================== LAPORAN BKU ===================

    public function test_user_can_view_laporan_bku(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/bku?bulan=1');
        $response->assertStatus(200);
        $response->assertSee('Belanja ATK');
    }

    public function test_user_can_view_laporan_bku_preview(): void
    {
        $this->actingAs($this->user)->get('/laporan/bku/preview?bulan=1')->assertStatus(200);
    }

    public function test_user_can_export_laporan_bku_excel(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/bku/export-excel?bulan=1');
        $response->assertStatus(302);
        $response->assertSessionHas('info');
    }

    public function test_laporan_bku_returns_pdf_when_cetak_param(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/bku?bulan=1&cetak=pdf');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // =================== LAPORAN REKAP REKENING ===================

    public function test_user_can_view_laporan_rekap_rekening(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-rekening?bulan=1')->assertStatus(200);
    }

    public function test_user_can_view_laporan_rekap_rekening_preview(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-rekening/preview?bulan=1')->assertStatus(200);
    }

    public function test_user_can_export_laporan_rekap_rekening_excel(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-rekening/export-excel?bulan=1');
        $response->assertStatus(302);
        $response->assertSessionHas('info');
    }

    public function test_laporan_rekap_rekening_returns_pdf_when_cetak_param(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-rekening?bulan=1&cetak=pdf');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // =================== LAPORAN REKAP KUARTAL ===================

    public function test_user_can_view_laporan_rekap_kuartal(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-kuartal?bulan=2')->assertStatus(200);
    }

    public function test_user_can_view_laporan_rekap_kuartal_preview(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-kuartal/preview?bulan=2')->assertStatus(200);
    }

    public function test_user_can_export_laporan_rekap_kuartal_excel(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-kuartal/export-excel?bulan=2');
        $response->assertStatus(302);
        $response->assertSessionHas('info');
    }

    public function test_laporan_rekap_kuartal_returns_pdf_when_cetak_param(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-kuartal?bulan=2&cetak=pdf');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // =================== LAPORAN REKAP SIPLAH ===================

    public function test_user_can_view_laporan_rekap_siplah(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-siplah?periode=all')->assertStatus(200);
    }

    public function test_user_can_view_laporan_rekap_siplah_preview(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-siplah/preview?periode=all')->assertStatus(200);
    }

    public function test_user_can_export_laporan_rekap_siplah_excel(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-siplah/export-excel?periode=all');
        $response->assertStatus(302);
        $response->assertSessionHas('info');
    }

    public function test_laporan_rekap_siplah_returns_pdf_when_cetak_param(): void
    {
        $response = $this->actingAs($this->user)->get('/laporan/rekap-siplah?periode=all&cetak=pdf');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // =================== FILTERS ===================

    public function test_laporan_bku_with_sumber_dana_filter(): void
    {
        $this->actingAs($this->user)->get('/laporan/bku?bulan=1&sumber_dana_id=' . $this->sumberDana->id)
            ->assertStatus(200);
    }

    public function test_laporan_bku_with_different_tahun(): void
    {
        TahunAnggaran::factory()->create(['tahun' => 2024]);
        $this->actingAs($this->user)->get('/laporan/bku?bulan=1&tahun=2024')->assertStatus(200);
    }

    public function test_laporan_rekap_siplah_with_bulan_param(): void
    {
        $this->actingAs($this->user)->get('/laporan/rekap-siplah?bulan=1')->assertStatus(200);
    }
}
