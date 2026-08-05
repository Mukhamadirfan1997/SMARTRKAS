<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Edit Jenis Belanja</div>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Edit Jenis Belanja</span>
            <a href="{{ route('jenis-belanja.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('jenis-belanja.update', $jenisBelanja) }}">
                @csrf
                @method('PUT')

                <div class="mb-6">
                    <label for="nama" class="form-label">Nama</label>
                    <input type="text" name="nama" id="nama" value="{{ old('nama', $jenisBelanja->nama) }}" class="form-input" required>
                    @error('nama')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('jenis-belanja.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
