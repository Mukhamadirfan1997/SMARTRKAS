<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class PasswordRecoveryController extends Controller
{
    /**
     * Reset password secara offline memakai kode pemulihan
     * (tanpa mail server, cocok untuk mode desktop).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'recovery_code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->string('email'))->first();

        if ($user === null || ! $user->verifyRecoveryCode($request->string('recovery_code'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['recovery_code' => 'Kode pemulihan tidak sesuai.']);
        }

        $user->forceFill([
            'password' => $request->string('password'),
            'remember_token' => null,
        ])->saveQuietly();

        return redirect()->route('login')
            ->with('status', 'Password berhasil direset. Silakan masuk menggunakan password baru.');
    }
}
