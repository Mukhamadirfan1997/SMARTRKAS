<?php

namespace Tests\Feature\RKAS;

use App\Models\AuditLog;
use App\Models\JenisBelanja;
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
use Tests\TestCase;

class RkasControllerTest extends TestCase
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

    private function makeItem(int $noUrut, string $uraian, float $jumlah): RkasItem
    {
        return RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => $noUrut,
            'uraian' => $uraian,
            'jumlah' => $jumlah,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/rkas')->assertRedirect('/login');
    }

    public function test_index_shows_items_for_active_tahun(): void
    {
        $item = $this->makeItem(1, 'Belanja Alat Tulis Kantor', 1000000);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Data RKAS');
        $response->assertSee($item->uraian);
    }

    public function test_index_filters_by_sumber_dana(): void
    {
        $sumber2 = SumberDana::factory()->create();

        $itemA = $this->makeItem(1, 'Belanja BOS', 1000000);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber2->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 2,
            'uraian' => 'Belanja Lainnya',
            'jumlah' => 500000,
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?sumber_dana_id=' . $this->sumber->id);

        $response->assertOk();
        $response->assertSee($itemA->uraian);
        $response->assertDontSee('Belanja Lainnya');
    }

    public function test_update_modifies_item(): void
    {
        $item = $this->makeItem(1, 'Belanja ATK', 100000);

        $response = $this->actingAs($this->user)->put('/rkas/' . $item->id, [
            'no_urut' => 3,
            'uraian' => 'Belanja ATK Diperbarui',
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'sumber_dana_id' => $this->sumber->id,
            'volume' => 10,
            'satuan' => 'buah',
            'tarif' => 5000,
            'jumlah' => 50000,
        ]);

        $response->assertRedirect(route('rkas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rkas_item', [
            'id' => $item->id,
            'no_urut' => 3,
            'uraian' => 'Belanja ATK Diperbarui',
            'jumlah' => 50000.0,
        ]);
    }

    public function test_update_rejects_invalid_data(): void
    {
        $item = $this->makeItem(1, 'Belanja ATK', 100000);

        $response = $this->actingAs($this->user)->put('/rkas/' . $item->id, [
            'no_urut' => 'bukan-angka',
            'uraian' => '',
            'jumlah' => 'abc',
        ]);

        $response->assertSessionHasErrors(['no_urut', 'uraian', 'jumlah']);
    }

    public function test_edit_page_renders(): void
    {
        $item = $this->makeItem(1, 'Belanja ATK', 100000);

        $response = $this->actingAs($this->user)->get('/rkas/' . $item->id . '/edit');

        $response->assertOk();
        $response->assertSee('Edit Item RKAS');
        $response->assertSee('id="form-rkas-edit"', false);
        $response->assertSee('name="tarif"', false);
        $response->assertSee('name="volume"', false);
    }

    public function test_update_normalizes_indonesian_number_format(): void
    {
        $item = $this->makeItem(1, 'Belanja ATK', 100000);

        $response = $this->actingAs($this->user)->put('/rkas/' . $item->id, [
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'sumber_dana_id' => $this->sumber->id,
            'volume' => '2,5',
            'satuan' => 'paket',
            'tarif' => '1.500.000',
            'jumlah' => '3.750.000',
        ]);

        $response->assertRedirect(route('rkas.index'));

        $this->assertDatabaseHas('rkas_item', [
            'id' => $item->id,
            'volume' => 2.5,
            'tarif' => 1500000.0,
            'jumlah' => 3750000.0,
        ]);
    }

    public function test_destroy_removes_item_and_renumbers(): void
    {
        $item1 = $this->makeItem(1, 'Belanja ATK', 100000);
        $item2 = $this->makeItem(2, 'Belanja Tinta', 200000);

        $response = $this->actingAs($this->user)->delete('/rkas/' . $item1->id);

        $response->assertRedirect(route('rkas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('rkas_item', ['id' => $item1->id]);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'rkas_item',
            'aksi' => 'delete',
        ]);

        $item2->refresh();
        $this->assertSame(1, (int) $item2->no_urut);
    }

    public function test_destroy_all_deletes_matching_items(): void
    {
        $item1 = $this->makeItem(1, 'Belanja ATK', 100000);
        $item2 = $this->makeItem(2, 'Belanja ATK', 200000);
        $item3 = $this->makeItem(3, 'Belanja Tinta', 300000);

        $response = $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => $this->tahun->tahun,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('rkas_item', ['id' => $item1->id]);
        $this->assertDatabaseMissing('rkas_item', ['id' => $item2->id]);
        $this->assertDatabaseMissing('rkas_item', ['id' => $item3->id]);
    }

    public function test_destroy_all_returns_error_when_no_match(): void
    {
        $response = $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => 1999,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('rkas_item', 0);
    }

    public function test_destroy_all_creates_bulk_audit_log(): void
    {
        $this->makeItem(1, 'Belanja ATK', 100000);
        $this->makeItem(2, 'Belanja Tinta', 200000);

        $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => $this->tahun->tahun,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'rkas_item',
            'aksi' => 'delete_bulk',
        ]);
    }

    public function test_index_page_renders_new_filters(): void
    {
        $this->makeItem(1, 'Belanja ATK', 100000);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Cari rekening (kode / nama)...');
        $response->assertSee('Semua Jenis Belanja');
    }

    public function test_index_filters_by_kode_rekening(): void
    {
        $rekening2 = MasterKodeRekening::factory()->create();
        $itemA = $this->makeItem(1, 'Belanja ATK', 100000);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekening2->id,
            'no_urut' => 2,
            'uraian' => 'Belanja Tinta',
            'jumlah' => 200000,
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?kode_rekening_id=' . $this->rekening->id);

        $response->assertOk();
        $response->assertSee($itemA->uraian);
        $response->assertDontSee('Belanja Tinta');
    }

    public function test_index_filters_by_jenis_belanja(): void
    {
        $jb1 = JenisBelanja::factory()->create();
        $jb2 = JenisBelanja::factory()->create();
        $rekeningA = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jb1->id]);
        $rekeningB = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jb2->id]);

        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningA->id,
            'no_urut' => 1,
            'uraian' => 'Belanja BOS',
            'jumlah' => 100000,
        ]);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningB->id,
            'no_urut' => 2,
            'uraian' => 'Belanja BOP',
            'jumlah' => 200000,
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?jenis_belanja_id=' . $jb1->id);

        $response->assertOk();
        $response->assertSee('Belanja BOS');
        $response->assertDontSee('Belanja BOP');
    }

    public function test_destroy_all_filters_by_program_uuid(): void
    {
        $program2 = MasterProgram::factory()->create();
        $itemA = $this->makeItem(1, 'Belanja ATK', 100000);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $program2->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 2,
            'uraian' => 'Belanja Lainnya',
            'jumlah' => 200000,
        ]);

        $response = $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => $this->tahun->tahun,
            'program_id' => $this->program->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('rkas_item', ['id' => $itemA->id]);
        $this->assertDatabaseHas('rkas_item', ['uraian' => 'Belanja Lainnya']);
    }

    public function test_destroy_all_filters_by_kode_rekening(): void
    {
        $rekening2 = MasterKodeRekening::factory()->create();
        $itemA = $this->makeItem(1, 'Belanja ATK', 100000);
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekening2->id,
            'no_urut' => 2,
            'uraian' => 'Belanja Tinta',
            'jumlah' => 200000,
        ]);

        $response = $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => $this->tahun->tahun,
            'kode_rekening_id' => $this->rekening->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('rkas_item', ['id' => $itemA->id]);
        $this->assertDatabaseHas('rkas_item', ['uraian' => 'Belanja Tinta']);
    }

    public function test_destroy_all_filters_by_jenis_belanja(): void
    {
        $jb1 = JenisBelanja::factory()->create();
        $jb2 = JenisBelanja::factory()->create();
        $rekeningA = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jb1->id]);
        $rekeningB = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jb2->id]);

        $itemA = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningA->id,
            'no_urut' => 1,
            'uraian' => 'Belanja BOS',
            'jumlah' => 100000,
        ]);
        $itemB = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekeningB->id,
            'no_urut' => 2,
            'uraian' => 'Belanja BOP',
            'jumlah' => 200000,
        ]);

        $response = $this->actingAs($this->user)->post('/rkas/hapus-semua', [
            'tahun' => $this->tahun->tahun,
            'jenis_belanja_id' => $jb1->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('rkas_item', ['id' => $itemA->id]);
        $this->assertDatabaseHas('rkas_item', ['id' => $itemB->id]);
    }

    public function test_index_total_realisasi_mencerminkan_nota_multi_item(): void
    {
        $this->makeItem(1, 'Belanja ATK', 500000);
        $item1 = $this->makeItem(2, 'Belanja Obat', 100000);
        $item2 = $this->makeItem(3, 'Belanja Alat', 200000);

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0100/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
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

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 300000,
            'no_bukti' => 'BPU997/20519260/01/2026',
            'uraian' => 'Nota belanja NOTA-0100/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'metode_pengadaan' => 'siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Rp 300.000');
    }

    public function test_index_sisa_per_item_mencerminkan_realisasi_nota(): void
    {
        $item1 = $this->makeItem(1, 'Belanja Obat', 100000);
        $item2 = $this->makeItem(2, 'Belanja Alat', 200000);

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0200/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
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

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 300000,
            'no_bukti' => 'BPU998/20519260/01/2026',
            'uraian' => 'Nota belanja NOTA-0200/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'metode_pengadaan' => 'siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Rp 300.000');
        $response->assertSee('Rp 0');
    }

    public function test_index_sisa_volume_mencerminkan_nota_multi_item(): void
    {
        $item1 = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Tinta Spidol Whiteboard',
            'volume' => 10,
            'satuan' => 'dus',
            'tarif' => 10000,
            'jumlah' => 500000,
        ]);

        $nota = NotaBku::factory()->create([
            'no_nota' => 'NOTA-0300/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'bulan' => 1,
            'kegiatan_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'sumber_dana_id' => $this->sumber->id,
            'tahun_anggaran_id' => $this->tahun->id,
            'metode_pengadaan' => 'non_siplah',
            'created_by' => $this->user->id,
        ]);

        NotaBkuItem::factory()->create([
            'nota_bku_id' => $nota->id,
            'rkas_item_id' => $item1->id,
            'urutan' => 1,
            'jumlah' => 10,
            'satuan' => 'dus',
            'harga_satuan' => 10000,
            'subtotal' => 100000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => null,
            'nota_bku_id' => $nota->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'no_bukti' => 'BPU999/20519260/01/2026',
            'uraian' => 'Nota belanja NOTA-0300/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Rp 100.000');
        $response->assertSee('sisa 40 dus', false);
        $response->assertDontSee('sisa 10 dus');
    }

    public function test_index_bulan_filter_shows_only_items_with_plan_that_month(): void
    {
        $itemJan = $this->makeItem(1, 'Belanja Januari', 100000);
        $itemFeb = $this->makeItem(2, 'Belanja Februari', 200000);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $itemJan->id,
            'bulan' => 1,
            'rencana' => 50000,
        ]);
        RkasItemBulan::factory()->create([
            'rkas_item_id' => $itemFeb->id,
            'bulan' => 2,
            'rencana' => 200000,
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?bulan=1');

        $response->assertOk();
        $response->assertSee('Belanja Januari');
        $response->assertDontSee('Belanja Februari');
    }

    public function test_index_bulan_filter_uses_monthly_plan_and_realisasi(): void
    {
        $item = $this->makeItem(1, 'Belanja Bulanan', 1000000);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 200000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
            'no_bukti' => 'BPU501/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'metode_pengadaan' => 'non_siplah',
        ]);
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'jumlah' => 300000,
            'no_bukti' => 'BPU502/20519260/02/2026',
            'tanggal' => '2026-02-15',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?bulan=1');

        $response->assertOk();
        $response->assertSee('Belanja Bulanan');
        $response->assertSee('Rp 200.000');
        $response->assertSee('Rp 50.000');
        $response->assertSee('Rp 150.000');
    }

    public function test_index_without_bulan_uses_yearly_jumlah_and_cumulative_realisasi(): void
    {
        $item = $this->makeItem(1, 'Belanja Tahunan', 1000000);

        RkasItemBulan::factory()->create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 200000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
            'no_bukti' => 'BPU503/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Belanja Tahunan');
        $response->assertSee('Rp 1.000.000');
        $response->assertSee('Rp 50.000');
    }

    public function test_index_menampilkan_ringkasan_capaian_dan_realisasi_per_jenis_belanja(): void
    {
        $jenisA = JenisBelanja::factory()->create(['nama' => 'Belanja Alat Tulis']);
        $jenisB = JenisBelanja::factory()->create(['nama' => 'Belanja Obat']);
        $rekA = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jenisA->id]);
        $rekB = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jenisB->id]);

        $itemA = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekA->id,
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'jumlah' => 500000,
        ]);
        $itemB = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $rekB->id,
            'no_urut' => 2,
            'uraian' => 'Belanja Obat Kantor',
            'jumlah' => 300000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $itemA->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 100000,
            'no_bukti' => 'BPU601/20519260/01/2026',
            'tanggal' => '2026-01-15',
            'metode_pengadaan' => 'non_siplah',
        ]);
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $itemB->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
            'no_bukti' => 'BPU602/20519260/01/2026',
            'tanggal' => '2026-01-16',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Ringkasan Capaian');
        $response->assertSee('Realisasi per Jenis Belanja');
        $response->assertSee('Rp 800.000');
        $response->assertSee('Rp 150.000');
        $response->assertSee('Belanja Alat Tulis');
        $response->assertSee('Belanja Obat');
        $response->assertSee('66.7');
    }

    public function test_index_sisa_volume_tanpa_filter_bulan_tidak_negatif(): void
    {
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu',
            'volume' => 13,
            'satuan' => 'dus',
            'tarif' => 30000,
            'jumlah' => 4290000,
        ]);

        foreach ([1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $bln) {
            RkasItemBulan::create([
                'rkas_item_id' => $item->id,
                'bulan' => $bln,
                'rencana' => 390000,
            ]);
        }

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 390000,
            'volume' => 13,
            'satuan' => 'dus',
            'no_bukti' => 'BPU101/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'metode_pengadaan' => 'non_siplah',
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'jumlah' => 390000,
            'volume' => 13,
            'satuan' => 'dus',
            'no_bukti' => 'BPU102/20519260/02/2026',
            'tanggal' => '2026-02-13',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas');

        $response->assertOk();
        $response->assertSee('Rp 4.290.000');
        $response->assertSee('Rp 780.000');
        $response->assertSee('Rp 3.510.000');
        $response->assertSee('117 dus', false);
        $response->assertDontSee('-13 dus');
        $response->assertSee('143 dus × Rp 30.000', false);
    }

    public function test_index_sisa_volume_dengan_filter_bulan_tetap_benar(): void
    {
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu',
            'volume' => 13,
            'satuan' => 'dus',
            'tarif' => 30000,
            'jumlah' => 4290000,
        ]);

        RkasItemBulan::create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 390000,
        ]);

        RkasItemBulan::create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 390000,
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 1,
            'jenis' => 'pengeluaran',
            'jumlah' => 390000,
            'volume' => 13,
            'satuan' => 'dus',
            'no_bukti' => 'BPU103/20519260/01/2026',
            'tanggal' => '2026-01-22',
            'metode_pengadaan' => 'non_siplah',
        ]);

        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'jumlah' => 390000,
            'volume' => 13,
            'satuan' => 'dus',
            'no_bukti' => 'BPU104/20519260/02/2026',
            'tanggal' => '2026-02-13',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?bulan=1');

        $response->assertOk();
        $response->assertSee('Rp 390.000');
        $response->assertSee('13 dus × Rp 30.000', false);
        $response->assertSee('sisa 0 dus', false);
        $response->assertDontSee('-13 dus');
    }

    /**
     * Skenario user: item "Token Listrik/PLN", Jan rencana 103500 realisasi 0,
     * Feb rencana 103500 realisasi 203500. Filter bulan Feb:
     * - Per-bulan: realisasi 203500 > rencana 103500 → 197%
     * - Kumulatif: rencana 207000 − realisasi 203500 = 3500 → 98%
     * Badge harus "Normal (98%)", BUKAN "Over Budget (197%)".
     */
    public function test_index_sisa_dan_badge_pakai_kumulatif_bukan_per_bulan(): void
    {
        $item = RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $this->sumber->id,
            'program_id' => $this->program->id,
            'kode_rekening_id' => $this->rekening->id,
            'no_urut' => 1,
            'uraian' => 'Token Listrik/PLN',
            'tarif' => 10000,
            'satuan' => 'kwh',
        ]);

        RkasItemBulan::create([
            'rkas_item_id' => $item->id,
            'bulan' => 1,
            'rencana' => 103500,
        ]);

        RkasItemBulan::create([
            'rkas_item_id' => $item->id,
            'bulan' => 2,
            'rencana' => 103500,
        ]);

        // Bulan 1: tidak ada realisasi
        // Bulan 2: realisasi 203500 (melebihi rencana Feb 103500)
        TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => $item->id,
            'sumber_dana_id' => $this->sumber->id,
            'bulan' => 2,
            'jenis' => 'pengeluaran',
            'jumlah' => 203500,
            'no_bukti' => 'BPU200/20519260/02/2026',
            'tanggal' => '2026-02-10',
            'metode_pengadaan' => 'non_siplah',
        ]);

        $response = $this->actingAs($this->user)->get('/rkas?bulan=2');
        $response->assertOk();

        // Rencana per-bulan Feb tetap tampil
        $response->assertSee('Rp 103.500');
        // Realisasi per-bulan Feb tetap tampil
        $response->assertSee('Rp 203.500');

        // Sisa kumulatif: Rp 3.500 (bukan −Rp 100.000 per-bulan)
        // Muncul di 2 tempat: badge per-item DAN summary card "Sisa Anggaran"
        $response->assertSee('Rp 3.500');
        // Ringkasan Capaian summary card juga kumulatif: tidak ada "Rp -100.000"
        $response->assertDontSee('Rp -100.000');

        // Badge kumulatif: Hampir Habis 98% (≥90%), BUKAN Over Budget 197%
        $response->assertSee('Hampir Habis (98%)');
        $response->assertDontSee('Over Budget');
    }
}
