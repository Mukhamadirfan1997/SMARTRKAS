<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Console\Command;

class TelegramKwitansiReminder extends Command
{
    protected $signature = 'telegram:kwitansi-reminder';

    protected $description = 'Kirim pengingat via Telegram jika ada transaksi pengeluaran yang belum dicetak kwitansinya';

    public function handle(): int
    {
        $tahun = TahunAnggaran::getActive();
        if ($tahun === null) {
            $this->warn('Tidak ada tahun anggaran aktif. Melewati pengingat.');
            return self::SUCCESS;
        }

        $count = TransaksiBku::where('tahun_anggaran_id', $tahun->id)
            ->where('jenis', 'pengeluaran')
            ->whereDoesntHave('kwitansi')
            ->count();

        if ($count === 0) {
            $this->info('Semua transaksi sudah dicetak kwitansinya. Tidak ada pengingat yang dikirim.');
            return self::SUCCESS;
        }

        $users = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter->hasTelegramDelivery();
        if ($users->isEmpty()) {
            $this->warn('Tidak ada user dengan Telegram aktif. Pengingat tidak dikirim.');
            return self::SUCCESS;
        }

        $message = "📋 <b>Pengingat Kwitansi</b>\n\n"
            . "Masih ada <b>{$count}</b> transaksi pengeluaran tahun anggaran {$tahun->tahun} "
            . "yang belum dicetak kwitansinya.\n\n"
            . "Silakan cetak kwitansi di menu <b>BKU → Cetak Kwitansi</b>.";

        foreach ($users as $user) {
            SendTelegramNotificationJob::dispatch(
                level: 'INFO',
                message: $message,
                botToken: $user->telegramBotToken(),
                chatId: $user->telegramChatId(),
            );
        }

        $this->info("Pengingat dikirim ke {$users->count()} user ({$count} transaksi belum dicetak).");
        return self::SUCCESS;
    }
}
