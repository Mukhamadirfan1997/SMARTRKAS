<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Master Kode Rekening</div>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Tambah Kode Rekening</span>
            <a href="{{ route('master-kode-rekening.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('master-kode-rekening.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="kode" class="form-label">Kode</label>
                    <input type="text" name="kode" id="kode" value="{{ old('kode') }}" class="form-input" required>
                    @error('kode')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="nama" class="form-label">Nama</label>
                    <input type="text" name="nama" id="nama" value="{{ old('nama') }}" class="form-input" required>
                    @error('nama')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="jenis_belanja_id" class="form-label">Jenis Belanja</label>
                    <select name="jenis_belanja_id" id="jenis_belanja_id" class="form-select" required>
                        <option value="">Pilih Jenis Belanja</option>
                        @foreach($jenisBelanjas as $jenis)
                            <option value="{{ $jenis->id }}" {{ old('jenis_belanja_id') == $jenis->id ? 'selected' : '' }}>{{ $jenis->nama }}</option>
                        @endforeach
                    </select>
                    @error('jenis_belanja_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('master-kode-rekening.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
