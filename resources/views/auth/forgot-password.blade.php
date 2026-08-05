<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-800">Lupa Password?</h2>
        <p class="text-sm text-slate-500 mt-2">Tidak masalah. Cukup beritahu kami alamat email Anda dan kami akan mengirimkan tautan reset password yang memungkinkan Anda memilih password baru.</p>
    </div>

    @if (session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="form-input" />
            @error('email')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="btn-primary">
                Kirim Tautan Reset Password
            </button>
        </div>
    </form>
</x-guest-layout>
