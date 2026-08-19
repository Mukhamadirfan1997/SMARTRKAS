<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\RealisasiQuery;
use Illuminate\Console\Command;

class TelegramRealisasiWarning extends Command
{
    protected $signature = 'telegram:realisasi-warning {--threshold=50 : Persentase minimum realisasi (default 50%)} {--month=10 : Bulan mulai peringatan (default 10 = Oktober)}';

    protected $description = 'Kirim peringatan via Telegram jika realisasi anggaran masih rendah menjelang akhir tahun';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $warnMonth = (int) $this->option('month');
        $currentMonth = (int) date('n');

        if ($currentMonth < $warnMonth) {
            $this->info("Belum memasuki bulan peringatan (bulan {$warnMonth}). Melewati.");
            return self::SUCCESS;
        }

        $tahun = TahunAnggaran::getActive();
        if ($tahun === null) {
            $this->warn('Tidak ada tahun anggaran aktif. Melewati.');
            return self::SUCCESS;
        }

        $itemIds = RkasItem::where('tahun_anggaran_id', $tahun->id)->pluck('id');
        if ($itemIds->isEmpty()) {
            $this->info('Tidak ada item RKAS. Melewati.');
            return self::SUCCESS;
        }

        $totalRencana = RkasItem::where('tahun_anggaran_id', $tahun->id)->sum('jumlah');
        $totalRealisasi = RealisasiQuery::base()
            ->whereIn('rb.rkas_item_id', $itemIds)
            ->sum('rb.jumlah');

        if ($totalRencana <= 0) {
            $this->info('Total rencana 0. Melewati.');
            return self::SUCCESS;
        }

        $persentase = round(($totalRealisasi / $totalRencana) * 100, 1);

        if ($persentase >= $threshold) {
            $this->info("Realisasi {$persentase}% sudah mencapai threshold {$threshold}%. Tidak ada peringatan.");
            return self::SUCCESS;
        }

        $users = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter->hasTelegramDelivery();
        if ($users->isEmpty()) {
            $this->warn('Tidak ada user dengan Telegram aktif. Peringatan tidak dikirim.');
            return self::SUCCESS;
        }

        $sisa = $totalRencana - $totalRealisasi;
        $sisaFormatted = number_format($sisa, 0, ',', '.');
        $rencanaFormatted = number_format($totalRencana, 0, ',', '.');
        $realisasiFormatted = number_format($totalRealisasi, 0, ',', '.');
        $sisaBulan = 12 - $currentMonth;

        $message = "⚠️ <b>Peringatan Realisasi Anggaran</b>\n\n"
            . "Realisasi tahun anggaran {$tahun->tahun} masih <b>{$persentase}%</b> "
            . "(Rp {$realisasiFormatted} dari Rp {$rencanaFormatted}).\n"
            . "Sisa anggaran: <b>Rp {$sisaFormatted}</b>.\n\n"
            . "⏳ Tersisa <b>{$sisaBulan} bulan</b> lagi sampai akhir tahun anggaran.\n\n"
            . "Mohon percepat realisasi anggaran agar tidak ada saldo yang menumpuk di akhir tahun.";

        foreach ($users as $user) {
            SendTelegramNotificationJob::dispatch(
                level: 'WARNING',
                message: $message,
                botToken: $user->telegramBotToken(),
                chatId: $user->telegramChatId(),
            );
        }

        $this->info("Peringatan dikirim ke {$users->count()} user (realisasi {$persentase}% < threshold {$threshold}%).");
        return self::SUCCESS;
    }
}
