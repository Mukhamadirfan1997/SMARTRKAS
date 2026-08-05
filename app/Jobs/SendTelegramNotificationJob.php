<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [2, 10];

    public string $level;
    public string $message;
    /** @var array<string, mixed> */
    public array $context;
    /** @var array<string, mixed> */
    public array $extra;
    public ?string $botToken = null;
    public ?string $chatId = null;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $level,
        string $message,
        array $context = [],
        array $extra = [],
        ?string $botToken = null,
        ?string $chatId = null,
    ) {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->extra = $extra;
        $this->botToken = $botToken;
        $this->chatId = $chatId;
    }

    public function handle(): void
    {
        $lock = Cache::lock('telegram-notification', 5);

        if (!$lock->get()) {
            return;
        }

        try {
            $botToken = $this->botToken ?? config('logging.telegram_bot_token');
            $chatId = $this->chatId ?? config('logging.telegram_chat_id');

            if (empty($botToken) || empty($chatId)) {
                return;
            }

            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $this->formatMessage(),
                'parse_mode' => 'HTML',
            ]);
        } finally {
            $lock->release();
        }
    }

    protected function formatMessage(): string
    {
        $emoji = match (strtoupper($this->level)) {
            'EMERGENCY' => '💀',
            'ALERT'     => '🚨',
            'CRITICAL'  => '🔴',
            'ERROR'     => '❌',
            'WARNING'   => '⚠️',
            'NOTICE'    => '📢',
            'INFO'      => 'ℹ️',
            'DEBUG'     => '🐛',
            default     => '❓',
        };

        $appEnv = config('app.env', 'local');
        $now = now()->format('Y-m-d H:i:s');
        $url = $this->extra['url'] ?? 'N/A';
        $user = $this->extra['user_email'] ?? 'Guest';

        return implode("\n", [
            "<b>{$emoji} [{$this->level}]</b>",
            "<b>Waktu:</b> {$now}",
            "<b>Lingkungan:</b> {$appEnv}",
            "<b>Pesan:</b> {$this->message}",
            "<b>URL:</b> {$url}",
            "<b>User:</b> {$user}",
        ]);
    }
}
