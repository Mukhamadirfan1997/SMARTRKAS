<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Kode Pemulihan</div>
    </x-slot>

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
                <strong>Kode pemulihan baru:</strong>
                <div class="mt-2 font-mono text-2xl tracking-[0.3em] font-bold text-amber-900">{{ session('recovery_code') }}</div>
                <p class="mt-2 text-xs">Kode hanya ditampilkan sekali. Simpan di tempat aman — dipakai untuk reset password secara offline tanpa email.</p>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <span class="card-title">Kode Pemulihan Akun</span>
        </div>
        <div class="card-body space-y-4">
            <p class="text-sm text-slate-600 leading-relaxed">
                Kode pemulihan digunakan untuk <strong>mereset password tanpa email</strong> (cocok untuk mode desktop).
                Di halaman login, pilih <em>"Lupa password?"</em> → <em>Opsi 1 — Kode Pemulihan</em>.
            </p>
            <p class="text-sm text-slate-600 leading-relaxed">
                Jika sudah mengatur <a href="{{ route('pengaturan.telegram.index') }}" class="text-indigo-600 hover:underline">Notifikasi Telegram</a>,
                salinan kode juga otomatis dikirim ke Telegram Anda setiap kode baru dibuat.
            </p>

            @if(auth()->user()->hasRecoveryCode())
                <div class="flex items-center gap-2 text-sm">
                    <span class="badge badge-green">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Kode aktif
                    </span>
                    <span class="text-xs text-slate-500">
                        Dibuat {{ auth()->user()->recovery_code_generated_at?->translatedFormat('d/m/Y H:i') }}
                    </span>
                </div>
                <p class="text-xs text-slate-500">Kode lama tidak dapat ditampilkan kembali (tersimpan sebagai hash). Jika lupa, buat kode baru di bawah.</p>
            @else
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <p class="text-xs text-amber-700">Anda belum memiliki kode pemulihan. Buat sekarang agar bisa reset password secara offline bila lupa.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('pengaturan.recovery-code.regenerate') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5m11 11v-5h-5M4.58 16A8 8 0 1116 19.42M4.58 8A8 8 0 1116 4.58"/></svg>
                    {{ auth()->user()->hasRecoveryCode() ? 'Buat Kode Baru' : 'Buat Kode Pemulihan' }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
