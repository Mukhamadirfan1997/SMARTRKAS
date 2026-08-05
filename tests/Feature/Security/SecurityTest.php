<?php

namespace Tests\Feature\Security;

use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_flash_success_message_is_escaped_against_xss(): void
    {
        TahunAnggaran::factory()->create(['status' => true]);
        session(['success' => '<script>alert(1)</script>']);

        $this->actingAs($this->user)
            ->get('/rkas')
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_master_program_import_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->create('large.xlsx', 6000);

        $this->actingAs($this->user)
            ->post('/master-program/import', ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_master_kode_rekening_import_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->create('large.xlsx', 6000);

        $this->actingAs($this->user)
            ->post('/master-kode-rekening/import', ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
