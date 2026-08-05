<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-800">Reset Password</h2>
        <p class="text-sm text-slate-500 mt-2">Masukkan email dan password baru Anda</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-5">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" class="form-input" />
            @error('email')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-5">
            <label class="form-label">Password Baru</label>
            <input type="password" name="password" required autocomplete="new-password" class="form-input" />
            @error('password')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="form-label">Konfirmasi Password Baru</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="form-input" />
            @error('password_confirmation')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="btn-primary">
                Reset Password
            </button>
        </div>
    </form>
</x-guest-layout>
