<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCleanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_clean_deletes_only_logs_older_than_days(): void
    {
        $old = new AuditLog(['tabel' => 'test', 'aksi' => 'delete']);
        $old->created_at = now()->subDays(100);
        $old->save();

        $recent = new AuditLog(['tabel' => 'test', 'aksi' => 'delete']);
        $recent->created_at = now()->subDays(10);
        $recent->save();

        $this->artisan('audit:clean', ['days' => 90])
            ->expectsOutput('Deleted 1 audit log entries older than 90 days.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('audit_log', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_log', ['id' => $recent->id]);
    }

    public function test_audit_clean_uses_default_90_days(): void
    {
        $old = new AuditLog(['tabel' => 'test', 'aksi' => 'delete']);
        $old->created_at = now()->subDays(91);
        $old->save();

        $this->artisan('audit:clean')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('audit_log', ['id' => $old->id]);
    }
}
