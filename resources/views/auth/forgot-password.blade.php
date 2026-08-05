<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-800">Lupa Password?</h2>
        <p class="text-sm text-slate-500 mt-2">Reset password memakai email atau kode pemulihan</p>
    </div>

    @if (session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">

        <div>
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Opsi 1 — Kode Pemulihan (disarankan untuk desktop)</h3>
            <form method="POST" action="{{ route('password.recovery') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="form-input" />
                    @error('email')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">Kode Pemulihan</label>
                    <input type="text" name="recovery_code" value="{{ old('recovery_code') }}" required placeholder="XXXX-XXXX" class="form-input font-mono" />
                    @error('recovery_code')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" required autocomplete="new-password" class="form-input" />
                    @error('password')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="form-input" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        Reset dengan Kode Pemulihan
                    </button>
                </div>
            </form>
        </div>

        <div class="flex items-center gap-3 text-xs text-slate-400">
            <span class="h-px flex-1 bg-slate-200"></span>
            atau
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Opsi 2 — Tautan Email</h3>
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="form-input" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-secondary">
                        Kirim Tautan Reset Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-guest-layout>
