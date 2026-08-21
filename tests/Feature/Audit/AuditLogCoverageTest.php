<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditLogCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_master_program_create_is_logged(): void
    {
        $this->actingAs($this->user)->post('/master-program', [
            'kode' => '1.1',
            'nama' => 'Program Wajib',
            'level' => 1,
        ])->assertRedirect(route('master-program.index'));

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'master_program',
            'aksi' => 'create',
        ]);
    }

    public function test_master_program_update_is_logged_with_before_after(): void
    {
        $program = MasterProgram::create(['kode' => '1.1', 'nama' => 'Lama', 'level' => 1]);

        $this->actingAs($this->user)->put('/master-program/' . $program->id, [
            'kode' => '1.1',
            'nama' => 'Baru',
            'level' => 1,
        ])->assertRedirect(route('master-program.index'));

        $log = AuditLog::where('tabel', 'master_program')->where('aksi', 'update')->firstOrFail();
        $dataBaru = $log->data_baru ?? [];
        $dataLama = $log->data_lama ?? [];

        $this->assertSame('Baru', $dataBaru['nama'] ?? null);
        $this->assertSame('Lama', $dataLama['nama'] ?? null);
    }

    public function test_backup_run_is_logged(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);

        $this->actingAs($this->user)
            ->post('/pengaturan/backup/now')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'backup',
            'aksi' => 'run',
        ]);
    }

    public function test_telegram_settings_update_is_logged_without_token_value(): void
    {
        $this->actingAs($this->user)->put('/pengaturan/telegram', [
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => '123456:ABC',
        ])->assertRedirect();

        $log = AuditLog::where('tabel', 'telegram_pengaturan')->where('aksi', 'update')->firstOrFail();
        $dataBaru = $log->data_baru ?? [];

        $this->assertSame('123456789', $dataBaru['telegram_chat_id'] ?? null);
        $this->assertSame(true, $dataBaru['telegram_bot_token_set'] ?? null);
        $this->assertArrayNotHasKey('telegram_bot_token', $dataBaru);
    }

    public function test_pengaturan_sekolah_update_is_logged(): void
    {
        PengaturanSekolah::create(['nama' => 'SD Lama']);

        $this->actingAs($this->user)->put('/pengaturan-sekolah', [
            'nama' => 'SD Negeri Contoh',
            'npsn' => '12345678',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->user->id,
            'tabel' => 'pengaturan_sekolah',
            'aksi' => 'update',
        ]);
    }
}
