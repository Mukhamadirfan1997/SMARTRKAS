<?php

namespace App\Http\Controllers;

use App\Jobs\SendRecoveryCodeTelegramJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecoveryCodeController extends Controller
{
    public function index(): View
    {
        return view('pengaturan.recovery-code');
    }

    public function regenerate(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $code = $user->setRecoveryCode();
        $user->saveQuietly();

        $telegramNote = '';

        if ($user->hasTelegramDelivery()) {
            SendRecoveryCodeTelegramJob::dispatch($user, $code);
            $telegramNote = ' Kode juga dikirim ke Telegram Anda.';
        }

        return back()->with('recovery_code', $code)
            ->with('status', 'Kode pemulihan baru berhasil dibuat. Simpan di tempat yang aman.'.$telegramNote);
    }
}
