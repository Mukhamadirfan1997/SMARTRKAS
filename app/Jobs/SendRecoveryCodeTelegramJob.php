<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendRecoveryCodeTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [2, 10];

    public User $user;
    public string $code;

    public function __construct(User $user, string $code)
    {
        $this->user = $user;
        $this->code = $code;
    }

    public function handle(): void
    {
        $botToken = $this->user->telegramBotToken();
        $chatId = $this->user->telegramChatId();

        if ($botToken === null || $chatId === null) {
            return;
        }

        Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $this->formatMessage(),
            'parse_mode' => 'HTML',
        ]);
    }

    protected function formatMessage(): string
    {
        return implode("\n", [
            '<b>🔐 Kode Pemulihan SmartRKAS</b>',
            '',
            "Kode: <b>{$this->code}</b>",
            "Akun: {$this->user->email}",
            'Dibuat: '.now()->format('Y-m-d H:i:s'),
            '',
            'Gunakan kode ini di menu "Lupa Password" → Opsi 1.',
            'Kode hanya berlaku sekali dan hanya ditampilkan sekali.',
        ]);
    }
}
