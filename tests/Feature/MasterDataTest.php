<?php

namespace Tests\Feature;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_master_pages(): void
    {
        $response = $this->get('/tahun-anggaran');

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }

    public function test_dashboard_page_can_be_rendered_without_active_year(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_login_is_blocked_for_inactive_user(): void
    {
        User::factory()->nonaktif()->create([
            'email' => 'nonaktif@sekolah.test',
        ]);

        $response = $this->post('/login', [
            'email' => 'nonaktif@sekolah.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'aktif@sekolah.test',
        ]);

        $this->post('/login', [
            'email' => 'aktif@sekolah.test',
            'password' => 'password',
        ]);

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_tahun_anggaran_crud_and_set_active(): void
    {
        $user = User::factory()->create();
        TahunAnggaran::factory()->create(['tahun' => 2025, 'status' => true]);

        $this->actingAs($user)->get('/tahun-anggaran')->assertStatus(200);
        $this->actingAs($user)->get('/tahun-anggaran/create')->assertStatus(200);

        $response = $this->actingAs($user)->post('/tahun-anggaran', ['tahun' => 2026]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('tahun-anggaran.index', absolute: false));

        $tahun2026 = TahunAnggaran::where('tahun', 2026)->firstOrFail();

        $response = $this->actingAs($user)->post("/tahun-anggaran/{$tahun2026->id}/set-active");
        $response->assertSessionHas('success');

        $this->assertTrue(TahunAnggaran::findOrFail($tahun2026->id)->status);
        $this->assertFalse(TahunAnggaran::where('tahun', 2025)->firstOrFail()->status);

        $this->actingAs($user)->get("/tahun-anggaran/{$tahun2026->id}/edit")->assertStatus(200);

        $response = $this->actingAs($user)->put("/tahun-anggaran/{$tahun2026->id}", ['tahun' => 2027]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tahun_anggaran', ['tahun' => 2027]);

        $response = $this->actingAs($user)->delete("/tahun-anggaran/{$tahun2026->id}");
        $response->assertSessionHas('error', 'Tahun anggaran aktif tidak boleh dihapus. Nonaktifkan terlebih dahulu dengan mengaktifkan tahun anggaran lain.');

        TahunAnggaran::where('tahun', 2025)->firstOrFail()->update(['status' => true]);
        TahunAnggaran::findOrFail($tahun2026->id)->update(['status' => false]);
        $this->actingAs($user)->delete("/tahun-anggaran/{$tahun2026->id}")->assertStatus(302);
        $this->assertDatabaseMissing('tahun_anggaran', ['tahun' => 2027]);
    }

    public function test_sumber_dana_crud(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sumber-dana')->assertStatus(200);
        $this->actingAs($user)->get('/sumber-dana/create')->assertStatus(200);

        $response = $this->actingAs($user)->post('/sumber-dana', ['kode' => 'BOSP-REG', 'nama' => 'BOSP Reguler']);
        $response->assertSessionHasNoErrors();

        $sumberDana = SumberDana::where('kode', 'BOSP-REG')->firstOrFail();

        $this->actingAs($user)->get("/sumber-dana/{$sumberDana->id}/edit")->assertStatus(200);

        $response = $this->actingAs($user)->put("/sumber-dana/{$sumberDana->id}", ['kode' => 'BOSP-KIN', 'nama' => 'BOSP Kinerja']);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sumber_dana', ['kode' => 'BOSP-KIN']);

        $this->actingAs($user)->delete("/sumber-dana/{$sumberDana->id}")->assertStatus(302);
        $this->assertDatabaseMissing('sumber_dana', ['id' => $sumberDana->id]);
    }

    public function test_jenis_belanja_crud(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/jenis-belanja')->assertStatus(200);
        $this->actingAs($user)->get('/jenis-belanja/create')->assertStatus(200);

        $response = $this->actingAs($user)->post('/jenis-belanja', ['nama' => 'Belanja Pegawai']);
        $response->assertSessionHasNoErrors();

        $jenisBelanja = JenisBelanja::where('nama', 'Belanja Pegawai')->firstOrFail();

        $this->actingAs($user)->get("/jenis-belanja/{$jenisBelanja->id}/edit")->assertStatus(200);

        $response = $this->actingAs($user)->put("/jenis-belanja/{$jenisBelanja->id}", ['nama' => 'Belanja Honor']);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jenis_belanja', ['nama' => 'Belanja Honor']);

        $this->actingAs($user)->delete("/jenis-belanja/{$jenisBelanja->id}")->assertStatus(302);
        $this->assertDatabaseMissing('jenis_belanja', ['id' => $jenisBelanja->id]);
    }

    public function test_master_program_crud(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/master-program')->assertStatus(200);
        $this->actingAs($user)->get('/master-program/create')->assertStatus(200);

        $response = $this->actingAs($user)->post('/master-program', [
            'kode' => '1.2.1',
            'nama' => 'Penerimaan Peserta Didik',
            'level' => 2,
        ]);
        $response->assertSessionHasNoErrors();

        $program = MasterProgram::where('kode', '1.2.1')->firstOrFail();

        $this->actingAs($user)->get("/master-program/{$program->id}/edit")->assertStatus(200);

        $response = $this->actingAs($user)->put("/master-program/{$program->id}", [
            'kode' => '1.2.2',
            'nama' => 'Pengembangan Profesi',
            'level' => 2,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_program', ['kode' => '1.2.2']);

        $this->actingAs($user)->delete("/master-program/{$program->id}")->assertStatus(302);
        $this->assertDatabaseMissing('master_program', ['id' => $program->id]);
    }

    public function test_master_kode_rekening_crud(): void
    {
        $user = User::factory()->create();
        $jenisBelanja = JenisBelanja::factory()->create();

        $this->actingAs($user)->get('/master-kode-rekening')->assertStatus(200);
        $this->actingAs($user)->get('/master-kode-rekening/create')->assertStatus(200);

        $response = $this->actingAs($user)->post('/master-kode-rekening', [
            'kode' => '5.1.02.01.01.0001',
            'nama' => 'Belanja Bahan Bangunan',
            'jenis_belanja_id' => $jenisBelanja->id,
        ]);
        $response->assertSessionHasNoErrors();

        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0001')->firstOrFail();

        $this->actingAs($user)->get("/master-kode-rekening/{$rekening->id}/edit")->assertStatus(200);

        $response = $this->actingAs($user)->put("/master-kode-rekening/{$rekening->id}", [
            'kode' => '5.1.02.01.01.0002',
            'nama' => 'Belanja Bahan Update',
            'jenis_belanja_id' => $jenisBelanja->id,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_kode_rekening', ['kode' => '5.1.02.01.01.0002']);

        $this->actingAs($user)->delete("/master-kode-rekening/{$rekening->id}")->assertStatus(302);
        $this->assertDatabaseMissing('master_kode_rekening', ['id' => $rekening->id]);
    }

    public function test_master_kode_rekening_download_template(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/master-kode-rekening/download-template');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_rejects_invalid_file_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/master-kode-rekening/import', [
            'file' => UploadedFile::fake()->create('rekening.pdf', 100),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_destroy_all_master_kode_rekenings_respects_search_filter(): void
    {
        $user = User::factory()->create();
        MasterKodeRekening::factory()->create(['kode' => '5.9.99.99.99.9999', 'nama' => 'Rekening Khusus']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.01.01.001', 'nama' => 'Rekening Lain']);

        $this->actingAs($user)->post('/master-kode-rekening/hapus-semua', ['search' => '5.9']);

        $this->assertDatabaseMissing('master_kode_rekening', ['kode' => '5.9.99.99.99.9999']);
        $this->assertDatabaseHas('master_kode_rekening', ['kode' => '5.1.01.01.001']);
    }

    public function test_pengaturan_sekolah_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/pengaturan-sekolah')->assertStatus(200);

        $response = $this->actingAs($user)->put('/pengaturan-sekolah', [
            'npsn' => '12345678',
            'nama' => 'SD NEGERI CONTOH',
            'kecamatan' => 'Sukajadi',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pengaturan_sekolah', ['nama' => 'SD NEGERI CONTOH']);

        $pengaturan = PengaturanSekolah::get();
        $this->assertNotNull($pengaturan);

        $this->actingAs($user)->put('/pengaturan-sekolah', [
            'nama' => 'SD NEGERI UPDATED',
        ])->assertSessionHasNoErrors();

        $this->assertSame('SD NEGERI UPDATED', $pengaturan->refresh()->nama);
    }
}
