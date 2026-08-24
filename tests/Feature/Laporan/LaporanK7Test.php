<?php

namespace Tests\Feature\Laporan;

use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanK7Test extends TestCase
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

        PengaturanSekolah::create([
            'nama' => 'SDN Toyaning I',
            'npsn' => '20512345',
            'alamat' => 'Jl. Raya Toyaning No. 10',
            'kabupaten' => 'Pasuruan',
            'nama_kepsek' => 'SRI LESTARI, S.Pd',
            'nip_kepsek' => '197103122000122003',
            'nama_bendahara' => 'SAIFUR RIZAL, S.Pd',
            'nip_bendahara' => '198405292019031003',
        ]);
    }

    public function test_guest_redirected_to_login_for_k7b_and_k7c(): void
    {
        $this->get(route('laporan.k7b'))->assertRedirect(route('login'));
        $this->get(route('laporan.k7c'))->assertRedirect(route('login'));
    }

    public function test_laporan_index_shows_k7b_and_k7c_links(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.index'));

        $response->assertOk();
        $response->assertSee('Register Kas (K-7b)');
        $response->assertSee('Pemeriksaan Kas (K-7c)');
    }

    public function test_k7b_web_view_renders_correctly_with_bku_balance(): void
    {
        $rkasItem = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        // Penerimaan Januari Rp 10.000.000
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 10000000,
            'tanggal' => '2026-01-05',
        ]);

        // Pengeluaran Januari Rp 3.000.000
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => $rkasItem->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 3000000,
            'tanggal' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
        ]));

        $response->assertOk();
        $response->assertSee('REGISTER PENUTUPAN KAS');
        $response->assertSee('SDN Toyaning I');
        $response->assertSee('SAIFUR RIZAL, S.Pd');
        $response->assertSee('SRI LESTARI, S.Pd');
        $response->assertSee('10.000.000');
        $response->assertSee('3.000.000');
        $response->assertSee('7.000.000');
    }

    public function test_k7b_calculates_cash_breakdown_and_zero_difference(): void
    {
        $rkasItem = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        // Saldo BKU = 5.000.000
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 5000000,
            'tanggal' => '2026-01-05',
        ]);

        // Input 20 lembar 100k (2.000.000) + saldo bank 3.000.000 = total 5.000.000 -> Perbedaan 0 (NIHIL)
        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'kertas_100000' => 20,
            'saldo_bank' => 3000000,
        ]));

        $response->assertOk();
        $response->assertSee('NIHIL');
    }

    public function test_k7b_pdf_stream_returns_success(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'cetak' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_k7c_web_view_renders_correctly(): void
    {
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 5000000,
            'tanggal' => '2026-01-05',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'sk_bupati_kepsek' => '821.2/TEST/2026',
            'sk_bupati_bendahara' => '420/TEST/2026',
        ]));

        $response->assertOk();
        $response->assertSee('BERITA ACARA PEMERIKSAAN KAS');
        $response->assertSee('821.2/TEST/2026');
        $response->assertSee('420/TEST/2026');
        $response->assertSee('SRI LESTARI, S.Pd');
        $response->assertSee('SAIFUR RIZAL, S.Pd');
    }

    public function test_k7c_pdf_stream_returns_success(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'cetak' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_k7b_default_tanggal_penutupan_adalah_akhir_bulan(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 1000000,
            'tanggal' => '2026-01-05',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
        ]));

        // 31 Januari 2026 = Sabtu
        $response->assertOk();
        $response->assertSee('31 Januari 2026');
    }

    public function test_k7b_mengabaikan_tanggal_stale_saat_bulan_filter_tidak_cocok(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 1000000,
            'tanggal' => '2026-01-05',
        ]);

        // User sebelumnya membuka bulan 2 (tanggal 28 Feb), lalu pindah ke bulan 1
        // tanpa mengubah input tanggal -> server harus pakai akhir Januari.
        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
            'tanggal_penutupan' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertSee('31 Januari 2026');
        $response->assertDontSee('28 Februari 2026');
    }

    public function test_k7b_tanggal_valid_custom_dipakai_sebagaimana_adanya(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 1000000,
            'tanggal' => '2026-01-05',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
            'tanggal_penutupan' => '2026-01-20',
        ]));

        $response->assertOk();
        $response->assertSee('20 Januari 2026');
        $response->assertDontSee('31 Januari 2026');
    }

    public function test_k7c_narasi_memuat_nama_hari_indonesia(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 1000000,
            'tanggal' => '2026-01-05',
        ]);

        // 20 Januari 2026 = Selasa
        $response = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => 2026,
            'tanggal_penutupan' => '2026-01-20',
            'sk_bupati_kepsek' => '821.2/TEST/2026',
        ]));

        $response->assertOk();
        $response->assertSee('Selasa');
        $response->assertSee('20 Januari 2026');
    }
}
