<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecoveryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_shows_recovery_option(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Kode Pemulihan')
            ->assertSee('Reset dengan Kode Pemulihan');
    }

    public function test_password_can_be_reset_with_valid_recovery_code(): void
    {
        $user = User::factory()->create();
        $code = $user->setRecoveryCode();
        $user->save();

        $response = $this->post('/forgot-password/recovery', [
            'email' => $user->email,
            'recovery_code' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_wrong_recovery_code(): void
    {
        $user = User::factory()->create();
        $user->setRecoveryCode();
        $user->save();

        $this->post('/forgot-password/recovery', [
            'email' => $user->email,
            'recovery_code' => 'WRON-WRONG',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertSessionHasErrors('recovery_code');
    }

    public function test_recovery_code_page_shows_status(): void
    {
        $user = User::factory()->create();
        $user->setRecoveryCode();
        $user->save();

        $this->actingAs($user)
            ->get('/pengaturan/kode-pemulihan')
            ->assertOk()
            ->assertSee('Kode aktif');
    }

    public function test_recovery_code_can_be_regenerated(): void
    {
        $user = User::factory()->create();
        $oldCode = $user->setRecoveryCode();
        $user->save();

        $response = $this->actingAs($user)
            ->post('/pengaturan/kode-pemulihan/regenerate');

        $response->assertRedirect();
        $response->assertSessionHas('recovery_code');
        $newCode = (string) session('recovery_code');

        $user->refresh();
        $this->assertNotSame($oldCode, $newCode);
        $this->assertFalse($user->verifyRecoveryCode($oldCode));
        $this->assertTrue($user->verifyRecoveryCode($newCode));
    }

    public function test_regenerate_dispatches_telegram_job_when_configured(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);
        Http::fake();

        $user = User::factory()->create([
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => 'token123',
        ]);

        $this->actingAs($user)
            ->post('/pengaturan/kode-pemulihan/regenerate')
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && $request['chat_id'] === '123456789'
                && str_contains($request['text'], 'Kode Pemulihan');
        });
    }

    public function test_regenerate_does_not_dispatch_telegram_job_when_not_configured(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/pengaturan/kode-pemulihan/regenerate')
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertNothingSent();
    }
}
