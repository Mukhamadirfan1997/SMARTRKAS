<?php

namespace Tests\Feature\Laporan;

use App\Models\KasPenutupan;
use App\Models\PengaturanSekolah;
use App\Models\Pencairan;
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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
            'rkas_item_id' => null,
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

    /**
     * @return array<string, int|string>
     */
    private function denominasiKosong(int $lembar100Rb = 0): array
    {
        return [
            'kertas_lembar_100000' => $lembar100Rb,
            'kertas_lembar_50000' => 0,
            'kertas_lembar_20000' => 0,
            'kertas_lembar_10000' => 0,
            'kertas_lembar_5000' => 0,
            'kertas_lembar_2000' => 0,
            'kertas_lembar_1000' => 0,
            'logam_keping_500' => 0,
            'logam_keping_200' => 0,
            'logam_keping_100' => 0,
            'logam_keping_50' => 0,
        ];
    }

    public function test_tarik_tunai_mutasi_tidak_dihitung_penerimaan_k7b(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        // Tarik tunai dari bank = mutasi internal (kategori_arus = mutasi).
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'kategori_arus' => 'mutasi',
            'rkas_item_id' => null,
            'jumlah' => 10000000,
            'tanggal' => '2026-01-05',
            'uraian' => 'Tarik Tunai dari Bank',
        ]);

        // Penerimaan riil.
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'kategori_arus' => null,
            'rkas_item_id' => null,
            'jumlah' => 2000000,
            'tanggal' => '2026-01-10',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('2.000.000');
        $response->assertDontSee('10.000.000');
    }

    public function test_k7b_pencairan_sp2d_dihitung_penerimaan(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        // Pencairan SP2D Januari Rp 10.000.000 (modul Data Pencairan).
        Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => null,
            'bulan' => 1,
            'nominal' => 10000000,
            'tanggal' => '2026-01-10',
            'created_by' => $this->user->id,
        ]);

        // Tarik tunai dari bank = mutasi internal, TIDAK dihitung penerimaan.
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'kategori_arus' => 'mutasi',
            'rkas_item_id' => null,
            'jumlah' => 4000000,
            'tanggal' => '2026-01-05',
            'uraian' => 'Tarik Tunai dari Bank',
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('10.000.000');
        $response->assertSee('Termasuk pencairan SP2D');
    }

    public function test_k7b_saldo_bank_default_diestimasi_dari_pencairan_minus_tarik_tunai(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => null,
            'bulan' => 1,
            'nominal' => 10000000,
            'tanggal' => '2026-01-10',
            'created_by' => $this->user->id,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'kategori_arus' => 'mutasi',
            'rkas_item_id' => null,
            'jumlah' => 4000000,
            'tanggal' => '2026-01-05',
            'uraian' => 'Tarik Tunai dari Bank',
        ]);

        // Tanpa input denominasi/saldo_bank dan tanpa opname tersimpan:
        // saldo bank default = pencairan (10jt) - tarik tunai s.d. bulan (4jt).
        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('value="6.000.000"', false);
    }

    public function test_simpan_k7b_menyimpan_penutupan_dan_mengisi_ulang_form(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => null,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 5000000,
            'tanggal' => '2026-01-05',
        ]);

        $response = $this->actingAs($this->user)->post(route('laporan.k7b.simpan'), array_merge([
            'bulan' => 1,
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal_penutupan' => '2026-01-31',
            'saldo_bank' => '3.000.000',
            'catatan' => 'NIHIL',
        ], $this->denominasiKosong(20)));

        $response->assertSessionHas('success');

        $penutupan = KasPenutupan::first();
        $this->assertNotNull($penutupan);
        $this->assertSame(1, $penutupan->bulan);
        $this->assertSame($this->sumberDana->id, $penutupan->sumber_dana_id);
        $this->assertSame(20, $penutupan->lembar_100000);
        $this->assertSame(3000000.0, (float) $penutupan->saldo_bank);
        $this->assertSame('2026-01-31', $penutupan->tanggal_penutupan?->toDateString());
        $this->assertSame('NIHIL', $penutupan->catatan);

        // Form terisi ulang dari record tersimpan (lookup pakai filter yang sama
        // dengan saat simpan, termasuk sumber dana).
        $page = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => 2026,
            'sumber_dana_id' => $this->sumberDana->id,
        ]));

        $page->assertOk();
        $page->assertSee('3.000.000');
        $page->assertSee('2.000.000,00');
        $page->assertSee('value="NIHIL"', false);
    }

    public function test_simpan_k7b_update_tanpa_duplikat_baris(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        $payloadBase = [
            'bulan' => 1,
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal_penutupan' => '2026-01-31',
            'catatan' => null,
        ];

        $this->actingAs($this->user)->post(route('laporan.k7b.simpan'), array_merge($payloadBase, [
            'saldo_bank' => '3.000.000',
        ], $this->denominasiKosong(20)));

        $this->actingAs($this->user)->post(route('laporan.k7b.simpan'), array_merge($payloadBase, [
            'saldo_bank' => '1.500.000',
        ], $this->denominasiKosong(25)));

        $this->assertSame(1, KasPenutupan::count());

        $penutupan = KasPenutupan::first();
        $this->assertSame(25, $penutupan->lembar_100000);
        $this->assertSame(1500000.0, (float) $penutupan->saldo_bank);
    }

    public function test_register_pdf_renders_lanskap_multi_bulan(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'rkas_item_id' => null,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 5000000,
            'tanggal' => '2026-01-05',
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'rkas_item_id' => null,
            'jumlah' => 1000000,
            'tanggal' => '2026-02-10',
        ]);

        KasPenutupan::create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'tanggal_penutupan' => '2026-01-31',
            'lembar_100000' => 30,
            'saldo_bank' => 2000000,
        ]);

        $response = $this->actingAs($this->user)->get(route('laporan.k7b.register', [
            'tahun' => 2026,
            'dari' => 1,
            'sampai' => 3,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Register_Penutupan_Kas_K7b-SDN_Toyaning_I-2026.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_register_pdf_menyertakan_pencairan_tanpa_error(): void
    {
        $this->tahunAnggaran->update(['tahun' => 2026]);

        Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => null,
            'bulan' => 1,
            'nominal' => 5000000,
            'tanggal' => '2026-01-10',
            'created_by' => $this->user->id,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'rkas_item_id' => null,
            'jumlah' => 1000000,
            'tanggal' => '2026-02-10',
        ]);

        // Smoke: register multi-bulan dengan pencairan harus tetap render PDF.
        // (Isi angka tidak bisa di-assert karena stream PDF terkompresi.)
        $response = $this->actingAs($this->user)->get(route('laporan.k7b.register', [
            'tahun' => 2026,
            'dari' => 1,
            'sampai' => 2,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Register_Penutupan_Kas_K7b-SDN_Toyaning_I-2026.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_k7b_memiliki_tombol_k7c_dengan_id(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.k7b', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
        ]));

        $response->assertOk();
        // JS memperbarui href tombol ini dengan data live form (updateK7cUrl).
        $response->assertSee('id="btn-k7c"', false);
    }

    public function test_k7c_menampilkan_data_live_dari_k7b_via_query_string(): void
    {
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'jumlah' => 8000000,
            'rkas_item_id' => null,
            'tanggal' => '2026-01-05',
        ]);

        // Data live K-7b: kas fisik 5 lembar 100rb + 10 keping 500 = 505.000,
        // saldo bank 2.500.000 -> dikirim via query string tanpa disimpan.
        $response = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'kertas_lembar_100000' => 5,
            'logam_keping_500' => 10,
            'saldo_bank' => 2500000,
        ]));

        $response->assertOk();
        $response->assertSee('value="505.000"', false);
        $response->assertSee('2.500.000');
    }

    public function test_k7c_menghormati_override_kas_fisik(): void
    {
        // Tanpa transaksi -> saldo BKU (A) = 0; kas fisik dipaksa 123456.
        $web = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'kas_fisik' => '123456',
        ]));

        $web->assertOk();
        $web->assertSee('value="123.456"', false);

        $pdf = $this->actingAs($this->user)->get(route('laporan.k7c', [
            'bulan' => 1,
            'tahun' => $this->tahunAnggaran->tahun,
            'kas_fisik' => '123456',
            'cetak' => 'pdf',
        ]));

        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
    }
}
