<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Nota Multi-Item</div>
    </x-slot>

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="w-full">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Form Nota Belanja Baru</span>
                <a href="{{ route('nota-bku.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('nota-bku.store') }}" id="form-nota">
                    @csrf

                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Informasi Nota</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="tanggal" class="form-label">Tanggal Nota</label>
                            <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" class="form-input" required>
                            @error('tanggal')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="kegiatan_id" class="form-label">Kegiatan</label>
                            <select name="kegiatan_id" id="kegiatan_id" class="form-select" required>
                                <option value="">-- Pilih Kegiatan --</option>
                                @foreach($kegiatans as $kegiatan)
                                    <option value="{{ $kegiatan->id }}" {{ old('kegiatan_id') == $kegiatan->id ? 'selected' : '' }}>
                                        {{ $kegiatan->kode }} - {{ $kegiatan->nama }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">1 nota hanya untuk 1 kegiatan. Kegiatan berbeda → buat nota baru.</p>
                            @error('kegiatan_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="toko_penerima" class="form-label">Toko / Penerima</label>
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
                        <label for="uraian" class="form-label">Uraian / Keterangan</label>
                        <textarea name="uraian" id="uraian" rows="2" class="form-input" placeholder="Keterangan tambahan (opsional)">{{ old('uraian') }}</textarea>
                        @error('uraian')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Item Belanja</h3>
                    </div>

                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl mb-4 text-sm text-blue-700">
                        Pilih kegiatan di atas untuk memuat daftar item RKAS. Centang item yang akan dibelanjakan, lalu isi <strong>Jumlah</strong> dan <strong>Harga Satuan</strong>. Format angka: titik untuk ribuan (mis. 1.500.000), koma untuk desimal.
                    </div>

                    @error('items')
                        <div class="alert-error mb-4">
                            <svg aria-hidden="true" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <div id="item-list" class="space-y-3 mb-4">
                        <div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dulu untuk memuat daftar item RKAS.</div>
                    </div>

                    <div id="manual-rows" class="space-y-3 mb-4"></div>

                    <div class="mb-6">
                        <button type="button" id="btn-tambah-item" class="btn btn-info btn-sm">
                            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            + Tambah Item
                        </button>
                    </div>

                    <div id="items-hidden"></div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <a href="{{ route('nota-bku.index') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Simpan Nota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const kegiatanSelect = document.getElementById('kegiatan_id');
            const tanggalInput = document.getElementById('tanggal');
            const itemList = document.getElementById('item-list');
            const manualRows = document.getElementById('manual-rows');
            const itemsHidden = document.getElementById('items-hidden');
            const metodePengadaanSelect = document.getElementById('metode_pengadaan');
            const rowNoInvoiceSiplah = document.getElementById('row_no_invoice_siplah');
            const btnTambahItem = document.getElementById('btn-tambah-item');

            let itemsCache = [];

            function escapeHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function fmtRp(n) {
                return new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
            }

            function parseBulan(tanggal) {
                if (!tanggal) return '';
                const d = new Date(tanggal + 'T00:00:00');
                return String(d.getMonth() + 1);
            }

            function itemById(id) {
                return itemsCache.find(function(i) { return i.id === id; }) || null;
            }

            function clearSelection() {
                itemsHidden.innerHTML = '';
                manualRows.innerHTML = '';
            }

            function loadItems() {
                const kegiatan = kegiatanSelect.value;
                const tanggal = tanggalInput.value;
                if (!kegiatan || !tanggal) {
                    itemList.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dulu untuk memuat daftar item RKAS.</div>';
                    clearSelection();
                    return;
                }
                const bulan = parseBulan(tanggal);
                fetch('/nota-bku/items?kegiatan_id=' + encodeURIComponent(kegiatan) + '&bulan=' + encodeURIComponent(bulan))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        itemsCache = Array.isArray(data.results) ? data.results : [];
                        renderItems(bulan);
                        clearSelection();
                    })
                    .catch(function() {
                        itemsCache = [];
                        itemList.innerHTML = '<div class="text-center text-red-500 text-sm py-8">Gagal memuat item RKAS.</div>';
                        clearSelection();
                    });
            }

            function renderItems(bulan) {
                if (itemsCache.length === 0) {
                    itemList.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Tidak ada item RKAS untuk kegiatan ini.</div>';
                    return;
                }
                itemList.innerHTML = itemsCache.map(function(it) {
                    return '<div class="item-row border border-slate-200 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3" data-id="' + it.id + '">' +
                        '<label class="flex items-start gap-3 flex-1 cursor-pointer">' +
                            '<input type="checkbox" class="item-check mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="' + it.id + '">' +
                            '<span class="min-w-0">' +
                                '<span class="block text-sm font-medium text-slate-700">' + it.no_urut + '. ' + escapeHtml(it.uraian) + '</span>' +
                                '<span class="block text-xs text-slate-500 mt-0.5">Satuan: ' + escapeHtml(it.satuan || '-') +
                                ' · Sumber: ' + escapeHtml(it.sumber_dana || '-') +
                                ' · Sisa s.d. bulan ' + bulan + ': Rp ' + fmtRp(it.sisa) + '</span>' +
                            '</span>' +
                        '</label>' +
                        '<div class="flex items-center gap-2 flex-shrink-0">' +
                            '<input type="text" class="item-qty form-input w-24 text-sm" placeholder="Jumlah" inputmode="decimal" value="1">' +
                            '<input type="text" class="item-harga form-input w-36 text-sm" placeholder="Harga satuan" inputmode="decimal" value="' + (it.tarif > 0 ? fmtRp(it.tarif) : '') + '">' +
                        '</div>' +
                    '</div>';
                }).join('');
                itemList.querySelectorAll('.item-row').forEach(function(row) { bindRow(row); });
            }

            function ensureHidden(id) {
                let wrap = document.getElementById('items-' + id);
                if (wrap) return wrap;
                wrap = document.createElement('div');
                wrap.id = 'items-' + id;
                wrap.innerHTML =
                    '<input type="hidden" name="items[' + id + '][rkas_item_id]" value="' + id + '">' +
                    '<input type="hidden" name="items[' + id + '][qty]" class="h-qty" value="">' +
                    '<input type="hidden" name="items[' + id + '][harga]" class="h-harga" value="">' +
                    '<input type="hidden" name="items[' + id + '][satuan]" class="h-satuan" value="">';
                itemsHidden.appendChild(wrap);
                return wrap;
            }

            function addHidden(id, qty, harga, satuan) {
                const wrap = ensureHidden(id);
                wrap.querySelector('.h-qty').value = qty;
                wrap.querySelector('.h-harga').value = harga;
                wrap.querySelector('.h-satuan').value = satuan || '';
            }

            function updateHidden(id, patch) {
                const wrap = document.getElementById('items-' + id);
                if (!wrap) return;
                if ('qty' in patch) wrap.querySelector('.h-qty').value = patch.qty;
                if ('harga' in patch) wrap.querySelector('.h-harga').value = patch.harga;
                if ('satuan' in patch) wrap.querySelector('.h-satuan').value = patch.satuan || '';
            }

            function removeHidden(id) {
                const wrap = document.getElementById('items-' + id);
                if (wrap) wrap.remove();
            }

            function bindRow(row) {
                const id = row.dataset.id;
                const check = row.querySelector('.item-check');
                const qty = row.querySelector('.item-qty');
                const harga = row.querySelector('.item-harga');
                check.addEventListener('change', function() {
                    const it = itemById(id);
                    if (check.checked) {
                        addHidden(id, qty.value, harga.value, it ? it.satuan : '');
                    } else {
                        removeHidden(id);
                    }
                });
                qty.addEventListener('input', function() {
                    if (check.checked) updateHidden(id, { qty: qty.value });
                });
                harga.addEventListener('input', function() {
                    if (check.checked) updateHidden(id, { harga: harga.value });
                });
            }

            function bindManualRow(row) {
                const sel = row.querySelector('.m-select');
                const qty = row.querySelector('.m-qty');
                const harga = row.querySelector('.m-harga');
                const rem = row.querySelector('.m-remove');
                let current = null;
                function sync() {
                    if (current) removeHidden(current);
                    current = sel.value;
                    if (current) {
                        const it = itemById(current);
                        if (it && harga.value === '') harga.value = fmtRp(it.tarif);
                        addHidden(current, qty.value, harga.value, it ? it.satuan : '');
                    }
                }
                sel.addEventListener('change', sync);
                qty.addEventListener('input', function() { if (current) updateHidden(current, { qty: qty.value }); });
                harga.addEventListener('input', function() { if (current) updateHidden(current, { harga: harga.value }); });
                rem.addEventListener('click', function() { if (current) removeHidden(current); row.remove(); });
            }

            function addManualRow() {
                const opts = itemsCache.map(function(it) {
                    return '<option value="' + it.id + '">' + it.no_urut + '. ' + escapeHtml(it.uraian) + '</option>';
                }).join('');
                const row = document.createElement('div');
                row.className = 'border border-dashed border-slate-300 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3';
                row.innerHTML =
                    '<select class="m-select form-select text-sm flex-1"><option value="">-- Pilih item --</option>' + opts + '</select>' +
                    '<input type="text" class="m-qty form-input w-24 text-sm" placeholder="Jumlah" inputmode="decimal" value="1">' +
                    '<input type="text" class="m-harga form-input w-36 text-sm" placeholder="Harga satuan" inputmode="decimal">' +
                    '<button type="button" class="m-remove btn btn-ghost btn-sm">Batal</button>';
                manualRows.appendChild(row);
                bindManualRow(row);
            }

            function toggleNoInvoice() {
                rowNoInvoiceSiplah.classList.toggle('hidden', metodePengadaanSelect.value !== 'siplah');
            }

            kegiatanSelect.addEventListener('change', loadItems);
            tanggalInput.addEventListener('change', loadItems);
            metodePengadaanSelect.addEventListener('change', toggleNoInvoice);
            btnTambahItem.addEventListener('click', addManualRow);

            toggleNoInvoice();
            loadItems();
        });
    </script>
</x-app-layout>