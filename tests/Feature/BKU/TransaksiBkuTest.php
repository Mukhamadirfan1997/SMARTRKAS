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
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_create_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/transaksi-bku/create');

        $response->assertOk();
        $response->assertSee('Tambah Transaksi BKU');
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

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
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
        $this->assertDatabaseHas('transaksi_bku', ['no_bukti' => 'BPU001/20519260/01/2026']);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'transaksi_bku',
            'aksi' => 'override_anggaran',
        ]);
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

        $response->assertSessionHas('error');
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
}
