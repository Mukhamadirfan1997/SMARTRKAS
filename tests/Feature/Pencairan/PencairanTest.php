<?php

namespace Tests\Feature\Pencairan;

use App\Models\AuditLog;
use App\Models\Pencairan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TahunAnggaran $tahunAktif;

    private SumberDana $sumberDana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahunAktif = TahunAnggaran::factory()->create([
            'tahun' => 2026,
            'status' => true,
        ]);
        $this->sumberDana = SumberDana::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pencairan.index'))->assertRedirect('/login');
    }

    public function test_index_page_renders_with_form_and_empty_state(): void
    {
        $response = $this->actingAs($this->user)->get(route('pencairan.index'));

        $response->assertOk();
        $response->assertSee('Data Pencairan');
        $response->assertSee('Catat Pencairan');
        $response->assertSee('Belum ada data pencairan tahun 2026', false);
        $response->assertSee(route('pencairan.store'));
    }

    public function test_store_creates_pencairan_dengan_bulan_dari_tanggal(): void
    {
        $response = $this->actingAs($this->user)->post(route('pencairan.store'), [
            'tanggal' => '2026-03-05',
            'sumber_dana_id' => $this->sumberDana->id,
            'nominal' => '90.160.000',
            'keterangan' => 'SP2D Tahap 1 BOSP Reguler',
        ]);

        $response->assertRedirect(route('pencairan.index'));
        $response->assertSessionHas('success');

        $pencairan = Pencairan::first();
        $this->assertNotNull($pencairan);
        $this->assertSame($this->tahunAktif->id, $pencairan->tahun_anggaran_id);
        $this->assertSame($this->sumberDana->id, $pencairan->sumber_dana_id);
        $this->assertSame('2026-03-05', $pencairan->tanggal->toDateString());
        $this->assertSame(3, $pencairan->bulan);
        $this->assertSame(90160000.0, $pencairan->nominal);
        $this->assertSame($this->user->id, $pencairan->created_by);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'pencairan',
            'aksi' => 'create',
        ]);
    }

    public function test_store_wajib_pilih_sumber_dana(): void
    {
        $response = $this->actingAs($this->user)->post(route('pencairan.store'), [
            'tanggal' => '2026-03-05',
            'sumber_dana_id' => '',
            'nominal' => '5000000',
        ]);

        $response->assertSessionHasErrors('sumber_dana_id');
        $this->assertDatabaseCount('pencairan', 0);
    }

    public function test_store_menolak_nominal_nol_dan_negatif(): void
    {
        $this->actingAs($this->user)->post(route('pencairan.store'), [
            'tanggal' => '2026-03-05',
            'sumber_dana_id' => $this->sumberDana->id,
            'nominal' => '0',
        ])->assertSessionHasErrors('nominal');

        $this->actingAs($this->user)->post(route('pencairan.store'), [
            'tanggal' => '2026-03-05',
            'sumber_dana_id' => $this->sumberDana->id,
            'nominal' => '-1000',
        ])->assertSessionHasErrors('nominal');

        $this->assertDatabaseCount('pencairan', 0);
    }

    public function test_index_menampilkan_riwayat_dan_total_tahun(): void
    {
        Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAktif->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal' => '2026-01-20',
            'bulan' => 1,
            'nominal' => 10768500,
        ]);
        Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAktif->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal' => '2026-02-11',
            'bulan' => 2,
            'nominal' => 27014500,
        ]);

        $response = $this->actingAs($this->user)->get(route('pencairan.index'));

        $response->assertOk();
        $response->assertSee('Rp 10.768.500');
        $response->assertSee('Rp 27.014.500');
        // Total = 37.783.000 pada kartu stat.
        $response->assertSee('Rp 37.783.000');
    }

    public function test_update_mengubah_data_dan_bulan_mengikuti_tanggal_baru(): void
    {
        $pencairan = Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAktif->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'tanggal' => '2026-01-20',
            'bulan' => 1,
            'nominal' => 10000000,
        ]);

        $response = $this->actingAs($this->user)->put(route('pencairan.update', $pencairan), [
            'tanggal' => '2026-04-15',
            'sumber_dana_id' => $this->sumberDana->id,
            'nominal' => '12.500.000',
            'keterangan' => 'SP2D dikoreksi',
        ]);

        $response->assertRedirect(route('pencairan.index'));
        $response->assertSessionHas('success');

        $pencairan->refresh();
        $this->assertSame('2026-04-15', $pencairan->tanggal->toDateString());
        $this->assertSame(4, $pencairan->bulan);
        $this->assertSame(12500000.0, $pencairan->nominal);
        $this->assertSame($this->tahunAktif->id, $pencairan->tahun_anggaran_id);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'pencairan',
            'aksi' => 'update',
        ]);
    }

    public function test_destroy_soft_delete_pencairan_dengan_audit(): void
    {
        $pencairan = Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAktif->id,
            'nominal' => 5000000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('pencairan.destroy', $pencairan));

        $response->assertRedirect(route('pencairan.index'));
        $this->assertSoftDeleted('pencairan', ['id' => $pencairan->id]);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'pencairan',
            'aksi' => 'delete',
        ]);
    }

    public function test_edit_page_renders_dengan_nilai_lama(): void
    {
        $pencairan = Pencairan::factory()->create([
            'tahun_anggaran_id' => $this->tahunAktif->id,
            'sumber_dana_id' => $this->sumberDana->id,
            'nominal' => 25000000,
        ]);

        $response = $this->actingAs($this->user)->get(route('pencairan.edit', $pencairan));

        $response->assertOk();
        $response->assertSee('Edit Pencairan');
        $response->assertSee('25.000.000');
        $response->assertSee(route('pencairan.update', $pencairan));
    }
}
