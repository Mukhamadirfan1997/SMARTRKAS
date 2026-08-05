<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Selamat Datang di SmartRKAS</h1>
        <p class="text-sm text-slate-500 mt-1">Pilih cara memulai aplikasi</p>
    </div>

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @if(session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    @if(session('recovery_code'))
        <div class="alert-warning mb-6">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong>Kode pemulihan Anda:</strong>
                <div class="mt-2 font-mono text-2xl tracking-[0.3em] font-bold text-amber-900">{{ session('recovery_code') }}</div>
                <p class="mt-2 text-xs">Simpan di tempat aman. Kode ini digunakan jika Anda lupa password (reset offline tanpa email).</p>
            </div>
        </div>
    @endif

    <div class="space-y-5">

        <div class="card">
            <div class="card-header">
                <span class="card-title">1. Mulai dari Awal</span>
            </div>
            <div class="card-body space-y-4">
                <p class="text-sm text-slate-600">Gunakan database baru dengan akun admin bawaan:</p>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Email</span>
                        <code class="font-medium text-slate-800">admin@sekolah.test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Password</span>
                        <code class="font-medium text-slate-800">password</code>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <p class="text-xs text-amber-700 leading-relaxed">Segera ganti password setelah masuk dan buat <strong>kode pemulihan</strong> bila belum ada.</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if($defaultUser !== null && $defaultUser->hasRecoveryCode())
                        <span class="inline-flex items-center gap-1.5 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Kode pemulihan sudah dibuat.
                        </span>
                    @else
                        <form method="POST" action="{{ route('onboarding.recovery-code') }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                Buat Kode Pemulihan
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('login', ['first' => 1]) }}" class="btn btn-primary">
                        Masuk dengan Akun Default
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">2. Pulihkan dari Backup</span>
            </div>
            <div class="card-body space-y-4">
                <p class="text-sm text-slate-600 leading-relaxed">
                    Pulihkan database dari file backup <code class="text-xs">.zip</code> yang sebelumnya diunduh dari menu
                    <strong>Pengaturan → Backup &amp; Pemulihan</strong>. Setelah berhasil, Anda masuk menggunakan akun dari data backup tersebut.
                </p>
                <a href="{{ route('restore.create') }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Pulihkan dari Backup
                </a>
            </div>
        </div>

    </div>
</x-guest-layout>
