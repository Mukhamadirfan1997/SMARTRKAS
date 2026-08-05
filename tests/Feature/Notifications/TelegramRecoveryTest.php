<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendRecoveryCodeTelegramJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_recovery_code_to_user_chat(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => 'chat123',
            'telegram_bot_token' => 'token123',
        ]);
        Http::fake();

        SendRecoveryCodeTelegramJob::dispatchSync($user, 'ABCD-EFGH');

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && $request['chat_id'] === 'chat123'
                && str_contains($request['text'], 'ABCD-EFGH')
                && str_contains($request['text'], $user->email)
                && $request['parse_mode'] === 'HTML';
        });
    }

    public function test_job_falls_back_to_env_token(): void
    {
        config([
            'logging.telegram_bot_token' => 'envtoken',
            'logging.telegram_chat_id' => null,
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => 'chat123',
            'telegram_bot_token' => null,
        ]);
        Http::fake();

        SendRecoveryCodeTelegramJob::dispatchSync($user, 'ABCD-EFGH');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botenvtoken/sendMessage');
    }

    public function test_job_skips_when_chat_id_missing(): void
    {
        config('logging.telegram_bot_token', 'envtoken');

        $user = User::factory()->create(['telegram_bot_token' => 'token123']);
        Http::fake();

        SendRecoveryCodeTelegramJob::dispatchSync($user, 'ABCD-EFGH');

        Http::assertNothingSent();
    }

    public function test_job_skips_when_token_missing(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);

        $user = User::factory()->create(['telegram_chat_id' => 'chat123']);
        Http::fake();

        SendRecoveryCodeTelegramJob::dispatchSync($user, 'ABCD-EFGH');

        Http::assertNothingSent();
    }

    public function test_notification_job_uses_provided_bot_token_and_chat_id(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);
        Http::fake();

        SendTelegramNotificationJob::dispatchSync(
            'INFO',
            'Pesan uji',
            [],
            [],
            'custom-token',
            'custom-chat',
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/botcustom-token/sendMessage'
                && $request['chat_id'] === 'custom-chat'
                && str_contains($request['text'], 'Pesan uji');
        });
    }
}
