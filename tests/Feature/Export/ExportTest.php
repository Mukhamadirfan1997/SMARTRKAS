<?php

namespace Tests\Feature\Export;

use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_export_download(): void
    {
        $exportJob = ExportJob::factory()->create();
        $this->get("/exports/{$exportJob->id}/download")->assertRedirect('/login');
    }

    public function test_guest_cannot_access_export_status(): void
    {
        $exportJob = ExportJob::factory()->create();
        $this->get("/exports/{$exportJob->id}/status")->assertRedirect('/login');
    }

    public function test_user_cannot_download_other_users_export(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exportJob = ExportJob::factory()->create([
            'user_id' => $user2->id,
            'status' => 'completed',
            'file_path' => 'exports/test.xlsx',
            'filename' => 'test.xlsx',
        ]);

        Storage::disk('public')->put('exports/test.xlsx', 'dummy');

        $this->actingAs($user1)->get("/exports/{$exportJob->id}/download")->assertStatus(403);
    }

    public function test_user_cannot_check_status_of_other_users_export(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exportJob = ExportJob::factory()->create([
            'user_id' => $user2->id,
        ]);

        $this->actingAs($user1)->getJson("/exports/{$exportJob->id}/status")->assertStatus(403);
    }

    public function test_download_returns_404_when_not_completed(): void
    {
        $user = User::factory()->create();
        $exportJob = ExportJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
        ]);

        $this->actingAs($user)->get("/exports/{$exportJob->id}/download")->assertStatus(404);
    }

    public function test_download_returns_404_when_file_missing(): void
    {
        $user = User::factory()->create();
        $exportJob = ExportJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'file_path' => 'exports/nonexistent.xlsx',
            'filename' => 'test.xlsx',
        ]);

        $this->actingAs($user)->get("/exports/{$exportJob->id}/download")->assertStatus(404);
    }

    public function test_user_can_download_own_completed_export(): void
    {
        $user = User::factory()->create();
        $exportJob = ExportJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'file_path' => 'exports/test.xlsx',
            'filename' => 'laporan.xlsx',
        ]);

        Storage::disk('public')->put('exports/test.xlsx', 'dummy content');

        $response = $this->actingAs($user)->get("/exports/{$exportJob->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=laporan.xlsx');
    }

    public function test_user_can_check_export_status(): void
    {
        $user = User::factory()->create();
        $exportJob = ExportJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'filename' => 'test.xlsx',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/exports/{$exportJob->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $exportJob->id,
            'status' => 'completed',
            'filename' => 'test.xlsx',
        ]);
    }

    public function test_export_status_shows_error_when_failed(): void
    {
        $user = User::factory()->create();
        $exportJob = ExportJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'error_message' => 'Something went wrong',
        ]);

        $response = $this->actingAs($user)->getJson("/exports/{$exportJob->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'failed',
            'error_message' => 'Something went wrong',
        ]);
    }
}
