<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Transaksi BKU</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="w-full">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Form Transaksi Baru</span>
                <a href="{{ route('transaksi-bku.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transaksi-bku.store') }}">
                    @csrf

                    {{-- Section 1: Info Dasar --}}
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Informasi Transaksi</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" class="form-input" required>
                            @error('tanggal')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="jenis" class="form-label">Jenis Transaksi</label>
                            <select name="jenis" id="jenis" class="form-select" required>
                                <option value="penerimaan" {{ old('jenis') == 'penerimaan' ? 'selected' : '' }}>Penerimaan</option>
                                <option value="pengeluaran" {{ old('jenis') == 'pengeluaran' ? 'selected' : '' }}>Pengeluaran</option>
                            </select>
                            @error('jenis')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="no_bukti" class="form-label">No Bukti</label>
                            <input type="text" name="no_bukti" id="no_bukti" value="{{ old('no_bukti') }}" class="form-input bg-slate-50 text-slate-500 font-mono text-sm" readonly required>
                            @error('no_bukti')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Section 2: Item RKAS --}}
                    @include('transaksi-bku._rkas-picker', ['pickerInitial' => null])

                    {{-- Section 3: Kalkulator --}}
                    <div class="my-5 p-4 bg-blue-50 border border-blue-200 rounded-xl" id="row_kalkulator">
                        <label class="block text-sm font-semibold text-blue-800 mb-3">Kalkulator Otomatis</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="harga_satuan" class="block text-xs font-medium text-slate-600 mb-1">Harga Satuan (Dari Item RKAS)</label>
                                <input type="text" id="harga_satuan" class="form-input bg-slate-100 text-slate-500" readonly placeholder="Pilih item RKAS dulu">
                            </div>
                            <div>
                                <label for="volume_barang" class="block text-xs font-medium text-slate-600 mb-1">Jumlah Barang (Volume)</label>
                                <input type="number" id="volume_barang" class="form-input" placeholder="Contoh: 10" min="0" step="0.01">
                            </div>
                        </div>
                        <p class="text-xs text-blue-600 mt-2">Isi <strong>Jumlah Barang</strong> untuk menghitung otomatis nominal <strong>Jumlah</strong> di bawah.</p>
                    </div>

                    {{-- Section 4: Nominal & Rincian --}}
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Nominal & Rincian</h3>
                    </div>
                    <input type="hidden" name="volume" id="volume" value="{{ old('volume') }}">
                    <input type="hidden" name="satuan" id="satuan" value="{{ old('satuan') }}">

                    <div class="mb-5">
                        <label for="jumlah" class="form-label">Jumlah Nominal (Rp)</label>
                        <input type="number" name="jumlah" id="jumlah" value="{{ old('jumlah') }}" class="form-input text-lg font-bold" step="0.01" required>
                        @error('jumlah')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label for="toko_penerima" class="form-label">Toko / Penerima / Sumber Dana</label>
                            <input type="text" name="toko_penerima" id="toko_penerima" value="{{ old('toko_penerima') }}" class="form-input">
                            @error('toko_penerima')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div id="row_metode_pengadaan">
                            <label for="metode_pengadaan" class="form-label">Metode Pengadaan</label>
                            <select name="metode_pengadaan" id="metode_pengadaan" class="form-select">
                                <option value="">-- Pilih --</option>
                                <option value="siplah" {{ old('metode_pengadaan') == 'siplah' ? 'selected' : '' }}>SIPLAH</option>
                                <option value="non_siplah" {{ old('metode_pengadaan') == 'non_siplah' ? 'selected' : '' }}>Non-SIPLAH</option>
                            </select>
                            @error('metode_pengadaan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="uraian" class="form-label">Uraian</label>
                        <textarea name="uraian" id="uraian" rows="3" class="form-input" placeholder="Keterangan tambahan (opsional)">{{ old('uraian') }}</textarea>
                        @error('uraian')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="row_override" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl hidden">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="override_anggaran" id="override_anggaran" value="1" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500" {{ old('override_anggaran') ? 'checked' : '' }}>
                            <div class="flex-1">
                                <label for="override_anggaran" class="text-sm font-semibold text-amber-800 cursor-pointer">Override Sisa Anggaran</label>
                                <p class="text-xs text-amber-600 mt-0.5">Centang jika ingin melanjutkan meskipun melebihi sisa anggaran. Wajib isi catatan di bawah.</p>
                                <div id="row_override_note" class="mt-3 {{ old('override_anggaran') ? '' : 'hidden' }}">
                                    <label for="override_note" class="block text-xs font-medium text-amber-700 mb-1">Catatan Override</label>
                                    <textarea name="override_note" id="override_note" rows="2" class="form-input text-sm" placeholder="Sebutkan alasan override (contoh: ada SILPA bulan lalu, urgent)">{{ old('override_note') }}</textarea>
                                    @error('override_note')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <a href="{{ route('transaksi-bku.index') }}" class="btn btn-secondary">
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hargaInput = document.getElementById('harga_satuan');
            const volumeInput = document.getElementById('volume_barang');
            const volumeHidden = document.getElementById('volume');
            const satuanHidden = document.getElementById('satuan');
            const jumlahInput = document.getElementById('jumlah');
            const jenisSelect = document.getElementById('jenis');
            const tanggalInput = document.getElementById('tanggal');
            const noBuktiInput = document.getElementById('no_bukti');
            const overrideCheckbox = document.getElementById('override_anggaran');
            const overrideNoteRow = document.getElementById('row_override_note');
            const overrideRow = document.getElementById('row_override');

            const rowRkas = document.getElementById('row_rkas_item');
            const rowKalkulator = document.getElementById('row_kalkulator');
            const rowMetodePengadaan = document.getElementById('row_metode_pengadaan');

            const npsnCode = "{{ $npsn }}";
            const countPenerimaan = {{ $countPenerimaan }};
            const countPengeluaran = {{ $countPengeluaran }};

            function generateNoBukti() {
                var dateVal = tanggalInput.value;
                if (!dateVal) return;
                var dateObj = new Date(dateVal);
                var m = String(dateObj.getMonth() + 1).padStart(2, '0');
                var y = dateObj.getFullYear();
                if (jenisSelect.value === 'penerimaan') {
                    var num = String(countPenerimaan).padStart(3, '0');
                    noBuktiInput.value = 'BBU' + num + '/' + npsnCode + '/' + m + '/' + y;
                } else {
                    var num = String(countPengeluaran).padStart(3, '0');
                    noBuktiInput.value = 'BPU' + num + '/' + npsnCode + '/' + m + '/' + y;
                }
            }

            function toggleVisibility() {
                if (jenisSelect.value === 'penerimaan') {
                    rowRkas.style.display = 'none';
                    rowKalkulator.style.display = 'none';
                    rowMetodePengadaan.style.display = 'none';
                    window.RkasPicker.setSelected(null);
                    hargaInput.value = '';
                    hargaInput.dataset.val = 0;
                    volumeInput.value = '';
                } else {
                    rowRkas.style.display = 'block';
                    rowKalkulator.style.display = 'block';
                    rowMetodePengadaan.style.display = 'block';
                }
                generateNoBukti();
            }

            function updateHarga(data) {
                var tarif = data ? (parseFloat(data.tarif) || 0) : 0;
                if (tarif > 0) {
                    hargaInput.value = 'Rp ' + new Intl.NumberFormat('id-ID').format(tarif);
                    hargaInput.dataset.val = tarif;
                } else {
                    hargaInput.value = '';
                    hargaInput.dataset.val = 0;
                }
                kalkulasiJumlah();
                if (data) {
                    overrideRow.classList.remove('hidden');
                } else {
                    overrideRow.classList.add('hidden');
                }
            }

            function onPickerSelect(data) {
                if (data && data.satuan) {
                    satuanHidden.value = data.satuan;
                } else {
                    satuanHidden.value = '';
                }
                updateHarga(data);
            }

            overrideCheckbox.addEventListener('change', function() {
                overrideNoteRow.classList.toggle('hidden', !this.checked);
            });

            function kalkulasiJumlah() {
                var tarif = parseFloat(hargaInput.dataset.val) || 0;
                var vol = parseFloat(volumeInput.value) || 0;
                volumeHidden.value = vol > 0 ? vol : '';
                if (tarif > 0 && vol > 0 && jenisSelect.value === 'pengeluaran') {
                    jumlahInput.value = (tarif * vol).toFixed(2);
                }
            }

            window.RkasPicker.onSelect = onPickerSelect;

            jenisSelect.addEventListener('change', toggleVisibility);
            volumeInput.addEventListener('input', kalkulasiJumlah);
            tanggalInput.addEventListener('change', generateNoBukti);

            toggleVisibility();
            generateNoBukti();
        });
    </script>
</x-app-layout>
