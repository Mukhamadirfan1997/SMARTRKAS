<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Masuk ke Akun</h1>
        <p class="text-sm text-slate-500 mt-1">Silakan masuk untuk melanjutkan</p>
    </div>

    @if(session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="loginForm">
        @csrf

        <div class="mb-5">
            <label class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="form-input">
            @if($errors->get('email'))
                <p class="text-red-500 text-xs mt-2">{{ implode(', ', $errors->get('email')) }}</p>
            @endif
        </div>

        <div class="mb-5">
            <label class="form-label">Password</label>
            <div class="relative">
                <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input pr-10">
                <button type="button" onclick="togglePassword('password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="eye-open w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg class="eye-closed w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
            </div>
            @if($errors->get('password'))
                <p class="text-red-500 text-xs mt-2">{{ implode(', ', $errors->get('password')) }}</p>
            @endif
        </div>

        <div class="flex items-center justify-between mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input id="remember_me" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" name="remember">
                <span class="text-sm text-slate-600">Ingat saya</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-blue-600 hover:text-blue-800 font-medium" href="{{ route('password.request') }}">
                    Lupa password?
                </a>
            @endif
        </div>

        <button type="submit" id="loginBtn" class="btn-primary w-full justify-center">
            <span class="btn-text">Masuk</span>
            <svg class="btn-spinner hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </button>
    </form>

    <div class="mt-6 text-center space-y-3">
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <p class="text-xs text-amber-700 font-medium leading-relaxed">Catatan: Aplikasi ini bersifat sebagai alat bantu monitoring. Input dan pelaporan resmi tetap dilakukan melalui ARKAS.</p>
        </div>
        <p class="text-xs text-slate-400">&copy; {{ date('Y') }} SmartRKAS v1.0</p>
    </div>

    <script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const eyeOpen = btn.querySelector('.eye-open');
        const eyeClosed = btn.querySelector('.eye-closed');
        if (input.type === 'password') {
            input.type = 'text';
            eyeOpen.classList.add('hidden');
            eyeClosed.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeOpen.classList.remove('hidden');
            eyeClosed.classList.add('hidden');
        }
    }
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.querySelector('.btn-text').textContent = 'Masuk...';
        btn.querySelector('.btn-spinner').classList.remove('hidden');
    });
    </script>
</x-guest-layout>
