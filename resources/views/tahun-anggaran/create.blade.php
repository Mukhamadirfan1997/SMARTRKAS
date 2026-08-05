<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Tahun Anggaran</div>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Tambah Tahun Anggaran</span>
            <a href="{{ route('tahun-anggaran.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form action="{{ route('tahun-anggaran.store') }}" method="POST">
                @csrf
                <div class="mb-6">
                    <label class="form-label" for="tahun">Tahun</label>
                    <input class="form-input" id="tahun" name="tahun" type="number" min="2020" max="2099" required>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('tahun-anggaran.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button class="btn-primary" type="submit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
