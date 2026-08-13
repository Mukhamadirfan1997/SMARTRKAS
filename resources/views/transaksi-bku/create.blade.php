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
                <form method="POST" action="{{ route('transaksi-bku.store') }}" id="form-bku" novalidate>
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
                        <div id="row_no_bukti">
                            <label for="no_bukti" class="form-label">No Bukti</label>
                            <input type="text" name="no_bukti" id="no_bukti" value="{{ old('no_bukti') }}" class="form-input bg-slate-50 text-slate-500 font-mono text-sm" readonly required>
                            <p class="text-xs text-slate-400 mt-1 hidden" id="no_bukti_hint_nota">Nomor bukti (BPU) dibuat otomatis saat menyimpan nota multi-item.</p>
                            @error('no_bukti')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Section 2: Item RKAS --}}
                    @include('transaksi-bku._rkas-picker', ['pickerInitial' => $pickerInitial])

                    {{-- Blok pilih Kegiatan -> Rekening -> checklist item (untuk Jenis Pengeluaran) --}}
                    <div id="row_item_checklist" class="hidden">
                        <div class="mb-2">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pilih Kegiatan & Item Belanja</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="kegiatan_id" class="form-label">Kegiatan <span class="text-red-500">*</span></label>
                                <input type="text" id="kegiatan_search" class="form-input text-sm mb-1.5" placeholder="Ketik untuk mencari kegiatan..." autocomplete="off">
                                <select name="kegiatan_id" id="kegiatan_id" class="form-select" required>
                                    <option value="">-- Pilih Kegiatan --</option>
                                    @foreach($kegiatans as $kegiatan)
                                        <option value="{{ $kegiatan->id }}" {{ old('kegiatan_id') == $kegiatan->id ? 'selected' : '' }}>{{ $kegiatan->kode }} - {{ $kegiatan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('kegiatan_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="kode_rekening_id" class="form-label">Rekening Belanja <span class="text-red-500">*</span></label>
                                <input type="text" id="kode_rekening_search" class="form-input text-sm mb-1.5" placeholder="Ketik untuk mencari rekening..." autocomplete="off">
                                <select name="kode_rekening_id" id="kode_rekening_id" class="form-select" required>
                                    <option value="">-- Pilih Kode Rekening --</option>
                                    @foreach($kodeRekenings as $rekening)
                                        <option value="{{ $rekening->id }}" {{ old('kode_rekening_id') == $rekening->id ? 'selected' : '' }}>{{ $rekening->kode }} - {{ $rekening->nama }}</option>
                                    @endforeach
                                </select>
                                @error('kode_rekening_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl mb-4 text-sm text-blue-700">
                            Pilih kegiatan dan rekening belanja sesuai transaksi fisik, lalu <strong>centang item</strong> yang dibelanjakan dan isi <strong>Jumlah</strong> + <strong>Harga Satuan</strong>.
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li><strong>1 item dicentang</strong>: transaksi tunggal (bisa gunakan opsi "Override Sisa Anggaran").</li>
                                <li><strong>2+ item dicentang</strong>: disimpan sebagai <strong>Nota Multi-Item</strong> — seluruh nota dibatalkan bila ada satu saja item melebihi anggaran (tanpa opsi override).</li>
                            </ul>
                            Nomor bukti / nomor nota dibuat otomatis oleh sistem.
                        </div>

                        @error('items')
                            <div class="alert-error mb-4">{{ $message }}</div>
                        @enderror

                        <div id="item-list" class="space-y-3 mb-4">
                            <div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dan rekening untuk memuat daftar item RKAS.</div>
                        </div>

                        <div id="manual-rows" class="space-y-3 mb-4"></div>

                        <div class="mb-4">
                            <button type="button" id="btn-tambah-item" class="btn btn-info btn-sm">+ Tambah Item</button>
                        </div>

                        <div class="mb-4 p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-700">Total Belanja</span>
                            <span class="text-lg font-bold text-indigo-700" id="total-belanja">Rp 0</span>
                        </div>

                        <div id="items-hidden"></div>

                        <label class="flex items-start gap-3 mt-2 mb-6 cursor-pointer">
                            <input type="checkbox" id="penyelesaian" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-slate-600">Semua item dalam transaksi untuk kegiatan dan rekening ini sudah dimasukkan semua.</span>
                        </label>
                    </div>

                    {{-- Section 4: Nominal & Rincian --}}
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Nominal & Rincian</h3>
                    </div>

                    {{-- Kalkulator (untuk Jenis Penerimaan) --}}
                    <div class="my-5 p-4 bg-blue-50 border border-blue-200 rounded-xl hidden" id="row_kalkulator">
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
                    <input type="hidden" name="volume" id="volume" value="{{ old('volume') }}">
                    <input type="hidden" name="satuan" id="satuan" value="{{ old('satuan') }}">

                    <div class="mb-5 hidden" id="row_jumlah">
                        <label for="jumlah" class="form-label">Jumlah Nominal (Rp)</label>
                        <input type="text" name="jumlah" id="jumlah" value="{{ old('jumlah') }}" class="form-input text-lg font-bold" inputmode="decimal" autocomplete="off" placeholder="Contoh: 1.500.000" required>
                        <p class="text-xs text-slate-400 mt-1">Format angka Indonesia: gunakan titik untuk ribuan (mis. 1.500.000).</p>
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
                            <div id="row_no_invoice_siplah" class="mt-3 {{ old('metode_pengadaan') == 'siplah' ? '' : 'hidden' }}">
                                <label for="no_invoice_siplah" class="form-label">Nomor Invoice SIPLah</label>
                                <input type="text" name="no_invoice_siplah" id="no_invoice_siplah" value="{{ old('no_invoice_siplah') }}" class="form-input" placeholder="Contoh: INV/2026/000123" maxlength="255">
                                <p class="text-xs text-slate-400 mt-1">Wajib diisi saat metode pengadaan SIPLah.</p>
                                @error('no_invoice_siplah')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
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
                                <p class="text-xs text-amber-600 mt-0.5">Hanya tersedia saat <strong>tepat 1 item</strong> dicentang. Centang jika ingin melanjutkan meskipun melebihi sisa anggaran. Wajib isi catatan minimal 10 karakter. Kwitansi transaksi ini akan terkunci sampai dilakukan pergeseran / Perubahan Anggaran (PA).</p>
                                <div id="row_override_note" class="mt-3 {{ old('override_anggaran') ? '' : 'hidden' }}">
                                    <label for="override_note" class="block text-xs font-medium text-amber-700 mb-1">Catatan Override</label>
                                    <textarea name="override_note" id="override_note" rows="2" class="form-input text-sm" placeholder="Sebutkan alasan override secara jelas (min. 10 karakter), contoh: harga barang naik karena penyesuaian harga" maxlength="500">{{ old('override_note') }}</textarea>
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
            const rowNoBukti = document.getElementById('row_no_bukti');
            const overrideCheckbox = document.getElementById('override_anggaran');
            const overrideNoteRow = document.getElementById('row_override_note');
            const overrideRow = document.getElementById('row_override');

            const rowRkas = document.getElementById('row_rkas_item');
            const rowKalkulator = document.getElementById('row_kalkulator');
            const rowJumlah = document.getElementById('row_jumlah');
            const rowMetodePengadaan = document.getElementById('row_metode_pengadaan');
            const metodePengadaanSelect = document.getElementById('metode_pengadaan');
            const rowNoInvoiceSiplah = document.getElementById('row_no_invoice_siplah');
            const rowChecklist = document.getElementById('row_item_checklist');

            const kegiatanSelect = document.getElementById('kegiatan_id');
            const kodeRekeningSelect = document.getElementById('kode_rekening_id');
            const itemList = document.getElementById('item-list');
            const manualRows = document.getElementById('manual-rows');
            const itemsHidden = document.getElementById('items-hidden');
            const totalBelanja = document.getElementById('total-belanja');

            const npsnCode = "{{ $npsn }}";
            const countPenerimaan = {{ $countPenerimaan }};
            const countPengeluaran = {{ $countPengeluaran }};
            const formEl = document.getElementById('form-bku');
            var volumeTouched = false;
            var initializing = true;

            function initSearchableSelect(selectId, inputId) {
                var select = document.getElementById(selectId);
                var input = document.getElementById(inputId);
                if (!select || !input) return;
                input.addEventListener('input', function () {
                    var q = this.value.trim().toLowerCase();
                    for (var i = 0; i < select.options.length; i++) {
                        var opt = select.options[i];
                        if (opt.value === '' || opt.selected) { opt.hidden = false; continue; }
                        opt.hidden = opt.textContent.toLowerCase().indexOf(q) === -1;
                    }
                });
            }

            let cache = [];
            let oldItems = @json(old('items', [])) || {};
            let oldRestored = false;

            const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const fmt = n => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
            const parseBulan = d => d ? String(new Date(d + 'T00:00:00').getMonth() + 1) : '';
            const itemById = id => cache.find(i => i.id === id) || null;
            const parseNum = s => parseFloat(String(s || '').replace(/\s+/g, '').replace(/\./g, '').replace(',', '.')) || 0;
            const parseDec = s => parseFloat(String(s || '').replace(/\s+/g, '').replace(',', '.')) || 0;

            function parseRupiah(value) {
                value = String(value).replace(/\s+/g, '');
                if (/^[+-]?\d+(\.\d{1,2})?$/.test(value)) {
                    return value;
                }
                return value.replace(/\./g, '').replace(/,/g, '.');
            }

            function parseDecimal(value) {
                return String(value).replace(/\s+/g, '').replace(/,/g, '.');
            }

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

            // ---- checklist item (diadaptasi dari nota-bku/create.blade.php) ----

            function loadItems() {
                const keg = kegiatanSelect.value, rek = kodeRekeningSelect.value, tgl = tanggalInput.value;
                if (!keg || !rek || !tgl) {
                    itemList.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dan rekening untuk memuat daftar item RKAS.</div>';
                    clearSelection();
                    recalcOverrideAndBukti();
                    return;
                }
                const bulan = parseBulan(tgl);
                fetch('/nota-bku/items?kegiatan_id=' + encodeURIComponent(keg) + '&kode_rekening_id=' + encodeURIComponent(rek) + '&bulan=' + encodeURIComponent(bulan))
                    .then(r => r.json())
                    .then(data => {
                        cache = Array.isArray(data.results) ? data.results : [];
                        clearSelection();
                        renderItems(bulan);
                        recalcOverrideAndBukti();
                    })
                    .catch(() => {
                        cache = [];
                        itemList.innerHTML = '<div class="text-center text-red-500 text-sm py-8">Gagal memuat item RKAS.</div>';
                        clearSelection();
                        recalcOverrideAndBukti();
                    });
            }

            function renderItems(bulan) {
                if (cache.length === 0) {
                    itemList.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Tidak ada item untuk kegiatan dan rekening ini.</div>';
                    return;
                }
                itemList.innerHTML = cache.map(it =>
                    '<div class="item-row border border-slate-200 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3" data-id="' + it.id + '">' +
                        '<label class="flex items-start gap-3 flex-1 cursor-pointer">' +
                            '<input type="checkbox" class="item-check mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="' + it.id + '">' +
                            '<span class="min-w-0">' +
                                '<span class="block text-sm font-medium text-slate-700">' + it.no_urut + '. ' + esc(it.uraian) + '</span>' +
                                '<span class="block text-xs text-slate-500 mt-0.5">Satuan: ' + esc(it.satuan || '-') + ' · Sisa s.d. bulan ' + bulan + ': Rp ' + fmt(it.sisa) + '</span>' +
                            '</span>' +
                        '</label>' +
                        '<input type="text" class="item-qty form-input w-24 text-sm" placeholder="Jumlah" inputmode="decimal" value="1">' +
                        '<input type="text" class="item-harga form-input w-36 text-sm" placeholder="Harga satuan" inputmode="decimal" value="' + (it.tarif > 0 ? fmt(it.tarif) : '') + '">' +
                        '<span class="row-subtotal text-sm font-semibold text-slate-700 w-24 text-right">Rp 0</span>' +
                    '</div>').join('');
                itemList.querySelectorAll('.item-row').forEach(bindRow);
                itemList.querySelectorAll('.item-row').forEach(updateRowSubtotal);

                if (!oldRestored && Object.keys(oldItems).length) {
                    oldRestored = true;
                    Object.keys(oldItems).forEach(function(id) {
                        const old = oldItems[id];
                        if (!old || !old.rkas_item_id) return;
                        const row = itemList.querySelector('.item-row[data-id="' + id + '"]');
                        if (!row) return;
                        row.querySelector('.item-check').checked = true;
                        if (old.qty !== undefined && old.qty !== '') row.querySelector('.item-qty').value = old.qty;
                        if (old.harga !== undefined && old.harga !== '') row.querySelector('.item-harga').value = old.harga;
                        addHidden(id, row.querySelector('.item-qty').value, row.querySelector('.item-harga').value, old.satuan || '');
                        updateRowSubtotal(row);
                    });
                }
                updateTotal();
            }

            function hiddenWrap(id) {
                let w = document.getElementById('items-' + id);
                if (w) return w;
                w = document.createElement('div');
                w.id = 'items-' + id;
                w.innerHTML =
                    '<input type="hidden" name="items[' + id + '][rkas_item_id]" value="' + id + '">' +
                    '<input type="hidden" name="items[' + id + '][qty]" class="h-qty" value="">' +
                    '<input type="hidden" name="items[' + id + '][harga]" class="h-harga" value="">' +
                    '<input type="hidden" name="items[' + id + '][satuan]" class="h-satuan" value="">';
                itemsHidden.appendChild(w);
                return w;
            }

            function addHidden(id, qty, harga, satuan) {
                const w = hiddenWrap(id);
                w.querySelector('.h-qty').value = qty;
                w.querySelector('.h-harga').value = harga;
                w.querySelector('.h-satuan').value = satuan || '';
            }

            function updHidden(id, patch) {
                const w = document.getElementById('items-' + id);
                if (!w) return;
                if ('qty' in patch) w.querySelector('.h-qty').value = patch.qty;
                if ('harga' in patch) w.querySelector('.h-harga').value = patch.harga;
                if ('satuan' in patch) w.querySelector('.h-satuan').value = patch.satuan || '';
            }

            function rmHidden(id) {
                const w = document.getElementById('items-' + id);
                if (w) w.remove();
            }

            function clearSelection() {
                itemsHidden.innerHTML = '';
                manualRows.innerHTML = '';
            }

            function updateRowSubtotal(row) {
                const qty = parseDec(row.querySelector('.item-qty').value);
                const harga = parseNum(row.querySelector('.item-harga').value);
                row.querySelector('.row-subtotal').textContent = 'Rp ' + fmt(qty * harga);
            }

            function updateTotal() {
                let total = 0;
                itemsHidden.querySelectorAll('div[id^="items-"]').forEach(function(w) {
                    const qty = parseDec(w.querySelector('.h-qty').value);
                    const harga = parseNum(w.querySelector('.h-harga').value);
                    total += qty * harga;
                });
                totalBelanja.textContent = 'Rp ' + fmt(total);
            }

            function bindRow(row) {
                const id = row.dataset.id;
                const check = row.querySelector('.item-check');
                const qty = row.querySelector('.item-qty');
                const harga = row.querySelector('.item-harga');
                check.addEventListener('change', () => {
                    const it = itemById(id);
                    if (check.checked) {
                        addHidden(id, qty.value, harga.value, it ? it.satuan : '');
                    } else {
                        rmHidden(id);
                    }
                    updateRowSubtotal(row);
                    updateTotal();
                    recalcOverrideAndBukti();
                });
                qty.addEventListener('input', () => {
                    if (check.checked) updHidden(id, { qty: qty.value });
                    updateRowSubtotal(row);
                    updateTotal();
                });
                harga.addEventListener('input', () => {
                    if (check.checked) updHidden(id, { harga: harga.value });
                    updateRowSubtotal(row);
                    updateTotal();
                });
            }

            function bindManualRow(row) {
                const sel = row.querySelector('.m-select');
                const qty = row.querySelector('.m-qty');
                const harga = row.querySelector('.m-harga');
                let current = null;
                function sync() {
                    if (current) rmHidden(current);
                    current = sel.value;
                    if (current) {
                        const it = itemById(current);
                        if (it && harga.value === '') harga.value = fmt(it.tarif);
                        addHidden(current, qty.value, harga.value, it ? it.satuan : '');
                    }
                    updateTotal();
                    recalcOverrideAndBukti();
                }
                sel.addEventListener('change', sync);
                qty.addEventListener('input', () => { if (current) updHidden(current, { qty: qty.value }); updateTotal(); });
                harga.addEventListener('input', () => { if (current) updHidden(current, { harga: harga.value }); updateTotal(); });
                row.querySelector('.m-remove').addEventListener('click', () => {
                    if (current) rmHidden(current);
                    row.remove();
                    updateTotal();
                    recalcOverrideAndBukti();
                });
            }

            function addManualRow() {
                const opts = cache.map(it => '<option value="' + it.id + '">' + it.no_urut + '. ' + esc(it.uraian) + '</option>').join('');
                const row = document.createElement('div');
                row.className = 'border border-dashed border-slate-300 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3';
                row.innerHTML =
                    '<select class="m-select form-select text-sm flex-1"><option value="">-- Pilih item --</option>' + opts + '</select>' +
                    '<input type="text" class="m-qty form-input w-24 text-sm" value="1" inputmode="decimal">' +
                    '<input type="text" class="m-harga form-input w-36 text-sm" inputmode="decimal">' +
                    '<button type="button" class="m-remove btn btn-ghost btn-sm">Batal</button>';
                manualRows.appendChild(row);
                bindManualRow(row);
            }

            function selectedCount() {
                return itemsHidden.querySelectorAll('div[id^="items-"]').length;
            }

            // ---- toggle override & no_bukti berdasar jumlah item (reaktif dua arah) ----

            function recalcOverrideAndBukti() {
                const count = selectedCount();
                if (jenisSelect.value === 'penerimaan') {
                    overrideRow.classList.add('hidden');
                    rowNoBukti.classList.remove('hidden');
                    document.getElementById('no_bukti_hint_nota').classList.add('hidden');
                    return;
                }
                overrideRow.classList.toggle('hidden', count !== 1);
                rowNoBukti.classList.toggle('hidden', count >= 2);
                document.getElementById('no_bukti_hint_nota').classList.toggle('hidden', count < 2);
            }

            function toggleVisibility() {
                if (jenisSelect.value === 'penerimaan') {
                    rowRkas.style.display = 'none';
                    rowChecklist.classList.add('hidden');
                    rowKalkulator.style.display = 'none';
                    rowJumlah.classList.remove('hidden');
                    rowMetodePengadaan.style.display = 'none';
                    volumeInput.value = '';
                    volumeHidden.value = '';
                    satuanHidden.value = '';
                    if (!initializing) {
                        window.RkasPicker.setSelected(null);
                    }
                    hargaInput.value = '';
                    hargaInput.dataset.val = 0;
                } else {
                    rowRkas.style.display = 'none';
                    rowChecklist.classList.remove('hidden');
                    rowKalkulator.style.display = 'none';
                    rowJumlah.classList.add('hidden');
                    rowMetodePengadaan.style.display = 'block';
                }
                recalcOverrideAndBukti();
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

            function toggleNoInvoice() {
                rowNoInvoiceSiplah.classList.toggle('hidden', metodePengadaanSelect.value !== 'siplah');
            }
            metodePengadaanSelect.addEventListener('change', toggleNoInvoice);

            function kalkulasiJumlah() {
                var tarif = parseFloat(hargaInput.dataset.val) || 0;
                var vol = parseFloat(parseDecimal(volumeInput.value)) || 0;
                if (volumeInput.value !== '' || volumeTouched) {
                    volumeHidden.value = vol > 0 ? vol : '';
                }
                if (tarif > 0 && vol > 0 && jenisSelect.value === 'pengeluaran') {
                    jumlahInput.value = (tarif * vol).toFixed(2);
                }
            }

            window.RkasPicker.onSelect = onPickerSelect;

            jenisSelect.addEventListener('change', toggleVisibility);
            volumeInput.addEventListener('input', function() {
                volumeTouched = true;
                kalkulasiJumlah();
            });
            tanggalInput.addEventListener('change', function() {
                generateNoBukti();
                loadItems();
            });

            kegiatanSelect.addEventListener('change', loadItems);
            kodeRekeningSelect.addEventListener('change', loadItems);
            document.getElementById('btn-tambah-item').addEventListener('click', addManualRow);

            formEl.addEventListener('submit', function(event) {
                if (jenisSelect.value === 'pengeluaran') {
                    if (selectedCount() === 0) {
                        event.preventDefault();
                        alert('Centang minimal satu item belanja terlebih dahulu.');
                        return;
                    }
                    if (!document.getElementById('penyelesaian').checked) {
                        event.preventDefault();
                        alert('Centang konfirmasi bahwa semua item dalam transaksi sudah dimasukkan.');
                        return;
                    }
                } else if (!jumlahInput.value.trim()) {
                    event.preventDefault();
                    alert('Isi jumlah nominal (Rp) terlebih dahulu.');
                    return;
                }
                if (jumlahInput.value) {
                    jumlahInput.value = parseRupiah(jumlahInput.value);
                }
            });

            toggleVisibility();
            toggleNoInvoice();
            initSearchableSelect('kegiatan_id', 'kegiatan_search');
            initSearchableSelect('kode_rekening_id', 'kode_rekening_search');
            window.RkasPicker.init();
            generateNoBukti();
            if (kegiatanSelect.value && kodeRekeningSelect.value) {
                loadItems();
            }
            recalcOverrideAndBukti();
            initializing = false;
        });
    </script>
</x-app-layout>
