<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Restore Berhasil</h1>
        <p class="text-sm text-slate-500 mt-2">Database berhasil dipulihkan dari backup</p>
    </div>

    <div class="alert-success mb-6">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <div>
            Data berhasil dipulihkan. Silakan masuk menggunakan <strong>email dan password dari data backup</strong> tersebut.
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-6">
        <p class="text-xs text-amber-700 leading-relaxed">
            Lupa password dari data backup? Gunakan <strong>kode pemulihan</strong> milik akun tersebut lewat menu
            "Lupa password?" di halaman login.
        </p>
    </div>

    <a href="{{ route('login') }}" class="btn btn-primary w-full justify-center">
        Masuk ke Aplikasi
    </a>
</x-guest-layout>
