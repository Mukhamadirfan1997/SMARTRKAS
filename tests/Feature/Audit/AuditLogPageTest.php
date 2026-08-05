<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengaturan/riwayat-aktivitas')->assertRedirect('/login');
    }

    public function test_index_lists_audit_entries(): void
    {
        $user = User::factory()->create(['name' => 'Bu Operator', 'email' => 'op@sekolah.test']);

        AuditLog::create([
            'user_id' => $user->id,
            'tabel' => 'rkas_item',
            'aksi' => 'delete',
            'data_lama' => ['uraian' => 'Pembelian meja'],
        ]);
        AuditLog::create([
            'user_id' => $this->admin->id,
            'tabel' => 'import_rkas',
            'aksi' => 'import',
            'data_baru' => ['jumlah_baris' => 25],
        ]);

        $this->actingAs($this->admin)
            ->get('/pengaturan/riwayat-aktivitas')
            ->assertOk()
            ->assertSee('Bu Operator')
            ->assertSee('Rkas Item')
            ->assertSee('Import Rkas')
            ->assertSee('Delete');
    }

    public function test_index_filters_by_tabel(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'tabel' => 'rkas_item', 'aksi' => 'delete']);
        AuditLog::create(['user_id' => $this->admin->id, 'tabel' => 'transaksi_bku', 'aksi' => 'override_anggaran']);

        $this->actingAs($this->admin)
            ->get('/pengaturan/riwayat-aktivitas?tabel=rkas_item')
            ->assertOk()
            ->assertSee('Rkas Item')
            ->assertDontSee('Override Anggaran');
    }

    public function test_index_searches_by_user(): void
    {
        $first = User::factory()->create(['name' => 'Pak Kepala', 'email' => 'kepala@sekolah.test']);
        $second = User::factory()->create(['name' => 'Bu Bendahara', 'email' => 'bendahara@sekolah.test']);

        AuditLog::create(['user_id' => $first->id, 'tabel' => 'rkas_item', 'aksi' => 'delete']);
        AuditLog::create(['user_id' => $second->id, 'tabel' => 'rkas_item', 'aksi' => 'update']);

        $this->actingAs($this->admin)
            ->get('/pengaturan/riwayat-aktivitas?q=kepala@sekolah.test')
            ->assertOk()
            ->assertSee('Pak Kepala')
            ->assertDontSee('Bu Bendahara');
    }

    public function test_index_shows_empty_state(): void
    {
        $this->actingAs($this->admin)
            ->get('/pengaturan/riwayat-aktivitas')
            ->assertOk()
            ->assertSee('Belum ada aktivitas tercatat');
    }
}
