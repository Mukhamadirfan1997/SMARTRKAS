<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_by_email(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:reset-password', ['user' => $user->email, 'password' => 'newpass123'])
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_reset_password_by_id(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:reset-password', ['user' => (string) $user->id, 'password' => 'newpass123'])
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_reset_password_generates_random_when_not_provided(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:reset-password', ['user' => $user->email])
            ->assertExitCode(0);

        $this->assertNotSame(
            $user->fresh()->password,
            $user->getOriginal('password'),
        );
    }

    public function test_reset_password_returns_failure_for_missing_user(): void
    {
        $this->artisan('user:reset-password', ['user' => 'nonexistent@example.com'])
            ->assertExitCode(1);
    }
}
