<?php

namespace Tests\Feature\RKAS;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
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
}
