<?php

namespace Tests\Feature\BKU;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\Outbox;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaBkuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahun;
    private SumberDana $sumber;
    private MasterProgram $program;
    private MasterKodeRekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
        $this->sumber = SumberDana::factory()->create();
        $this->program = MasterProgram::factory()->create();
        $this->rekening = MasterKodeRekening::factory()->create();

        PengaturanSekolah::create(['nama' => 'SD Negeri Uji Coba', 'npsn' => '20519260']);
    }

    private function makeItem(float $rencanaJanuari = 1000000, ?MasterProgram $program = null): RkasItem
    {
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $program->id ?? $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Belanja ATK Nota Test',
            'satuan' => 'paket',
            'tarif' => 50000,
            'jumlah' => $rencanaJanuari,
        ]);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => $rencanaJanuari,
        ]);

        return $item;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $overrides
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postNota(array $items, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post('/nota-bku', array_merge([
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
            'items' => $items,
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/nota-bku')->assertRedirect('/login');
        $this->get('/nota-bku/create')->assertRedirect('/login');
    }

    public function test_index_page_renders_nota_list(): void
    {
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/nota-bku');

        $response->assertOk();
        $response->assertSee('Riwayat Nota Belanja');
        $response->assertSee($nota->no_nota);
    }

    public function test_create_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/nota-bku/create');

        $response->assertOk();
        $response->assertSee('Tambah Nota Belanja');
        $response->assertSee('id="kegiatan_id"', false);
        $response->assertSee('id="kode_rekening_id"', false);
        $response->assertSee('id="item-list"', false);
        $response->assertSee('id="btn-tambah-item"', false);
    }

    public function test_items_endpoint_returns_items_for_kegiatan_with_sisa(): void
    {
        $item = $this->makeItem(2000000);
        $otherProgram = MasterProgram::factory()->create();
        $this->makeItem(500000, $otherProgram);

        $response = $this->actingAs($this->user)
            ->get('/nota-bku/items?kegiatan_id=' . $this->program->id . '&bulan=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.id', $item->id);
        $response->assertJsonPath('results.0.sisa', 2000000);
        $response->assertJsonPath('results.0.satuan', 'paket');
    }

    public function test_items_endpoint_excludes_items_from_other_tahun_anggaran(): void
    {
        $tahunLain = TahunAnggaran::factory()->create(['status' => false]);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahunLain->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 9,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/nota-bku/items?kegiatan_id=' . $this->program->id . '&bulan=1');

        $response->assertOk();
        $response->assertJsonCount(0, 'results');
    }

    public function test_items_endpoint_filters_by_kode_rekening(): void
    {
        $rekeningLain = MasterKodeRekening::factory()->create();
        $item = $this->makeItem(2000000);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningLain->id,
            'no_urut' => 2,
            'uraian' => 'Item rekening lain',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/nota-bku/items?kegiatan_id=' . $this->program->id
                . '&kode_rekening_id=' . $this->rekening->id . '&bulan=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.id', $item->id);

        // Tanpa filter kode rekening, kedua item muncul.
        $all = $this->actingAs($this->user)
            ->get('/nota-bku/items?kegiatan_id=' . $this->program->id . '&bulan=1');
        $all->assertOk();
        $all->assertJsonCount(2, 'results');
    }

    public function test_store_rejected_when_item_from_other_kode_rekening(): void
    {
        $rekeningLain = MasterKodeRekening::factory()->create();
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningLain->id,
            'no_urut' => 1,
            'uraian' => 'Item rekening lain',
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 1000000]);

        $response = $this->postNota([
            ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000'],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_rejected_when_kode_rekening_missing(): void
    {
        $item = $this->makeItem(1000000);

        $response = $this->postNota(
            [['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000']],
            ['kode_rekening_id' => '']
        );

        $response->assertSessionHasErrors('kode_rekening_id');
        $this->assertDatabaseCount('nota_bku', 0);
    }

    public function test_store_saves_kode_rekening_nota_relation_via_transaksi(): void
    {
        $item = $this->makeItem(5000000);

        $response = $this->postNota([
            ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '100000'],
        ]);

        $response->assertRedirect();

        $transaksi = TransaksiBku::firstOrFail();
        $this->assertSame($this->rekening->id, (string) $transaksi->notaBku->kode_rekening_id);
    }

    public function test_store_rejected_when_item_from_other_tahun_anggaran(): void
    {
        $tahunLain = TahunAnggaran::factory()->create(['status' => false]);
        $sumberLain = SumberDana::factory()->create();
        $rekeningLain = MasterKodeRekening::factory()->create();
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahunLain->id,
            'sumber_dana_id' => $sumberLain->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningLain->id,
            'no_urut' => 1,
            'uraian' => 'Item tahun anggaran lama',
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 1000000]);

        $response = $this->postNota([
            ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000'],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertStringContainsString('tahun anggaran aktif', (string) session('errors')->first('items'));
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_creates_nota_items_and_transaksi(): void
    {
        $item1 = $this->makeItem(1000000);
        $item2 = $this->makeItem(2000000);

        $item2->update(['no_urut' => 2]);

        $response = $this->postNota([
            [
                'rkas_item_id' => $item1->id,
                'qty' => '10',
                'harga' => '50000',
                'satuan' => 'paket',
            ],
            [
                'rkas_item_id' => $item2->id,
                'qty' => '2',
                'harga' => '250000',
                'satuan' => 'set',
            ],
        ]);

        $response->assertRedirect();

        $nota = NotaBku::where('no_nota', 'LIKE', 'NOTA-%/20519260/01/2026')->firstOrFail();
        $this->assertSame(1, (int) $nota->bulan);
        $this->assertSame($this->program->id, $nota->kegiatan_id);
        $this->assertSame($this->sumber->id, $nota->sumber_dana_id);
        $this->assertSame($this->user->id, $nota->created_by);

        $this->assertSame(2, $nota->items()->count());
        $this->assertSame(500000.0, (float) $nota->items()->orderBy('urutan')->first()->subtotal);

        $transaksis = TransaksiBku::where('nota_bku_id', $nota->id)->orderBy('no_bukti')->get();
        $this->assertCount(1, $transaksis);
        $transaksi = $transaksis->first();
        $this->assertSame('pengeluaran', $transaksi->jenis);
        $this->assertSame($nota->id, $transaksi->nota_bku_id);
        $this->assertNull($transaksi->rkas_item_id);
        $this->assertSame(1000000.0, (float) $transaksi->jumlah);
        $this->assertMatchesRegularExpression('/^BPU\d{3}\/20519260\/01\/2026$/', (string) $transaksi->no_bukti);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'nota_bku',
            'aksi' => 'create',
        ]);
        $this->assertDatabaseHas('outbox', [
            'model' => 'NotaBku',
            'model_id' => $nota->id,
            'aksi' => 'create',
        ]);
        $this->assertSame(2, Outbox::count()); // 1 nota + 1 transaksi
    }

    public function test_store_rejected_without_items(): void
    {
        $response = $this->postNota([]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_rejected_when_item_from_other_kegiatan(): void
    {
        $otherProgram = MasterProgram::factory()->create();
        $item = $this->makeItem(1000000, $otherProgram);

        $response = $this->postNota([
            ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000'],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_rejected_when_sumber_dana_mixed(): void
    {
        $sumberLain = SumberDana::factory()->create();
        $item1 = $this->makeItem(1000000);
        $item2 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumberLain->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 2,
            'uraian' => 'Belanja dari sumber lain',
            'satuan' => 'paket',
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item2->id, 'bulan' => 1, 'rencana' => 1000000]);

        $response = $this->postNota([
            ['rkas_item_id' => $item1->id, 'qty' => '1', 'harga' => '50000'],
            ['rkas_item_id' => $item2->id, 'qty' => '1', 'harga' => '50000'],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_rejects_entire_nota_when_any_item_over_budget(): void
    {
        $cukup = $this->makeItem(1000000);
        $kurang = $this->makeItem(50000);
        $kurang->update(['no_urut' => 2]);

        $response = $this->postNota([
            ['rkas_item_id' => $cukup->id, 'qty' => '1', 'harga' => '50000'],
            ['rkas_item_id' => $kurang->id, 'qty' => '10', 'harga' => '10000'], // 100.000 > sisa 50.000
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertStringContainsString('SELURUH nota dibatalkan', (string) session('errors')->first('items'));

        // SEMUA baris dari ketiga tabel harus tidak ada — bukan hanya "request ditolak".
        $this->assertDatabaseMissing('nota_bku', ['no_nota' => 'NOTA-0001/20519260/01/2026']);
        $this->assertDatabaseMissing('nota_bku_item', ['rkas_item_id' => $cukup->id]);
        $this->assertDatabaseMissing('nota_bku_item', ['rkas_item_id' => $kurang->id]);
        $this->assertDatabaseMissing('transaksi_bku', ['rkas_item_id' => $cukup->id, 'jenis' => 'pengeluaran']);
        $this->assertDatabaseMissing('transaksi_bku', ['rkas_item_id' => $kurang->id, 'jenis' => 'pengeluaran']);

        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('nota_bku_item', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_normalizes_indonesian_number_format(): void
    {
        $item = $this->makeItem(5000000);

        $response = $this->postNota([
            ['rkas_item_id' => $item->id, 'qty' => '2,5', 'harga' => '1.500.000', 'satuan' => 'paket'],
        ]);

        $response->assertRedirect();

        $nota = NotaBku::firstOrFail();
        $notaItem = $nota->items()->firstOrFail();
        $this->assertSame(2.5, (float) $notaItem->jumlah);
        $this->assertSame(1500000.0, (float) $notaItem->harga_satuan);
        $this->assertSame(3750000.0, (float) $notaItem->subtotal);

        $transaksi = TransaksiBku::where('nota_bku_id', $nota->id)->firstOrFail();
        $this->assertSame(3750000.0, (float) $transaksi->jumlah);
    }

    public function test_store_siplah_requires_no_invoice(): void
    {
        $item = $this->makeItem(1000000);

        $response = $this->postNota(
            [['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000']],
            ['metode_pengadaan' => 'siplah', 'no_invoice_siplah' => '']
        );

        $response->assertSessionHasErrors('no_invoice_siplah');
        $this->assertDatabaseCount('nota_bku', 0);
    }

    public function test_store_siplah_saves_invoice_on_nota_and_transaksi(): void
    {
        $item = $this->makeItem(1000000);

        $response = $this->postNota(
            [['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '50000']],
            ['metode_pengadaan' => 'siplah', 'no_invoice_siplah' => 'INV/2026/000123']
        );

        $response->assertRedirect();

        $nota = NotaBku::firstOrFail();
        $this->assertSame('INV/2026/000123', $nota->no_invoice_siplah);
        $this->assertSame('INV/2026/000123', TransaksiBku::where('nota_bku_id', $nota->id)->firstOrFail()->no_invoice_siplah);
    }

    public function test_show_page_renders_detail(): void
    {
        $item = $this->makeItem(1000000);
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);
        $nota->items()->create([
            'rkas_item_id' => $item->id,
            'urutan' => 1,
            'jumlah' => 10,
            'satuan' => 'paket',
            'harga_satuan' => 50000,
            'subtotal' => 500000,
        ]);
        TransaksiBku::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/nota-bku/' . $nota->id);

        $response->assertOk();
        $response->assertSee('Detail Nota');
        $response->assertSee($nota->no_nota);
        $response->assertSee('500.000');
        $response->assertSee('BPU001/20519260/01/2026');
        $response->assertSee('Cetak PDF');
    }

    public function test_cetak_kwitansi_flattened_transaksi_menampilkan_program_subprogram_rekening_tanpa_no_nota(): void
    {
        $this->program->update(['program' => 'Program Sarana', 'sub_program' => 'Sub Program Belanja']);
        $this->rekening->update(['kode' => '5.1.2.01.0001', 'nama' => 'Belanja Alat Tulis Kantor']);
        $item = $this->makeItem(1000000);
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
        ]);
        $transaksi = TransaksiBku::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'created_by' => $this->user->id,
        ]);

        $html = view('transaksi-bku.kwitansi-content', [
            'transaksiBku' => $transaksi,
            'profil' => PengaturanSekolah::get(),
        ])->render();

        $this->assertStringContainsString('BPU001/20519260/01/2026', $html);
        $this->assertStringContainsString('Belanja ATK Nota Test', $html);
        $this->assertStringContainsString('Program', $html);
        $this->assertStringContainsString('Program Sarana', $html);
        $this->assertStringContainsString('Sub Program', $html);
        $this->assertStringContainsString('Sub Program Belanja', $html);
        $this->assertStringContainsString('Kode Rekening', $html);
        $this->assertStringContainsString('5.1.2.01.0001', $html);
        $this->assertStringNotContainsString('No. Nota', $html);
        $this->assertStringNotContainsString('NOTA-0001/20519260/01/2026', $html);
    }

    public function test_cetak_returns_pdf(): void    {
        $item = $this->makeItem(1000000);
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);
        $nota->items()->create([
            'rkas_item_id' => $item->id,
            'urutan' => 1,
            'jumlah' => 10,
            'satuan' => 'paket',
            'harga_satuan' => 50000,
            'subtotal' => 500000,
        ]);

        $response = $this->actingAs($this->user)->get('/nota-bku/' . $nota->id . '/cetak');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_cetak_menampilkan_rincian_belanja_dan_no_bpu_tanpa_no_nota(): void
    {
        $item = $this->makeItem(1000000);
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);
        $nota->items()->create([
            'rkas_item_id' => $item->id,
            'urutan' => 1,
            'jumlah' => 10,
            'satuan' => 'paket',
            'harga_satuan' => 50000,
            'subtotal' => 500000,
        ]);
        TransaksiBku::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => null,
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'created_by' => $this->user->id,
        ]);

        $html = view('nota-bku.cetak', ['notaBku' => $nota, 'total' => 500000])->render();

        $this->assertStringContainsString('Rincian Belanja', $html);
        $this->assertStringContainsString('No. BPU', $html);
        $this->assertStringContainsString('BPU001/20519260/01/2026', $html);
        $this->assertStringNotContainsString('No. Nota', $html);
        $this->assertStringNotContainsString('NOTA-0001/20519260/01/2026', $html);
    }

    public function test_destroy_soft_deletes_nota_and_transaksi(): void
    {
        $item = $this->makeItem(1000000);
        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0001/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);
        $transaksi = TransaksiBku::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete('/nota-bku/' . $nota->id);

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('nota_bku', ['id' => $nota->id]);
        $this->assertSoftDeleted('transaksi_bku', ['id' => $transaksi->id]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'nota_bku',
            'aksi' => 'delete',
        ]);
        $this->assertDatabaseHas('outbox', [
            'model' => 'TransaksiBku',
            'model_id' => $transaksi->id,
            'aksi' => 'delete',
        ]);
    }

    public function test_duplicate_no_bukti_from_nota_is_unique(): void
    {
        $item1 = $this->makeItem(1000000);
        $item2 = $this->makeItem(1000000);
        $item2->update(['no_urut' => 2]);

        $this->postNota([
            ['rkas_item_id' => $item1->id, 'qty' => '1', 'harga' => '50000'],
            ['rkas_item_id' => $item2->id, 'qty' => '1', 'harga' => '50000'],
        ]);

        $nota = NotaBku::firstOrFail();
        $noBuktis = TransaksiBku::where('nota_bku_id', $nota->id)->pluck('no_bukti');

        $this->assertCount(1, $noBuktis);
        $this->assertSame(1, $noBuktis->unique()->count());
        $this->assertMatchesRegularExpression('/^BPU\d{3}\/20519260\/01\/2026$/', (string) $noBuktis->first());
    }

    public function test_realisasi_nota_tidak_dobel_dengan_transaksi_legacy_nota(): void
    {
        $item = $this->makeItem(1000000);
        $item->update(['no_urut' => 2]);

        $this->postNota([
            [
                'rkas_item_id' => $item->id,
                'qty' => '10',
                'harga' => '50000',
                'satuan' => 'paket',
            ],
        ]);

        $nota = NotaBku::firstOrFail();

        $transaksiBaru = TransaksiBku::where('nota_bku_id', $nota->id)->firstOrFail();
        $this->assertNull($transaksiBaru->rkas_item_id);

        TransaksiBku::create([
            'no_bukti' => 'BPU999/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 500000,
            'bulan' => 1,
            'tanggal' => '2026-01-15',
            'rkas_item_id' => $item->id,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'uraian' => 'Flatten legacy nota',
            'created_by' => $this->user->id,
        ]);

        $this->assertSame(500000.0, $item->refresh()->realisasiKumulatifSd(1));
        $this->assertSame(500000.0, $item->refresh()->sisaKumulatifSd(1));
    }

    public function test_realisasi_nota_tidak_dihitung_saat_semua_transaksi_dihapus(): void
    {
        $item = $this->makeItem(1000000);
        $item->update(['no_urut' => 2]);

        $this->postNota([
            [
                'rkas_item_id' => $item->id,
                'qty' => '10',
                'harga' => '50000',
                'satuan' => 'paket',
            ],
        ]);

        $nota = NotaBku::firstOrFail();
        $transaksi = TransaksiBku::where('nota_bku_id', $nota->id)->firstOrFail();

        $this->assertSame(500000.0, $item->refresh()->realisasiKumulatifSd(1));

        $transaksi->delete();

        $this->assertNull($nota->refresh()->deleted_at);
        $this->assertSoftDeleted('transaksi_bku', ['id' => $transaksi->id]);
        $this->assertSame(0.0, $item->refresh()->realisasiKumulatifSd(1));
        $this->assertSame(1000000.0, $item->refresh()->sisaKumulatifSd(1));
        $this->assertSame(0.0, (float) \App\Support\RealisasiQuery::base()->sum('rb.jumlah'));
    }

    public function test_destroy_transaksi_nota_cascades_to_nota_dan_anggaran_kembali(): void
    {
        $item = $this->makeItem(1000000);
        $item->update(['no_urut' => 2]);

        $this->postNota([
            [
                'rkas_item_id' => $item->id,
                'qty' => '10',
                'harga' => '50000',
                'satuan' => 'paket',
            ],
        ]);

        $nota = NotaBku::firstOrFail();
        $transaksi = TransaksiBku::where('nota_bku_id', $nota->id)->firstOrFail();

        $this->assertSame(500000.0, $item->refresh()->realisasiKumulatifSd(1));

        $response = $this->actingAs($this->user)->delete('/transaksi-bku/' . $transaksi->id);

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('nota_bku', ['id' => $nota->id]);
        $this->assertSoftDeleted('transaksi_bku', ['id' => $transaksi->id]);
        $this->assertSame(0.0, $item->refresh()->realisasiKumulatifSd(1));
        $this->assertSame(1000000.0, $item->refresh()->sisaKumulatifSd(1));
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'nota_bku',
            'aksi' => 'delete',
        ]);
    }

    public function test_destroy_all_cascades_transaksi_nota(): void
    {
        $item1 = $this->makeItem(1000000);
        $item2 = $this->makeItem(2000000);
        $item1->update(['no_urut' => 1]);
        $item2->update(['no_urut' => 2]);

        $this->postNota([
            ['rkas_item_id' => $item1->id, 'qty' => '10', 'harga' => '50000', 'satuan' => 'paket'],
            ['rkas_item_id' => $item2->id, 'qty' => '2', 'harga' => '250000', 'satuan' => 'set'],
        ]);

        $nota = NotaBku::firstOrFail();

        $response = $this->actingAs($this->user)->post('/transaksi-bku/hapus-semua', [
            'tahun' => $this->tahun->tahun,
            'bulan' => 1,
            'alasan' => 'Reset uji cascade',
        ]);

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('nota_bku', ['id' => $nota->id]);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'delete_bulk',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'nota_bku',
            'aksi' => 'delete',
        ]);
    }
}
