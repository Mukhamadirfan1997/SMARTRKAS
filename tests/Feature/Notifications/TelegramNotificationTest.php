<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendTelegramNotificationJob;
use App\Listeners\NotifyBackupTelegram;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    public function test_job_posts_message_to_telegram_api(): void
    {
        config([
            'logging.telegram_bot_token' => 'token123',
            'logging.telegram_chat_id' => 'chat123',
        ]);
        Http::fake();

        SendTelegramNotificationJob::dispatchSync('ERROR', 'Pesan uji');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && $request['chat_id'] === 'chat123'
                && str_contains($request['text'], 'Pesan uji')
                && $request['parse_mode'] === 'HTML';
        });
    }

    public function test_job_skips_when_not_configured(): void
    {
        config([
            'logging.telegram_bot_token' => null,
            'logging.telegram_chat_id' => null,
        ]);
        Http::fake();

        SendTelegramNotificationJob::dispatchSync('ERROR', 'Pesan uji');

        Http::assertNothingSent();
    }

    public function test_listener_dispatches_job_on_backup_success(): void
    {
        config([
            'logging.telegram_bot_token' => 'token123',
            'logging.telegram_chat_id' => 'chat123',
        ]);
        Http::fake();

        $destination = $this->createMock(BackupDestination::class);
        $destination->method('diskName')->willReturn('local');
        $destination->method('backupName')->willReturn('backup-2026');

        (new NotifyBackupTelegram)->handle(new BackupWasSuccessful($destination));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && $request['chat_id'] === 'chat123'
                && str_contains($request['text'], 'Backup berhasil')
                && str_contains($request['text'], 'local');
        });
    }

    public function test_listener_dispatches_job_on_backup_failure(): void
    {
        config([
            'logging.telegram_bot_token' => 'token123',
            'logging.telegram_chat_id' => 'chat123',
        ]);
        Http::fake();

        (new NotifyBackupTelegram)->handle(new BackupHasFailed(new \RuntimeException('Disk penuh')));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && str_contains($request['text'], 'Disk penuh');
        });
    }

    public function test_telegram_log_channel_dispatches_job_for_error_logs(): void
    {
        config([
            'logging.telegram_bot_token' => 'token123',
            'logging.telegram_chat_id' => 'chat123',
        ]);
        Http::fake();

        Log::channel('telegram')->error('Terjadi kegagalan aplikasi');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottoken123/sendMessage'
                && str_contains($request['text'], 'Terjadi kegagalan aplikasi');
        });
    }

    public function test_telegram_log_channel_ignores_info_logs(): void
    {
        config([
            'logging.telegram_bot_token' => 'token123',
            'logging.telegram_chat_id' => 'chat123',
        ]);
        Http::fake();

        Log::channel('telegram')->info('Bukan error');

        Http::assertNothingSent();
    }
}
