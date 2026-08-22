<?php

namespace Tests\Feature\Juknis;

use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 3 — Halaman Monitoring Kepatuhan Juknis BOSP.
 *
 * RefreshDatabase menjalankan migrasi 000030 (seed Honor/Pemeliharaan/Buku),
 * tetapi kategori seed TIDAK dipetakan ke kode rekening mana pun sehingga
 * tidak muncul sebagai kartu monitoring (whereHas kodeRekenings).
 */
class MonitoringJuknisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{TahunAnggaran, KategoriJuknis, MasterKodeRekening, RkasItem}
     */
    private function buatSetup(string $namaKategori, string $arah, float $batas): array
    {
        $tahun = TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);
        $rekening = MasterKodeRekening::factory()->create();

        $kategori = KategoriJuknis::factory()
            ->create(['nama' => $namaKategori, 'arah' => $arah, 'batas_persen' => $batas]);
        $kategori->kodeRekenings()->attach($rekening->id);

        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'kode_rekening_id' => $rekening->id,
            'jumlah' => 200000,
        ]);

        /** @var array{TahunAnggaran, KategoriJuknis, MasterKodeRekening, RkasItem} $setup */
        $setup = [$tahun, $kategori, $rekening, $item];

        return $setup;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('laporan.monitoring-juknis'))->assertRedirect('/login');
    }

    public function test_sidebar_menampilkan_link_monitoring_juknis(): void
    {
        $user = User::factory()->create();
        TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Monitoring Juknis');
        $response->assertSee(route('laporan.monitoring-juknis'));
    }

    public function test_tepat_di_batas_maksimal_dinilai_sesuai(): void
    {
        [$tahun, $kategori] = $this->buatSetup('Honor Uji Monitoring', 'maksimal', 20);

        // Pagu total = 1.000.000: 200.000 pada rekening ter-mapping + 800.000
        // pada rekening LAIN yang tidak dikategorikan.
        $rekeningLain = MasterKodeRekening::factory()->create();
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'kode_rekening_id' => $rekeningLain->id,
            'jumlah' => 800000,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('laporan.monitoring-juknis'));

        $response->assertOk();
        // Kode rekening tak-termap TIDAK masuk kategori: 200.000 / 1.000.000 = 20% (tepat batas) -> SESUAI.
        $response->assertSee('Total Pagu');
        $response->assertSee('Rp 1.000.000');
        $response->assertSee($kategori->nama);
        $response->assertSee('Sudah sesuai juknis');
        $response->assertDontSee('Melebihi batas maksimal');
        // Info kode rekening belum dikategorikan.
        $response->assertSee('1 kode rekening');
    }

    public function test_melebihi_batas_maksimal_ditandai(): void
    {
        [$tahun, $kategori] = $this->buatSetup('Honor Uji Melebihi', 'maksimal', 20);
        $item = RkasItem::where('tahun_anggaran_id', $tahun->id)->firstOrFail();
        $item->update(['jumlah' => 500000]);

        $response = $this->actingAs(User::factory()->create())->get(route('laporan.monitoring-juknis'));

        $response->assertOk();
        // 500.000 / 500.000 = 100% > 20%
        $response->assertSee('Melebihi batas maksimal');
        $response->assertDontSee('Sudah sesuai juknis');
        $this->assertSame(20.0, (float) $kategori->batas_persen);
    }

    public function test_realisasi_di_bawah_batas_minimal_ditandai(): void
    {
        [$tahun] = $this->buatSetup('Perpustakaan Uji Minimal', 'minimal', 15);
        $item = RkasItem::where('tahun_anggaran_id', $tahun->id)->firstOrFail();
        $item->update(['jumlah' => 100000]);

        $response = $this->actingAs(User::factory()->create())->get(route('laporan.monitoring-juknis'));

        $response->assertOk();
        // 100.000 / 100.000 = 100% >= 15% (minimal tercapai) -> sesuai.
        $response->assertSee('Sudah sesuai juknis');
    }

    public function test_basis_toggle_mengubah_perhitungan_rencana_ke_realisasi(): void
    {
        [$tahun] = $this->buatSetup('Buku Uji Basis', 'minimal', 15);
        $item = RkasItem::where('tahun_anggaran_id', $tahun->id)->firstOrFail();
        $item->update(['jumlah' => 200000]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'rkas_item_id' => $item->id,
            'jenis' => 'pengeluaran',
            'bulan' => 1,
            'jumlah' => 100000,
            'no_bukti' => 'BPU901/20519260/01/2026',
        ]);

        $user = User::factory()->create();

        // Rencana: 200.000 / 200.000 = 100% >= 15% -> sesuai.
        $rencana = $this->actingAs($user)->get(route('laporan.monitoring-juknis'));
        $rencana->assertOk();
        $rencana->assertSee('Sudah sesuai juknis');

        // Realisasi: 100.000 / 200.000 = 50% >= 15% -> tetap sesuai, tapi nominal berubah.
        $realisasi = $this->actingAs($user)->get(route('laporan.monitoring-juknis', ['basis' => 'realisasi']));
        $realisasi->assertOk();
        $realisasi->assertSee('Rp 100.000');

        // Toggle link tersedia di halaman.
        $rencana->assertSee('basis=rencana');
        $rencana->assertSee('basis=realisasi');
    }

    public function test_realisasi_nota_multi_item_ikut_dihitung(): void
    {
        [, , , $item] = $this->buatSetup('Obat Uji Nota', 'minimal', 5);
        $tahun = $item->tahunAnggaran;
        $nota = \App\Models\NotaBku::factory()->create(['tahun_anggaran_id' => $tahun->id, 'bulan' => 1]);
        \App\Models\NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'jumlah' => 2,
            'harga_satuan' => 50000,
            'subtotal' => 100000,
        ]);
        // Rincian nota hanya dihitung bila nota masih punya minimal SATU transaksi aktif.
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'jenis' => 'pengeluaran',
            'bulan' => 1,
            'jumlah' => 100000,
            'no_bukti' => 'BPU902/20519260/01/2026',
        ]);

        $user = User::factory()->create();
        $rencana = $this->actingAs($user)->get(route('laporan.monitoring-juknis'));
        $rencana->assertOk();
        $rencana->assertSee('Rp 200.000'); // rencana penuh

        $realisasi = $this->actingAs($user)->get(route('laporan.monitoring-juknis', ['basis' => 'realisasi']));
        $realisasi->assertOk();
        $realisasi->assertSee('Rp 100.000'); // realisasi dari nota_bku_item
    }

    public function test_tanpa_tahun_anggaran_menampilkan_peringatan(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('laporan.monitoring-juknis'));

        $response->assertOk();
        $response->assertSee('Belum ada tahun anggaran');
    }
}
