<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Pulihkan dari Backup</h1>
        <p class="text-sm text-slate-500 mt-2">Unggah file backup (.zip) untuk memulihkan data</p>
    </div>

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="alert-warning mb-6">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <strong>Perhatian:</strong> proses restore akan menimpa database saat ini. Pastikan Anda memiliki salinan backup terbaru sebelum melanjutkan.
        </div>
    </div>

    <form method="POST" action="{{ route('restore.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="mb-6">
            <label class="form-label">File Backup (.zip)</label>
            <input type="file" name="file" accept=".zip,application/zip" required class="form-input" />
            @error('file')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('onboarding') }}" class="text-sm text-slate-500 hover:text-slate-700 hover:underline">Kembali</a>
            <button type="submit" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Pulihkan Database
            </button>
        </div>
    </form>
</x-guest-layout>
