<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlushSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_flush_sessions_deletes_all_sessions_and_remember_tokens(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('payload'),
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('app:flush-sessions')
            ->expectsOutput('Semua sesi login dan token "ingat saya" telah dihapus.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'remember_token' => null]);
    }

    public function test_flush_sessions_is_idempotent_on_empty_database(): void
    {
        $this->artisan('app:flush-sessions')->assertExitCode(0);

        $this->assertDatabaseCount('sessions', 0);
    }
}
