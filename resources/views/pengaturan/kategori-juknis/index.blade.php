<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Kategori Juknis BOSP</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <div class="card lg:col-span-1">
            <div class="card-header">
                <span class="card-title">Tambah Kategori</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pengaturan.kategori-juknis.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="nama" class="form-label">Nama Kategori</label>
                        <input type="text" name="nama" id="nama" value="{{ old('nama') }}" class="form-input" placeholder="mis. Honor" required>
                        @error('nama')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="arah" class="form-label">Batas Atas / Batas Bawah</label>
                        <select name="arah" id="arah" class="form-input" required>
                            <option value="">-- Pilih Arah --</option>
                            <option value="maksimal" {{ old('arah') === 'maksimal' ? 'selected' : '' }}>Batas Maksimal (tidak boleh melebihi)</option>
                            <option value="minimal" {{ old('arah') === 'minimal' ? 'selected' : '' }}>Batas Minimal (harus mencapai)</option>
                        </select>
                        @error('arah')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="batas_persen" class="form-label">Batas (%)</label>
                        <input type="number" name="batas_persen" id="batas_persen" value="{{ old('batas_persen') }}" min="0" max="100" step="0.01" class="form-input" required>
                        @error('batas_persen')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="berlaku_untuk" class="form-label">Berlaku Untuk <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <input type="text" name="berlaku_untuk" id="berlaku_untuk" value="{{ old('berlaku_untuk') }}" class="form-input" maxlength="50" placeholder="mis. negeri / swasta">
                        @error('berlaku_untuk')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah
                    </button>
                </form>
            </div>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header">
                <span class="card-title">Daftar Kategori</span>
                <a href="{{ route('pengaturan.kategori-juknis.pemetaan') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656 0l-4-4a4 4 0 010-5.656l1.5-1.5m7.5 7.5l1.5-1.5"/></svg>
                    Pemetaan Kode Rekening
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Arah</th>
                            <th class="text-center">Batas</th>
                            <th>Berlaku Untuk</th>
                            <th class="text-center">Rekening Terkait</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kategoriJuknis as $kategori)
                            <tr>
                                <td class="font-medium text-slate-800">{{ $kategori->nama }}</td>
                                <td>
                                    @if($kategori->arah === 'minimal')
                                        <span class="badge badge-green" title="Harus mencapai batas minimal">Minimal &ge;</span>
                                    @else
                                        <span class="badge badge-red" title="Tidak boleh melebihi batas maksimal">Maksimal &le;</span>
                                    @endif
                                </td>
                                <td class="text-center font-mono">{{ number_format((float) $kategori->batas_persen, 2, ',', '.') }}%</td>
                                <td>{{ $kategori->berlaku_untuk ?? '-' }}</td>
                                <td class="text-center">{{ $kategori->kode_rekenings_count }}</td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('pengaturan.kategori-juknis.edit', $kategori) }}" class="btn btn-secondary btn-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit
                                        </a>
                                        <form action="{{ route('pengaturan.kategori-juknis.destroy', $kategori) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus kategori ini? Pemetaan kode rekening terkait ikut terhapus.')">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-slate-500 py-8">Belum ada kategori Juknis BOSP.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert-info flex items-start gap-2">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Kategori di sini dipakai untuk memantau kepatuhan Juknis BOSP di halaman Monitoring. Tandai kode rekening yang termasuk tiap kategori lewat tombol <strong>Pemetaan Kode Rekening</strong>.</span>
    </div>
</x-app-layout>
