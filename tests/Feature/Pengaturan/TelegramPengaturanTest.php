<?php

namespace Tests\Feature\Pengaturan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPengaturanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengaturan/telegram')->assertRedirect(route('login'));
    }

    public function test_page_renders_when_bot_active_via_env(): void
    {
        config('logging.telegram_bot_token', 'envtoken');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pengaturan/telegram')
            ->assertOk()
            ->assertSee('Notifikasi Telegram')
            ->assertSee('Bot Aktif')
            ->assertSee('dari file .env');
    }

    public function test_page_renders_inactive_state_without_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pengaturan/telegram')
            ->assertOk()
            ->assertSee('Bot Tidak Aktif')
            ->assertSee('TELEGRAM_BOT_TOKEN');
    }

    public function test_chat_id_and_token_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/pengaturan/telegram', [
                'telegram_chat_id' => '123456789',
                'telegram_bot_token' => 'token123',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('123456789', $user->telegram_chat_id);
        $this->assertSame('token123', $user->telegram_bot_token);
    }

    public function test_empty_values_are_cleared_to_null(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => 'token123',
        ]);

        $this->actingAs($user)
            ->put('/pengaturan/telegram', [
                'telegram_chat_id' => '',
                'telegram_bot_token' => '',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_bot_token);
    }

    public function test_chat_id_longer_than_64_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/pengaturan/telegram', [
                'telegram_chat_id' => str_repeat('1', 65),
                'telegram_bot_token' => 'token123',
            ])
            ->assertSessionHasErrors('telegram_chat_id');
    }

    public function test_bot_token_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create(['telegram_bot_token' => 'rahasia123']);

        $this->assertArrayNotHasKey('telegram_bot_token', $user->toArray());
    }

    public function test_test_button_sends_message_when_configured(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => 'token123',
        ]);

        $this->actingAs($user)
            ->post('/pengaturan/telegram/test')
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && $request['chat_id'] === '123456789'
                && str_contains($request['text'], 'Pesan uji');
        });
    }

    public function test_test_button_shows_error_when_telegram_rejects(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response('{"ok":false,"error_code":401,"description":"Unauthorized"}', 401),
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => '123456789',
            'telegram_bot_token' => 'token123',
        ]);

        $response = $this->actingAs($user)
            ->post('/pengaturan/telegram/test')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('Unauthorized', (string) session('error'));
        $this->assertStringContainsString('Start', (string) session('error'));
    }

    public function test_test_button_errors_when_token_missing(): void
    {
        Http::fake();

        $user = User::factory()->create(['telegram_chat_id' => '123456789']);

        $this->actingAs($user)
            ->post('/pengaturan/telegram/test')
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_test_button_errors_when_chat_id_missing(): void
    {
        Http::fake();

        $user = User::factory()->create(['telegram_bot_token' => 'token123']);

        $this->actingAs($user)
            ->post('/pengaturan/telegram/test')
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_page_shows_desktop_data_dir_when_available(): void
    {
        $original = config('app.data_dir');
        config(['app.data_dir' => 'C:\\SmartRkas\\Data']);

        try {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->get('/pengaturan/telegram')
                ->assertOk()
                ->assertSee('C:\\SmartRkas\\Data')
                ->assertSee('Mode desktop');
        } finally {
            config(['app.data_dir' => $original]);
        }
    }
}
