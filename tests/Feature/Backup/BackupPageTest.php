<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        config(['backup.backup.name' => 'SmartRKAS']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengaturan/backup')->assertRedirect('/login');
        $this->post('/pengaturan/backup/now')->assertRedirect('/login');
    }

    public function test_index_lists_backup_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('SmartRKAS/backup-2026-08-05-01-30.zip', 'data1');
        Storage::disk('local')->put('SmartRKAS/backup-2026-08-06-01-30.zip', 'data2');

        $this->actingAs($this->user)
            ->get('/pengaturan/backup')
            ->assertOk()
            ->assertSee('backup-2026-08-05-01-30.zip')
            ->assertSee('backup-2026-08-06-01-30.zip')
            ->assertSee('2');
    }

    public function test_index_shows_empty_state_without_backups(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user)
            ->get('/pengaturan/backup')
            ->assertOk()
            ->assertSee('Belum ada backup');
    }

    public function test_run_shows_success_when_backup_succeeds(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);

        $this->actingAs($this->user)
            ->post('/pengaturan/backup/now')
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    public function test_run_shows_error_when_backup_fails(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);

        $this->actingAs($this->user)
            ->post('/pengaturan/backup/now')
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    }

    public function test_run_shows_error_when_artisan_throws(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('Disk full'));

        $this->actingAs($this->user)
            ->post('/pengaturan/backup/now')
            ->assertRedirect()
            ->assertSessionHas('error', 'Backup gagal: Disk full')
            ->assertSessionMissing('success');
    }

    public function test_backup_file_can_be_downloaded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('SmartRKAS/backup-2026-08-05.zip', 'backup content');

        $this->actingAs($this->user)
            ->get('/pengaturan/backup/download/backup-2026-08-05.zip')
            ->assertOk()
            ->assertDownload('backup-2026-08-05.zip');
    }

    public function test_download_rejects_path_traversal(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user)
            ->get('/pengaturan/backup/download/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_download_rejects_unknown_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user)
            ->get('/pengaturan/backup/download/tidak-ada.zip')
            ->assertNotFound();
    }
}
