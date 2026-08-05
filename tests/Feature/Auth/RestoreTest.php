<?php

namespace Tests\Feature\Auth;

use App\Exceptions\RestoreException;
use App\Models\User;
use App\Services\RestoreService;
use App\Support\AppState;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PDO;
use Tests\TestCase;
use ZipArchive;

class RestoreTest extends TestCase
{
    use RefreshDatabase;

    protected string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-marker-'.uniqid().'.tmp';
        config(['app.initialized_marker_path' => $this->markerPath]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->markerPath)) {
            @unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_restore_page_rendered_when_first_run(): void
    {
        User::factory()->create();

        $this->get('/restore')
            ->assertOk()
            ->assertSee('Pulihkan dari Backup');
    }

    public function test_restore_page_redirects_when_not_first_run(): void
    {
        User::factory()->create(['last_login_at' => now()]);

        $this->get('/restore')->assertRedirect(route('login'));
    }

    public function test_restore_rejects_invalid_zip(): void
    {
        User::factory()->create();

        $file = UploadedFile::fake()->create('backup.zip', 50);

        $this->post('/restore', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_restore_success_replaces_database_and_marks_initialized(): void
    {
        User::factory()->create();

        $service = $this->spy(RestoreService::class);

        $file = UploadedFile::fake()->create('backup.zip', 50);

        $this->post('/restore', ['file' => $file])
            ->assertOk()
            ->assertSee('Restore Berhasil');

        $service->shouldHaveReceived('restore');

        $this->assertFileExists($this->markerPath);
        $this->assertFalse(AppState::isFirstRun());
    }

    public function test_service_replaces_target_database_and_keeps_pre_restore(): void
    {
        $sourceDb = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-source-'.uniqid().'.sqlite';
        $targetDb = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-target-'.uniqid().'.sqlite';
        $zipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-backup-'.uniqid().'.zip';

        try {
            $this->createDatabase($sourceDb, true);
            $this->createDatabase($targetDb, false);
            $this->createZip($zipPath, $sourceDb);

            $service = new RestoreService(new Filesystem(), false);
            $service->restore($zipPath, $targetDb);

            $this->assertTrue($this->databaseHasTable($targetDb, 'users'));
            $this->assertTrue($this->databaseHasTable($targetDb, 'migrations'));
            $this->assertFileExists($targetDb.'.pre-restore');
        } finally {
            @unlink($sourceDb);
            @unlink($targetDb);
            @unlink($targetDb.'.pre-restore');
            @unlink($zipPath);
        }
    }

    public function test_service_rejects_zip_without_database(): void
    {
        $sourceFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-txt-'.uniqid().'.txt';
        $zipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-badbackup-'.uniqid().'.zip';

        try {
            file_put_contents($sourceFile, 'not a database');
            $this->createZip($zipPath, $sourceFile);

            $service = new RestoreService(new Filesystem(), false);

            $this->assertThrows(
                fn () => $service->restore($zipPath, sys_get_temp_dir().DIRECTORY_SEPARATOR.'smartrkas-never-'.uniqid().'.sqlite'),
                RestoreException::class
            );
        } finally {
            @unlink($sourceFile);
            @unlink($zipPath);
        }
    }

    private function createDatabase(string $path, bool $withTables): void
    {
        $pdo = new PDO('sqlite:'.$path);

        if ($withTables) {
            $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
            $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY)');
        }

        $pdo = null;
    }

    private function databaseHasTable(string $path, string $table): bool
    {
        $pdo = new PDO('sqlite:'.$path);
        $statement = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ".$pdo->quote($table)
        );
        $found = $statement !== false && $statement->fetch() !== false;
        $pdo = null;

        return $found;
    }

    private function createZip(string $zipPath, string $filePath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($filePath, 'db-dumps/'.basename($filePath));
        $zip->close();
    }
}
