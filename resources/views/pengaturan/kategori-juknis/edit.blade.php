<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Edit Kategori Juknis</div>
    </x-slot>

    <div class="card max-w-2xl mx-auto">
        <div class="card-header">
            <span class="card-title">Form Edit Kategori</span>
            <a href="{{ route('pengaturan.kategori-juknis.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengaturan.kategori-juknis.update', $kategori) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="nama" class="form-label">Nama Kategori</label>
                    <input type="text" name="nama" id="nama" value="{{ old('nama', $kategori->nama) }}" class="form-input" required>
                    @error('nama')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="arah" class="form-label">Batas Atas / Batas Bawah</label>
                    <select name="arah" id="arah" class="form-input" required>
                        <option value="">-- Pilih Arah --</option>
                        <option value="maksimal" {{ old('arah', $kategori->arah) === 'maksimal' ? 'selected' : '' }}>Batas Maksimal (tidak boleh melebihi)</option>
                        <option value="minimal" {{ old('arah', $kategori->arah) === 'minimal' ? 'selected' : '' }}>Batas Minimal (harus mencapai)</option>
                    </select>
                    @error('arah')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="batas_persen" class="form-label">Batas (%)</label>
                    <input type="number" name="batas_persen" id="batas_persen" value="{{ old('batas_persen', $kategori->batas_persen) }}" min="0" max="100" step="0.01" class="form-input" required>
                    @error('batas_persen')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="berlaku_untuk" class="form-label">Berlaku Untuk <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <input type="text" name="berlaku_untuk" id="berlaku_untuk" value="{{ old('berlaku_untuk', $kategori->berlaku_untuk) }}" class="form-input" maxlength="50" placeholder="mis. negeri / swasta">
                    @error('berlaku_untuk')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('pengaturan.kategori-juknis.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
