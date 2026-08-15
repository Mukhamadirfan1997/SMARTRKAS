<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Edit Item RKAS</div>
    </x-slot>

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Edit</span>
            <a href="{{ route('rkas.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('rkas.update', $rkasItem) }}" id="form-rkas-edit">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="form-label">No. Urut</label>
                        <input type="number" name="no_urut" value="{{ old('no_urut', $rkasItem->no_urut) }}" class="form-input" required>
                        @error('no_urut')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Uraian</label>
                        <input type="text" name="uraian" value="{{ old('uraian', $rkasItem->uraian) }}" class="form-input" required>
                        @error('uraian')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Program</label>
                        @include('transaksi-bku._search-picker', [
                            'spPrefix' => 'program',
                            'spLabel' => '',
                            'spLabelLower' => 'program',
                            'spRequired' => true,
                            'spStatusHint' => 'Pilih program...',
                            'spPlaceholder' => 'Cari program (kode / nama)...',
                            'spInitial' => (string) old('program_id', $rkasItem->program_id ?? ''),
                            'spError' => 'program_id',
                            'spAutoSubmit' => false,
                            'spOptions' => $masterPrograms->map(fn ($p) => ['id' => (string) $p->id, 'text' => $p->kode . ' - ' . $p->nama])->values()->all(),
                        ])
                    </div>

                    <div>
                        <label class="form-label">Kode Rekening</label>
                        @include('transaksi-bku._search-picker', [
                            'spPrefix' => 'kode_rekening',
                            'spLabel' => '',
                            'spLabelLower' => 'kode rekening',
                            'spRequired' => true,
                            'spStatusHint' => 'Pilih kode rekening...',
                            'spPlaceholder' => 'Cari rekening (kode / nama)...',
                            'spInitial' => (string) old('kode_rekening_id', $rkasItem->kode_rekening_id ?? ''),
                            'spError' => 'kode_rekening_id',
                            'spAutoSubmit' => false,
                            'spOptions' => $masterKodeRekenings->map(fn ($r) => ['id' => (string) $r->id, 'text' => $r->kode . ' - ' . $r->nama])->values()->all(),
                        ])
                    </div>

                    <div>
                        <label class="form-label">Sumber Dana</label>
                        <select name="sumber_dana_id" class="form-select">
                            <option value="">-- Pilih Sumber Dana --</option>
                            @foreach($sumberDanas as $sd)
                                <option value="{{ $sd->id }}" {{ old('sumber_dana_id', $rkasItem->sumber_dana_id) == $sd->id ? 'selected' : '' }}>
                                    {{ $sd->kode }} - {{ $sd->nama }}
                                </option>
                            @endforeach
                        </select>
                        @error('sumber_dana_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Volume</label>
                        <input type="text" name="volume" value="{{ old('volume', $rkasItem->volume) }}" class="form-input" inputmode="decimal" autocomplete="off">
                        @error('volume')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Satuan</label>
                        <input type="text" name="satuan" value="{{ old('satuan', $rkasItem->satuan) }}" class="form-input">
                        @error('satuan')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Tarif</label>
                        <input type="text" name="tarif" value="{{ old('tarif', $rkasItem->tarif) }}" class="form-input" inputmode="decimal" autocomplete="off" placeholder="Contoh: 1.500.000">
                        @error('tarif')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Jumlah</label>
                        <input type="text" name="jumlah" value="{{ old('jumlah', $rkasItem->jumlah) }}" class="form-input" inputmode="decimal" autocomplete="off" placeholder="Contoh: 1.500.000" required>
                        @error('jumlah')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                    <a href="{{ route('rkas.index') }}" class="btn btn-secondary">
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

    <script>
        document.getElementById('form-rkas-edit').addEventListener('submit', function() {
            var normal = function (v) {
                v = String(v).replace(/\s+/g, '');
                if (/^[+-]?\d+(\.\d{1,2})?$/.test(v)) return v;
                return v.replace(/\./g, '').replace(/,/g, '.');
            };
            var decimal = function (v) { return String(v).replace(/\s+/g, '').replace(/,/g, '.'); };
            var tarif = document.querySelector('input[name="tarif"]');
            var jumlah = document.querySelector('input[name="jumlah"]');
            var volume = document.querySelector('input[name="volume"]');
            if (tarif && tarif.value) tarif.value = normal(tarif.value);
            if (jumlah && jumlah.value) jumlah.value = normal(jumlah.value);
            if (volume && volume.value) volume.value = decimal(volume.value);
        });
    </script>
</x-app-layout>
