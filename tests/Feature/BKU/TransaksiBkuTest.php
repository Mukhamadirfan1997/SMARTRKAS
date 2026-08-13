<?php

namespace Tests\Feature\BKU;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\Outbox;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransaksiBkuTest extends TestCase
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
    }

    private function makeItem(float $jumlah = 1000000): RkasItem
    {
        return RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Belanja Alat Tulis Kantor',
            'jumlah' => $jumlah,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeTransaksi(array $overrides = []): TransaksiBku
    {
        return TransaksiBku::factory()->create(array_merge([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'rkas_item_id' => null,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/transaksi-bku')->assertRedirect('/login');
        $this->get('/transaksi-bku/create')->assertRedirect('/login');
    }

    public function test_index_shows_transaksi_for_selected_bulan(): void
    {
        $transaksi = $this->makeTransaksi([
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BBU001/NPSN/01/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 500000,
        ]);

        $response = $this->actingAs($this->user)->get('/transaksi-bku?bulan=1');

        $response->assertOk();
        $response->assertSee('Buku Kas Umum (BKU)');
        $response->assertSee($transaksi->no_bukti);
        $response->assertSee('500.000');
    }

    public function test_index_filters_by_sumber_dana(): void
    {
        $sumber2 = SumberDana::factory()->create();
        $this->makeTransaksi([
            'bulan' => 1,
            'no_bukti' => 'BBU001/NPSN/01/2026',
            'sumber_dana_id' => $sumber2->id,
        ]);

        $response = $this->actingAs($this->user)->get('/transaksi-bku?bulan=1&sumber_dana_id=' . $this->sumber->id);

        $response->assertOk();
        $response->assertDontSee('BBU001/NPSN/01/2026');
    }

    public function test_index_saldo_berjalan_tidak_dobel_saat_semua_bulan(): void
    {
        $this->makeTransaksi([
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'no_bukti' => 'BBU001/20519260/01/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 10768500,
        ]);
        $this->makeTransaksi([
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BBU002/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 225000,
        ]);

        $response = $this->actingAs($this->user)->get('/transaksi-bku?bulan=');

        $response->assertOk();
        $response->assertSee('10.543.500');
        $response->assertDontSee('21.312.000');
    }

    public function test_index_saldo_berjalan_lanjut_di_halaman_kedua_saat_semua_bulan(): void
    {
        $dates = [];
        foreach (range(1, 31) as $day) {
            $dates[] = '2026-01-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }
        foreach (range(1, 24) as $day) {
            $dates[] = '2026-02-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        foreach ($dates as $i => $date) {
            $this->makeTransaksi([
                'tanggal' => $date,
                'bulan' => (int) Carbon::parse($date)->month,
                'no_bukti' => 'BBU' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT) . '/20519260/2026',
                'jenis' => 'penerimaan',
                'jumlah' => 100,
            ]);
        }

        $response = $this->actingAs($this->user)->get('/transaksi-bku?bulan=&page=2');

        $response->assertOk();
        $response->assertSee('5.100');
        $response->assertDontSee('5.600');
    }

    public function test_index_saldo_awal_dibawa_dari_bulan_sebelumnya(): void
    {
        $this->makeTransaksi([
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BBU001/20519260/01/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 500000,
        ]);
        $this->makeTransaksi([
            'tanggal' => '2026-02-10',
            'bulan' => 2,
            'no_bukti' => 'BBU002/20519260/02/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response = $this->actingAs($this->user)->get('/transaksi-bku?bulan=2');

        $response->assertOk();
        $response->assertSee('400.000');
        $response->assertDontSee('900.000');
    }

    public function test_create_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/transaksi-bku/create');

        $response->assertOk();
        $response->assertSee('Tambah Transaksi BKU');
        $response->assertSee('id="form-bku"', false);
        $response->assertSee('name="jumlah"', false);
        $response->assertSee('id="rkas_item_id"', false);
        $response->assertSee('id="row_override"', false);
        $response->assertSee('id="no_invoice_siplah"', false);
        $response->assertSee('id="row_no_invoice_siplah"', false);
        $response->assertSee('Format angka Indonesia', false);
    }

    public function test_store_penerimaan_transaksi(): void
    {
        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BBU001/20519260/01/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 1000000,
            'toko_penerima' => 'BOS Reguler',
            'uraian' => 'Penerimaan BOS Tahap 1',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BBU001/20519260/01/2026',
            'bulan' => 1,
            'tahun_anggaran_id' => $this->tahun->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('outbox', [
            'model' => 'TransaksiBku',
            'model_id' => TransaksiBku::where('no_bukti', 'BBU001/20519260/01/2026')->value('id'),
            'aksi' => 'create',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'create',
        ]);
    }

    public function test_store_pengeluaran_within_budget(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 200000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'volume' => 10,
            'satuan' => 'paket',
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'sumber_dana_id' => $this->sumber->id,
        ]);
    }

    public function test_store_pengeluaran_rejected_when_exceeds_budget(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response->assertSessionHasErrors('jumlah');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_single_checked_item_creates_transaksi_not_nota(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
            'items' => [
                ['rkas_item_id' => $item->id, 'qty' => '10', 'harga' => '50000', 'satuan' => 'paket'],
            ],
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseHas('transaksi_bku', [
            'rkas_item_id' => $item->id,
            'jenis' => 'pengeluaran',
            'jumlah' => 500000.0,
            'volume' => 10,
            'satuan' => 'paket',
        ]);
    }

    public function test_store_two_checked_items_create_nota_and_flattened_transaksi(): void
    {
        $item1 = $this->makeItem();
        $item2 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 2,
            'uraian' => 'Belanja ATK Kedua',
            'jumlah' => 5000000,
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item1->id, 'bulan' => 1, 'rencana' => 5000000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item2->id, 'bulan' => 1, 'rencana' => 5000000]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
            'items' => [
                ['rkas_item_id' => $item1->id, 'qty' => '10', 'harga' => '50000'],
                ['rkas_item_id' => $item2->id, 'qty' => '2', 'harga' => '250000'],
            ],
        ]);

        $response->assertRedirect();
        $nota = \App\Models\NotaBku::firstOrFail();

        $this->assertSame(2, $nota->items()->count());
        $this->assertDatabaseCount('transaksi_bku', 1);
        $this->assertTrue(TransaksiBku::where('nota_bku_id', $nota->id)->count() === 1);
        $this->assertSame($this->rekening->id, $nota->kode_rekening_id);
    }

    public function test_store_single_item_over_budget_without_override_rejected(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'metode_pengadaan' => 'non_siplah',
            'items' => [
                ['rkas_item_id' => $item->id, 'qty' => '10', 'harga' => '100000'],
            ],
        ]);

        $response->assertSessionHasErrors('jumlah');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_two_items_over_budget_rejects_entire_nota(): void
    {
        $cukup = $this->makeItem();
        $kurang = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 2,
            'uraian' => 'Belanja ATK Kurang',
            'jumlah' => 50000,
        ]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $cukup->id, 'bulan' => 1, 'rencana' => 1000000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $kurang->id, 'bulan' => 1, 'rencana' => 50000]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'metode_pengadaan' => 'non_siplah',
            'items' => [
                ['rkas_item_id' => $cukup->id, 'qty' => '1', 'harga' => '50000'],
                ['rkas_item_id' => $kurang->id, 'qty' => '10', 'harga' => '10000'],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseCount('nota_bku_item', 0);
        $this->assertDatabaseCount('transaksi_bku', 0);
    }

    public function test_store_single_checked_item_with_override_saves_override_note(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'metode_pengadaan' => 'non_siplah',
            'override_anggaran' => '1',
            'override_note' => 'Harga barang naik karena penyesuaian pasar',
            'items' => [
                ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '100000'],
            ],
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('nota_bku', 0);
        $this->assertDatabaseHas('transaksi_bku', [
            'rkas_item_id' => $item->id,
            'jenis' => 'pengeluaran',
            'jumlah' => 100000.0,
            'override_note' => 'Harga barang naik karena penyesuaian pasar',
        ]);
    }

    public function test_store_single_checked_item_override_blocks_kwitansi_until_resolved(): void
    {
        Storage::fake('public');

        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'metode_pengadaan' => 'non_siplah',
            'override_anggaran' => '1',
            'override_note' => 'Harga barang naik karena penyesuaian pasar',
            'items' => [
                ['rkas_item_id' => $item->id, 'qty' => '1', 'harga' => '100000'],
            ],
        ]);

        $transaksi = TransaksiBku::where('rkas_item_id', $item->id)->where('jenis', 'pengeluaran')->firstOrFail();
        $transaksi->load('rkasItem.bulanRencana', 'rkasItem.transaksiBkus');
        $this->assertTrue($transaksi->masihOverBudget());

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/cetak-kwitansi');
        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('kwitansi', 0);

        RkasItemBulan::where('rkas_item_id', $item->id)
            ->where('bulan', 1)
            ->update(['rencana' => 110000]);

        $transaksi = TransaksiBku::with('rkasItem.bulanRencana', 'rkasItem.transaksiBkus')->findOrFail($transaksi->id);
        $this->assertFalse($transaksi->masihOverBudget());

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/cetak-kwitansi');
        $response->assertOk();
        $this->assertDatabaseCount('kwitansi', 1);
    }

    public function test_store_normalizes_indonesian_number_format(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => '1.500.000',
            'volume' => '2,5',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jumlah' => 1500000.0,
            'volume' => 2.5,
        ]);
    }

    public function test_store_keeps_calculator_decimal_format(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => '2500000.00',
            'volume' => '25',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jumlah' => 2500000.0,
        ]);
    }

    public function test_store_pengeluaran_accepted_with_override(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
            'override_note' => 'Ada SILPA bulan sebelumnya',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertTrue(str_contains((string) session('success'), 'Perubahan Anggaran'));
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'override_note' => 'Ada SILPA bulan sebelumnya',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'override_anggaran',
        ]);
    }

    public function test_store_siplah_rejected_without_no_invoice_siplah(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'siplah',
        ]);

        $response->assertSessionHasErrors('no_invoice_siplah');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_siplah_rejected_with_empty_no_invoice_siplah(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'siplah',
            'no_invoice_siplah' => '',
        ]);

        $response->assertSessionHasErrors('no_invoice_siplah');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_non_siplah_allowed_without_no_invoice_siplah(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'non_siplah',
            'no_invoice_siplah' => '',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'no_invoice_siplah' => null,
        ]);
    }

    public function test_store_siplah_saves_no_invoice_siplah(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'siplah',
            'no_invoice_siplah' => 'INV/2026/000123',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseHas('transaksi_bku', [
            'no_bukti' => 'BPU001/20519260/01/2026',
            'no_invoice_siplah' => 'INV/2026/000123',
        ]);
    }

    public function test_update_siplah_requires_no_invoice_siplah(): void
    {
        $transaksi = $this->makeTransaksi([
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'bulan' => 1,
            'tanggal' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->put('/transaksi-bku/' . $transaksi->id, [
            'rkas_item_id' => null,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'siplah',
            'no_invoice_siplah' => '',
        ]);

        $response->assertSessionHasErrors('no_invoice_siplah');
        $this->assertDatabaseHas('transaksi_bku', ['id' => $transaksi->id, 'no_invoice_siplah' => null]);
    }

    public function test_update_siplah_saves_no_invoice_siplah(): void
    {
        $transaksi = $this->makeTransaksi([
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'bulan' => 1,
            'tanggal' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->put('/transaksi-bku/' . $transaksi->id, [
            'rkas_item_id' => null,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'metode_pengadaan' => 'siplah',
            'no_invoice_siplah' => 'INV/2026/000456',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $this->assertDatabaseHas('transaksi_bku', [
            'id' => $transaksi->id,
            'no_invoice_siplah' => 'INV/2026/000456',
        ]);
    }

    public function test_store_override_rejected_without_note(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
        ]);

        $response->assertSessionHasErrors('override_note');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_override_rejected_with_whitespace_note(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
            'override_note' => '   ',
        ]);

        $response->assertSessionHasErrors('override_note');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_override_rejected_with_short_note(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
            'override_note' => 'singkat',
        ]);

        $response->assertSessionHasErrors('override_note');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
    }

    public function test_store_jumlah_must_be_positive(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        foreach ([0, -1000] as $jumlah) {
            $response = $this->actingAs($this->user)->post('/transaksi-bku', [
                'rkas_item_id' => $item->id,
                'tanggal' => '2026-01-15',
                'no_bukti' => 'BPU001/20519260/01/2026',
                'jenis' => 'pengeluaran',
                'jumlah' => $jumlah,
            ]);

            $response->assertSessionHasErrors('jumlah');
            $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
        }
    }

    public function test_update_jumlah_must_be_positive(): void
    {
        $transaksi = $this->makeTransaksi([
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'bulan' => 1,
            'tanggal' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->put('/transaksi-bku/' . $transaksi->id, [
            'rkas_item_id' => null,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 0,
        ]);

        $response->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('transaksi_bku', ['id' => $transaksi->id, 'jumlah' => 100000]);
    }

    public function test_kwitansi_blocked_until_override_resolved(): void
    {
        Storage::fake('public');

        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
            'override_note' => 'Harga barang naik drastis',
        ]);

        $transaksi = TransaksiBku::where('no_bukti', 'BPU001/20519260/01/2026')->firstOrFail();
        $transaksi->load('rkasItem.bulanRencana', 'rkasItem.transaksiBkus');
        $this->assertTrue($transaksi->masihOverBudget());

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/cetak-kwitansi');
        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('kwitansi', 0);

        RkasItemBulan::where('rkas_item_id', $item->id)
            ->where('bulan', 1)
            ->update(['rencana' => 110000]);

        $transaksi = TransaksiBku::with('rkasItem.bulanRencana', 'rkasItem.transaksiBkus')->findOrFail($transaksi->id);
        $this->assertFalse($transaksi->masihOverBudget());

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/cetak-kwitansi');
        $response->assertOk();
        $this->assertDatabaseCount('kwitansi', 1);
    }

    public function test_kwitansi_batch_blocked_when_override_unresolved(): void
    {
        Storage::fake('public');

        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'override_anggaran' => '1',
            'override_note' => 'Harga barang naik drastis',
        ]);
        $override = TransaksiBku::where('no_bukti', 'BPU001/20519260/01/2026')->firstOrFail();
        $normal = $this->makeTransaksi([
            'no_bukti' => 'BPU002/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
            'bulan' => 1,
            'tanggal' => '2026-01-20',
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku/cetak-kwitansi-batch', [
            'ids' => [$override->id, $normal->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('kwitansi', 0);

        $response = $this->actingAs($this->user)->post('/transaksi-bku/cetak-kwitansi-batch', [
            'ids' => [$normal->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('kwitansi', 1);
    }

    public function test_edit_page_renders(): void
    {
        $transaksi = $this->makeTransaksi(['no_bukti' => 'BPU001/20519260/01/2026']);

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/edit');

        $response->assertOk();
        $response->assertSee('Edit Transaksi BKU');
        $response->assertSee($transaksi->no_bukti);
    }

    public function test_update_transaksi(): void
    {
        $transaksi = $this->makeTransaksi([
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 500000,
        ]);

        $response = $this->actingAs($this->user)->put('/transaksi-bku/' . $transaksi->id, [
            'tanggal' => '2026-02-10',
            'no_bukti' => 'BPU001/20519260/02/2026',
            'jenis' => 'penerimaan',
            'jumlah' => 750000,
            'toko_penerima' => 'BOS Reguler',
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transaksi_bku', [
            'id' => $transaksi->id,
            'no_bukti' => 'BPU001/20519260/02/2026',
            'bulan' => 2,
            'jumlah' => 750000.0,
        ]);

        $this->assertDatabaseHas('outbox', [
            'model' => 'TransaksiBku',
            'model_id' => $transaksi->id,
            'aksi' => 'update',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'update',
        ]);
    }

    public function test_update_pengeluaran_rejected_when_exceeds_budget(): void
    {
        $item = $this->makeItem();
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);

        $transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 30000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put('/transaksi-bku/' . $transaksi->id, [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response->assertSessionHasErrors('jumlah');
        $transaksi->refresh();
        $this->assertSame(30000.0, (float) $transaksi->jumlah);
    }

    public function test_destroy_soft_deletes_and_records_outbox(): void
    {
        $transaksi = $this->makeTransaksi(['no_bukti' => 'BPU001/20519260/01/2026']);

        $response = $this->actingAs($this->user)->delete('/transaksi-bku/' . $transaksi->id, [
            'delete_note' => 'Salah input',
        ]);

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('transaksi_bku', ['id' => $transaksi->id]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'delete',
        ]);

        $this->assertDatabaseHas('outbox', [
            'model' => 'TransaksiBku',
            'model_id' => $transaksi->id,
            'aksi' => 'delete',
        ]);
    }

    public function test_destroy_all_deletes_matching_transactions(): void
    {
        $this->makeTransaksi(['bulan' => 1, 'no_bukti' => 'BBU001/NPSN/01/2026']);
        $this->makeTransaksi(['bulan' => 1, 'no_bukti' => 'BBU002/NPSN/01/2026']);
        $this->makeTransaksi(['bulan' => 2, 'no_bukti' => 'BBU003/NPSN/02/2026']);

        $response = $this->actingAs($this->user)->post('/transaksi-bku/hapus-semua', [
            'tahun' => $this->tahun->tahun,
            'bulan' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('transaksi_bku', ['no_bukti' => 'BBU001/NPSN/01/2026']);
        $this->assertSoftDeleted('transaksi_bku', ['no_bukti' => 'BBU002/NPSN/01/2026']);
        $this->assertDatabaseHas('transaksi_bku', ['no_bukti' => 'BBU003/NPSN/02/2026']);

        $this->assertDatabaseHas('outbox', [
            'model' => 'TransaksiBku',
            'aksi' => 'delete',
        ]);
        $this->assertDatabaseCount('outbox', 2);
    }

    public function test_destroy_all_returns_error_when_no_match(): void
    {
        $response = $this->actingAs($this->user)->post('/transaksi-bku/hapus-semua', [
            'tahun' => 1999,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('outbox', 0);
    }

    public function test_store_generates_no_bukti_when_missing(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => '',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));

        $saved = TransaksiBku::where('rkas_item_id', $item->id)->where('jumlah', 100000)->first();
        $this->assertNotNull($saved);
        $this->assertMatchesRegularExpression('/^BPU00\d\/\d+\/01\/2026$/', (string) $saved->no_bukti);
    }

    public function test_store_regenerates_duplicate_no_bukti_instead_of_rejecting(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 5000000,
        ]);

        $this->makeTransaksi([
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jumlah' => 100000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));
        $saved = TransaksiBku::where('rkas_item_id', $item->id)
            ->orderBy('created_at')
            ->get(['no_bukti']);
        $this->assertCount(2, $saved);
        $this->assertSame(
            2,
            $saved->pluck('no_bukti')->unique()->count(),
            'Kedua transaksi tersimpan dengan no_bukti unik'
        );
    }

    public function test_store_reuses_soft_deleted_no_bukti_when_autogenerating(): void
    {
        $deleted = $this->makeTransaksi([
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'jenis' => 'penerimaan',
            'no_bukti' => 'BBU001/20519260/01/2026',
            'jumlah' => 100000,
        ]);
        $deleted->delete();
        $this->assertNotNull($deleted->deleted_at);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'tanggal' => '2026-01-15',
            'no_bukti' => '',
            'jenis' => 'penerimaan',
            'jumlah' => 500000,
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));

        $new = TransaksiBku::where('jenis', 'penerimaan')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
        $this->assertNotNull($new);
        $this->assertSame('BBU001/00000000/01/2026', $new->no_bukti);
        $this->assertSame(2, TransaksiBku::withTrashed()->where('no_bukti', 'like', 'BBU%/01/2026')->count());
    }

    public function test_store_autogenerated_no_bukti_continues_from_highest_in_month_instead_of_filling_gap(): void
    {
        // Bulan 2 memakai BPU011-017 (BPU001-010 bulan 2 tidak pernah ada —
        // gap akibat counter JS setahun pada versi lama). Nomor berikutnya harus
        // BPU018, BUKAN mengisi nomor terendah yang bebas (mis. BPU001).
        foreach (range(11, 17) as $i) {
            $this->makeTransaksi([
                'tanggal' => '2026-02-10',
                'bulan' => 2,
                'jenis' => 'pengeluaran',
                'no_bukti' => 'BPU' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '/00000000/02/2026',
                'jumlah' => 100000,
            ]);
        }

        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku', [
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-02-15',
            'no_bukti' => '',
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
        ]);

        $response->assertRedirect(route('transaksi-bku.index'));

        $saved = TransaksiBku::where('no_bukti', 'BPU018/00000000/02/2026')->first();
        $this->assertNotNull($saved, 'No bukti harus meneruskan dari nomor tertinggi bulan tsb (BPU018), bukan mengisi gap.');
    }

    public function test_create_page_shows_monthly_cumulative_sisa_matching_guard(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 2000000]);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 2, 'rencana' => 3000000]);
        $this->makeTransaksi([
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jumlah' => 500000,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['_old_input' => ['rkas_item_id' => $item->id, 'tanggal' => '2026-02-05']])
            ->get('/transaksi-bku/create');

        $response->assertOk();
        $response->assertSee('"bulan":2', false);
        $response->assertSee('"sisa":4500000', false);
        $response->assertSee('Sisa s.d. bulan', false);
    }

    public function test_edit_page_shows_monthly_cumulative_sisa(): void
    {
        $item = $this->makeItem(10000000);
        RkasItemBulan::factory()->create(['rkas_item_id' => $item->id, 'bulan' => 1, 'rencana' => 2000000]);
        $this->makeTransaksi([
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-10',
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jumlah' => 500000,
        ]);
        $transaksi = $this->makeTransaksi([
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-12',
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'no_bukti' => 'BPU002/20519260/01/2026',
            'jumlah' => 100000,
        ]);

        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $transaksi->id . '/edit');

        $response->assertOk();
        $response->assertSee('"bulan":1', false);
        $response->assertSee('"sisa":1400000', false);
        $response->assertSee('Sisa s.d. bulan', false);
    }
}
