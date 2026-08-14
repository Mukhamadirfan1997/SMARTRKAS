<?php

namespace Tests\Feature\Laporan;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\NotaBkuItem;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use App\Exports\BkuExport;
use App\Exports\RekapSiplahExport;
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

    // =================== LAPORAN BKU: TRANSAKSI NOTA ===================

    /**
     * Buat nota + transaksi flatten (rkas_item_id null) utk menguji fallback
     * kegiatan/kode rekening/jenis belanja dari notaBku di laporan BKU.
     *
     * @return array{program: MasterProgram, rekening: MasterKodeRekening, nota: NotaBku, transaksi: TransaksiBku}
     */
    private function createNotaTransaksi(): array
    {
        $program = MasterProgram::factory()->create(['kode' => '06.05.08.', 'nama' => 'Kegiatan Uji Nota']);
        $rekening = MasterKodeRekening::factory()->create(['kode' => '5.1.02.01.01.0025', 'nama' => 'Bahan Cetak']);
        $jenis = \App\Models\JenisBelanja::factory()->create(['nama' => 'Belanja Barang Persediaan']);
        $rekening->jenis_belanja_id = $jenis->id;
        $rekening->save();

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-20',
            'bulan' => 1,
            'kegiatan_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'created_by' => $this->user->id,
        ]);

        $transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 550000,
            'no_bukti' => 'BPU999/20519260/01/2026',
            'uraian' => 'Nota belanja NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-20',
        ]);

        return [
            'program' => $program,
            'rekening' => $rekening,
            'nota' => $nota,
            'transaksi' => $transaksi,
        ];
    }

    /**
     * @return array{rekening1: MasterKodeRekening, rekening2: MasterKodeRekening, item1: RkasItem, item2: RkasItem, nota: NotaBku, transaksi: TransaksiBku}
     */
    private function createNotaMultiItem(): array
    {
        $jenis1 = JenisBelanja::factory()->create(['nama' => 'Belanja ATK Nota']);
        $rekening1 = MasterKodeRekening::factory()->create([
            'kode' => '5.1.02.01.01.0101',
            'nama' => 'Bahan ATK',
            'jenis_belanja_id' => $jenis1->id,
        ]);
        $item1 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => MasterProgram::factory(),
            'kode_rekening_id' => $rekening1->id,
            'no_urut' => 10,
            'uraian' => 'Pembelian Buku Tulis',
            'jumlah' => 500000,
        ]);

        $jenis2 = JenisBelanja::factory()->create(['nama' => 'Belanja Obat Nota']);
        $rekening2 = MasterKodeRekening::factory()->create([
            'kode' => '5.1.02.01.01.0102',
            'nama' => 'Bahan Obat',
            'jenis_belanja_id' => $jenis2->id,
        ]);
        $item2 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'program_id' => MasterProgram::factory(),
            'kode_rekening_id' => $rekening2->id,
            'no_urut' => 11,
            'uraian' => 'Pembelian Obat',
            'jumlah' => 300000,
        ]);

        $program = MasterProgram::factory()->create(['kode' => '06.05.09.', 'nama' => 'Kegiatan Nota Multi']);

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0099/20519260/01/2026',
            'tanggal' => '2026-01-21',
            'bulan' => 1,
            'kegiatan_id' => $program->id,
            'kode_rekening_id' => $rekening1->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'metode_pengadaan' => 'siplah',
            'created_by' => $this->user->id,
        ]);

        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item1->id,
            'urutan' => 1,
            'jumlah' => 10,
            'satuan' => 'buah',
            'harga_satuan' => 10000,
            'subtotal' => 100000,
        ]);
        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item2->id,
            'urutan' => 2,
            'jumlah' => 20,
            'satuan' => 'botol',
            'harga_satuan' => 10000,
            'subtotal' => 200000,
        ]);

        $transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahunAnggaran->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 300000,
            'no_bukti' => 'BPU998/20519260/01/2026',
            'uraian' => 'Nota belanja NOTA-0099/20519260/01/2026',
            'tanggal' => '2026-01-21',
            'metode_pengadaan' => 'siplah',
        ]);

        return [
            'rekening1' => $rekening1,
            'rekening2' => $rekening2,
            'item1' => $item1,
            'item2' => $item2,
            'nota' => $nota,
            'transaksi' => $transaksi,
        ];
    }

    public function test_laporan_rekap_siplah_menampilkan_realisasi_nota_multi_item(): void
    {
        $this->createNotaMultiItem();

        $response = $this->actingAs($this->user)->get('/laporan/rekap-siplah/preview?periode=all');
        $response->assertOk();
        $response->assertSee('Belanja ATK Nota');
        $response->assertSee('Belanja Obat Nota');
        $response->assertSee('Rp 100.000');
        $response->assertSee('Rp 200.000');
        $response->assertSee('Rp 800.000');
        $response->assertDontSee('Tidak Terkategori');
    }

    public function test_rekap_siplah_export_mencerminkan_realisasi_nota_multi_item(): void
    {
        $this->createNotaMultiItem();

        $export = new RekapSiplahExport(range(1, 12), 'Seluruh Tahun', $this->tahunAnggaran->id);
        $rows = $export->collection();

        $rowAtk = $rows->first(fn (TransaksiBku $r) => $r->getAttribute('jenis_belanja') === 'Belanja ATK Nota');
        $this->assertNotNull($rowAtk);
        $this->assertSame(100000.0, (float) $rowAtk->getAttribute('total'));
        $this->assertSame(100000.0, (float) $rowAtk->getAttribute('siplah'));

        $rowObat = $rows->first(fn (TransaksiBku $r) => $r->getAttribute('jenis_belanja') === 'Belanja Obat Nota');
        $this->assertNotNull($rowObat);
        $this->assertSame(200000.0, (float) $rowObat->getAttribute('total'));

        $this->assertTrue($rows->doesntContain(fn (TransaksiBku $r) => $r->getAttribute('jenis_belanja') === 'Tidak Terkategori'));
    }

    public function test_laporan_bku_menampilkan_kegiatan_rekening_jenis_dari_nota(): void
    {
        $this->createNotaTransaksi();

        $response = $this->actingAs($this->user)->get('/laporan/bku/preview?bulan=1');
        $response->assertOk();
        $response->assertSee('BPU999/20519260/01/2026');
        $response->assertSee('06.05.08.');
        $response->assertSee('5.1.02.01.01.0025');
        $response->assertSee('Belanja Barang Persediaan');
    }

    public function test_laporan_bku_pdf_menampilkan_kegiatan_rekening_jenis_dari_nota(): void
    {
        $this->createNotaTransaksi();

        // View PDF `laporan.bku` dirender oleh `bku()` saat cetak=pdf; cek render HTML view.
        $response = $this->actingAs($this->user)->get('/laporan/bku?bulan=1&cetak=pdf');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bku_export_mengisi_kegiatan_rekening_dari_nota(): void
    {
        $this->createNotaTransaksi();

        $export = new BkuExport(1, '', $this->tahunAnggaran->id);
        $row = $export->collection()->first(
            fn (TransaksiBku $t) => $t->no_bukti === 'BPU999/20519260/01/2026'
        );
        $this->assertNotNull($row);

        $mapped = $export->map($row);
        $this->assertSame('06.05.08.', $mapped[2]);
        $this->assertSame('5.1.02.01.01.0025', $mapped[3]);
        $this->assertSame('550000', (string) $mapped[6]);
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

    public function test_laporan_rekap_kuartal_menampilkan_realisasi(): void
    {
        // Item di bulan 1 masuk kuartal 1 (bulan 1-3). Realisasi 500.000 harus
        // tampil sebagai angka, bukan '&mdash;' (regresi drop kolom m0/m1/m2).
        $response = $this->actingAs($this->user)->get('/laporan/rekap-kuartal?bulan=2');
        $response->assertStatus(200);
        $response->assertSee('Rp 500.000');
        $response->assertDontSee('&mdash;');
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
