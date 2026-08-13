<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Edit Transaksi BKU</div>
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

    @php
        $isNotaTransaksi = $transaksiBku->nota_bku_id !== null;
        $hargaSatuanEdit = ($transaksiBku->volume > 0) ? round($transaksiBku->jumlah / $transaksiBku->volume, 2) : 0;
        $initialKegiatanId = $transaksiBku->rkasItem?->program_id;
        $initialKodeRekeningId = $transaksiBku->rkasItem?->kode_rekening_id;
        $notaKegiatan = $transaksiBku->notaBku?->kegiatan;
        $notaRekening = $transaksiBku->notaBku?->kodeRekening;
        $segmenNota = $notaKegiatan && $notaKegiatan->kode ? explode('.', rtrim($notaKegiatan->kode, '.')) : [];
        $notaProgram = count($segmenNota) > 0 ? ($notaKegiatan->program ?: '-') : '-';
        $notaSubProgram = count($segmenNota) > 1 ? ($notaKegiatan->sub_program ?: '-') : '-';
    @endphp

    <div class="w-full">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Form Edit Transaksi</span>
                <a href="{{ route('transaksi-bku.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transaksi-bku.update', $transaksiBku) }}" id="form-bku">
                    @csrf
                    @method('PUT')

                    {{-- Section 1: Informasi Transaksi --}}
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Informasi Transaksi</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', $transaksiBku->tanggal) }}" class="form-input" required>
                            @error('tanggal')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="jenis" class="form-label">Jenis Transaksi</label>
                            <select name="jenis" id="jenis" class="form-select" required>
                                <option value="penerimaan" {{ old('jenis', strtolower($transaksiBku->jenis)) == 'penerimaan' ? 'selected' : '' }}>Penerimaan</option>
                                <option value="pengeluaran" {{ old('jenis', strtolower($transaksiBku->jenis)) == 'pengeluaran' ? 'selected' : '' }}>Pengeluaran</option>
                            </select>
                            @error('jenis')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="no_bukti" class="form-label">No Bukti</label>
                            <input type="text" name="no_bukti" id="no_bukti" value="{{ old('no_bukti', $transaksiBku->no_bukti) }}" class="form-input font-mono text-sm" required>
                            @error('no_bukti')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Section 2: Item RKAS (Penerimaan) --}}
                    @include('transaksi-bku._rkas-picker', ['pickerInitial' => $selectedRkas])

                    {{-- Section 2b: Kegiatan -> Rekening -> item (Pengeluaran) --}}
                    <div id="row_item_checklist" class="hidden">
                        <div class="mb-2">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pilih Kegiatan & Item Belanja</h3>
                        </div>

                        @if($isNotaTransaksi)
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl mb-4">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-700">Transaksi ini berasal dari Nota Multi-Item</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Rincian item belanja diatur dari halaman <strong>Riwayat Nota</strong>. Pada form ini hanya kolom header yang bisa diubah.</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm mt-4">
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">No. Nota</span>
                                        <p class="text-slate-800 font-semibold">{{ $transaksiBku->notaBku?->no_nota ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">Kegiatan</span>
                                        <p class="text-slate-800 font-semibold">{{ $notaKegiatan ? ($notaKegiatan->kode . ' - ' . $notaKegiatan->nama) : '-' }}</p>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">Jumlah Item</span>
                                        <p class="text-slate-800 font-semibold">{{ $transaksiBku->notaBku?->items->count() ?? 0 }}</p>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">Program</span>
                                        <p class="text-slate-800">{{ $notaProgram }}</p>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">Sub Program</span>
                                        <p class="text-slate-800">{{ $notaSubProgram }}</p>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-medium text-slate-500 mb-0.5">Kode Rekening</span>
                                        <p class="text-slate-800">{{ $notaRekening ? ($notaRekening->kode . ' - ' . $notaRekening->nama) : '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="kegiatan_id" class="form-label">Kegiatan <span class="text-red-500">*</span></label>
                                    <select name="kegiatan_id" id="kegiatan_id" class="form-select" required>
                                        <option value="">-- Pilih Kegiatan --</option>
                                        @foreach($kegiatans as $kegiatan)
                                            <option value="{{ $kegiatan->id }}" {{ (string) old('kegiatan_id', $initialKegiatanId) == (string) $kegiatan->id ? 'selected' : '' }}>{{ $kegiatan->kode }} - {{ $kegiatan->nama }}</option>
                                        @endforeach
                                    </select>
                                    @error('kegiatan_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="kode_rekening_id" class="form-label">Rekening Belanja <span class="text-red-500">*</span></label>
                                    <select name="kode_rekening_id" id="kode_rekening_id" class="form-select" required>
                                        <option value="">-- Pilih Kode Rekening --</option>
                                        @foreach($kodeRekenings as $rekening)
                                            <option value="{{ $rekening->id }}" {{ (string) old('kode_rekening_id', $initialKodeRekeningId) == (string) $rekening->id ? 'selected' : '' }}>{{ $rekening->kode }} - {{ $rekening->nama }}</option>
                                        @endforeach
                                    </select>
                                    @error('kode_rekening_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl mb-4 text-sm text-blue-700">
                                Item transaksi ini akan otomatis tercentang sesuai data lama. Sesuaikan <strong>Jumlah</strong> dan <strong>Harga Satuan</strong> bila perlu; <strong>Jumlah Nominal</strong> dihitung otomatis (qty × harga). Opsi override tidak tersedia saat edit.
                            </div>

                            @error('items')
                                <div class="alert-error mb-4">{{ $message }}</div>
                            @enderror

                            <div id="item-list" class="space-y-3 mb-4">
                                <div class="text-center text-slate-400 text-sm py-8">Memuat item RKAS...</div>
                            </div>

                            <div class="mb-4 p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-700">Total Belanja</span>
                                <span class="text-lg font-bold text-indigo-700" id="total-belanja">Rp 0</span>
                            </div>

                            <div id="items-hidden"></div>
                        @endif
                    </div>

                    {{-- Section 3: Nominal & Rincian --}}
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
                    <input type="hidden" name="volume" id="volume" value="{{ old('volume', $transaksiBku->volume) }}">
                    <input type="hidden" name="satuan" id="satuan" value="{{ old('satuan', $transaksiBku->satuan) }}">

                    <div class="mb-5" id="row_jumlah">
                        <label for="jumlah" class="form-label">Jumlah Nominal (Rp)</label>
                        <input type="text" name="jumlah" id="jumlah" value="{{ old('jumlah', $transaksiBku->jumlah) }}" class="form-input text-lg font-bold" inputmode="decimal" autocomplete="off" placeholder="Contoh: 1.500.000" required>
                        <p class="text-xs text-slate-400 mt-1">Format angka Indonesia: gunakan titik untuk ribuan (mis. 1.500.000).</p>
                        @error('jumlah')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label for="toko_penerima" class="form-label">Toko / Penerima / Sumber Dana</label>
                            <input type="text" name="toko_penerima" id="toko_penerima" value="{{ old('toko_penerima', $transaksiBku->toko_penerima) }}" class="form-input">
                            @error('toko_penerima')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div id="row_metode_pengadaan">
                            <label for="metode_pengadaan" class="form-label">Metode Pengadaan</label>
                            <select name="metode_pengadaan" id="metode_pengadaan" class="form-select">
                                <option value="">-- Pilih --</option>
                                <option value="siplah" {{ old('metode_pengadaan', $transaksiBku->metode_pengadaan) == 'siplah' ? 'selected' : '' }}>SIPLAH</option>
                                <option value="non_siplah" {{ old('metode_pengadaan', $transaksiBku->metode_pengadaan) == 'non_siplah' ? 'selected' : '' }}>Non-SIPLAH</option>
                            </select>
                            @error('metode_pengadaan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            <div id="row_no_invoice_siplah" class="mt-3 {{ old('metode_pengadaan', $transaksiBku->metode_pengadaan) == 'siplah' ? '' : 'hidden' }}">
                                <label for="no_invoice_siplah" class="form-label">Nomor Invoice SIPLah</label>
                                <input type="text" name="no_invoice_siplah" id="no_invoice_siplah" value="{{ old('no_invoice_siplah', $transaksiBku->no_invoice_siplah) }}" class="form-input" placeholder="Contoh: INV/2026/000123" maxlength="255">
                                <p class="text-xs text-slate-400 mt-1">Wajib diisi saat metode pengadaan SIPLah.</p>
                                @error('no_invoice_siplah')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="uraian" class="form-label">Uraian</label>
                        <textarea name="uraian" id="uraian" rows="3" class="form-input" placeholder="Keterangan tambahan (opsional)">{{ old('uraian', $transaksiBku->uraian) }}</textarea>
                        @error('uraian')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <a href="{{ route('transaksi-bku.index') }}" class="btn btn-secondary">
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

            const rowRkas = document.getElementById('row_rkas_item');
            const rowKalkulator = document.getElementById('row_kalkulator');
            const rowJumlah = document.getElementById('row_jumlah');
            const rowMetodePengadaan = document.getElementById('row_metode_pengadaan');
            const metodePengadaanSelect = document.getElementById('metode_pengadaan');
            const rowNoInvoiceSiplah = document.getElementById('row_no_invoice_siplah');
            const rowChecklist = document.getElementById('row_item_checklist');
            const formEl = document.getElementById('form-bku');

            const isNota = @json($isNotaTransaksi);
            const initialItemId = @json((string) ($transaksiBku->rkas_item_id ?? ''));
            const initialQty = @json((string) ($transaksiBku->volume ?? ''));
            const initialHarga = @json((float) $hargaSatuanEdit);
            let autoChecked = false;

            const kegiatanSelect = document.getElementById('kegiatan_id');
            const kodeRekeningSelect = document.getElementById('kode_rekening_id');
            const itemList = document.getElementById('item-list');
            const itemsHidden = document.getElementById('items-hidden');
            const totalBelanja = document.getElementById('total-belanja');

            var volumeTouched = false;
            var initializing = true;

            let cache = [];

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

            // ---- checklist item (diadaptasi dari create.blade.php) ----

            function loadItems() {
                const keg = kegiatanSelect.value, rek = kodeRekeningSelect.value, tgl = tanggalInput.value;
                if (!keg || !rek || !tgl) {
                    itemList.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dan rekening untuk memuat daftar item RKAS.</div>';
                    clearSelection();
                    return;
                }
                const bulan = parseBulan(tgl);
                fetch('/nota-bku/items?kegiatan_id=' + encodeURIComponent(keg) + '&kode_rekening_id=' + encodeURIComponent(rek) + '&bulan=' + encodeURIComponent(bulan))
                    .then(r => r.json())
                    .then(data => {
                        cache = Array.isArray(data.results) ? data.results : [];
                        clearSelection();
                        renderItems(bulan);
                    })
                    .catch(() => {
                        cache = [];
                        itemList.innerHTML = '<div class="text-center text-red-500 text-sm py-8">Gagal memuat item RKAS.</div>';
                        clearSelection();
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

                if (!autoChecked && initialItemId) {
                    const row = itemList.querySelector('.item-row[data-id="' + initialItemId + '"]');
                    if (row) {
                        row.querySelector('.item-check').checked = true;
                        if (initialQty !== '' && initialQty !== 'null' && initialQty !== null) {
                            row.querySelector('.item-qty').value = initialQty;
                        }
                        if (initialHarga > 0) {
                            row.querySelector('.item-harga').value = fmt(initialHarga);
                        }
                        const it = itemById(initialItemId);
                        addHidden(initialItemId, row.querySelector('.item-qty').value, row.querySelector('.item-harga').value, it ? it.satuan : '');
                        updateRowSubtotal(row);
                        autoChecked = true;
                    }
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

            function selectedCount() {
                return itemsHidden.querySelectorAll('div[id^="items-"]').length;
            }

            function toggleVisibility() {
                if (jenisSelect.value === 'penerimaan') {
                    rowRkas.style.display = 'block';
                    rowChecklist.classList.add('hidden');
                    rowKalkulator.style.display = 'block';
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
                    rowJumlah.classList.toggle('hidden', !isNota);
                    rowMetodePengadaan.style.display = 'block';
                }
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

            if (!isNota) {
                tanggalInput.addEventListener('change', loadItems);
                kegiatanSelect.addEventListener('change', loadItems);
                kodeRekeningSelect.addEventListener('change', loadItems);
            }

            formEl.addEventListener('submit', function(event) {
                if (jenisSelect.value === 'pengeluaran') {
                    if (!isNota && selectedCount() === 0) {
                        event.preventDefault();
                        alert('Centang minimal satu item belanja terlebih dahulu.');
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
            window.RkasPicker.init();
            if (!isNota && kegiatanSelect.value && kodeRekeningSelect.value) {
                loadItems();
            }
            initializing = false;
        });
    </script>
</x-app-layout>
