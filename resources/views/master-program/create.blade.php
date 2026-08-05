<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Master Program</div>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Tambah Master Program</span>
            <a href="{{ route('master-program.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('master-program.store') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="form-label">Kode</label>
                        <input type="text" name="kode" value="{{ old('kode') }}" class="form-input" required>
                        @error('kode')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Nama</label>
                        <input type="text" name="nama" value="{{ old('nama') }}" class="form-input" required>
                        @error('nama')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Program (Opsional)</label>
                        <input type="text" name="program" value="{{ old('program') }}" class="form-input">
                        @error('program')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Sub Program (Opsional)</label>
                        <input type="text" name="sub_program" value="{{ old('sub_program') }}" class="form-input">
                        @error('sub_program')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Parent (Opsional)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">Pilih Parent</option>
                            @foreach($parentPrograms as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->kode }} - {{ $parent->nama }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Level</label>
                        <input type="number" name="level" value="{{ old('level', 1) }}" class="form-input" required>
                        @error('level')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                    <a href="{{ route('master-program.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
