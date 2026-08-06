<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramPengaturanController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('pengaturan.telegram', [
            'user' => $user,
            'botToken' => $user->telegramBotToken(),
            'botSource' => $this->botSource($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $chatId = $this->emptyToNull($data['telegram_chat_id'] ?? null);
        $token = $this->emptyToNull($data['telegram_bot_token'] ?? null);

        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_bot_token' => $token,
        ])->saveQuietly();

        AuditLog::record('telegram_pengaturan', 'update', [
            'telegram_chat_id' => $chatId,
            'telegram_bot_token_set' => $token !== null,
        ], null, $user->id);

        return back()->with('status', 'Pengaturan Telegram berhasil disimpan.');
    }

    public function test(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->telegramBotToken() === null) {
            return back()->with('error', 'Bot Telegram belum dikonfigurasi. Isi Token Bot lalu Simpan, atau set TELEGRAM_BOT_TOKEN di file .env.');
        }

        if ($user->telegramChatId() === null) {
            return back()->with('error', 'ID Telegram belum diisi. Isi ID Telegram lalu Simpan.');
        }

        $job = new SendTelegramNotificationJob(
            'INFO',
            'Pesan uji dari SmartRKAS — notifikasi Telegram berfungsi.',
            [],
            [],
            $user->telegramBotToken(),
            $user->telegramChatId(),
        );

        try {
            $response = $job->send();
        } catch (\Throwable $e) {
            AuditLog::record('telegram_pengaturan', 'test', [
                'chat_id' => $user->telegramChatId(),
                'success' => false,
                'error' => $e->getMessage(),
            ], null, $user->id);

            return back()->with('error', 'Pesan uji GAGAL dikirim: '.$e->getMessage().'. Periksa koneksi internet dan pengaturan bot.');
        }

        if ($response->successful()) {
            AuditLog::record('telegram_pengaturan', 'test', [
                'chat_id' => $user->telegramChatId(),
                'success' => true,
            ], null, $user->id);

            return back()->with('status', 'Pesan uji berhasil dikirim ke Telegram Anda. Buka chat bot untuk memastikan pesannya sampai.');
        }

        $description = is_string($response->json('description'))
            ? $response->json('description')
            : 'HTTP '.$response->status();

        AuditLog::record('telegram_pengaturan', 'test', [
            'chat_id' => $user->telegramChatId(),
            'success' => false,
            'error' => $description,
        ], null, $user->id);

        return back()->with('error', 'Pesan uji GAGAL dikirim: '.$description.'. Pastikan token benar (dari @BotFather), ID benar (dari @userinfobot), dan Anda sudah menekan Start pada bot.');
    }

    private function botSource(User $user): ?string
    {
        if (is_string($user->telegram_bot_token) && $user->telegram_bot_token !== '') {
            return 'db';
        }

        $env = config('logging.telegram_bot_token');

        return is_string($env) && $env !== '' ? 'env' : null;
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
