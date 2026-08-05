<?php

namespace App\Http\Controllers;

use App\Jobs\SendRecoveryCodeTelegramJob;
use App\Models\User;
use App\Support\AppState;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! AppState::isFirstRun()) {
            return redirect()->route('login');
        }

        return view('auth.onboarding', [
            'defaultUser' => User::orderBy('id')->first(),
        ]);
    }

    /**
     * Generate kode pemulihan untuk akun admin default saat onboarding
     * (belum ada user yang pernah login).
     */
    public function generateRecoveryCode(): RedirectResponse
    {
        if (! AppState::isFirstRun()) {
            return redirect()->route('login');
        }

        $user = User::orderBy('id')->first();

        if ($user === null) {
            return back()->with('error', 'Tidak ditemukan akun pengguna. Silakan login terlebih dahulu.');
        }

        $code = $user->setRecoveryCode();
        $user->saveQuietly();

        $telegramNote = '';

        if ($user->hasTelegramDelivery()) {
            SendRecoveryCodeTelegramJob::dispatch($user, $code);
            $telegramNote = ' Kode juga dikirim ke Telegram Anda.';
        }

        return back()->with('recovery_code', $code)->with('status', 'Kode pemulihan berhasil dibuat.'.$telegramNote);
    }
}
