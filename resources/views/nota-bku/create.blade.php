<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tambah Nota Belanja</div>
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
                <span class="card-title">Input Nota Belanja</span>
                <a href="{{ route('nota-bku.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('nota-bku.store') }}" id="form-nota">
                    @csrf

                    <div class="flex items-center gap-2 mb-6 text-sm font-medium flex-wrap">
                        <span class="step-badge-active inline-flex items-center gap-1.5 p-2 rounded-lg" id="badge-1">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs">1</span> Detail Transaksi
                        </span>
                        <span class="text-slate-300">→</span>
                        <span class="inline-flex items-center gap-1.5 text-slate-400 p-2" id="badge-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-xs">2</span> Barang / Jasa
                        </span>
                        <span class="text-slate-300">→</span>
                        <span class="inline-flex items-center gap-1.5 text-slate-400 p-2" id="badge-3">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-xs">3</span> Simpan
                        </span>
                    </div>

                    {{-- TA HAP 1 : DETAIL TRANSAKSI --}}
                    <div id="step-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="tanggal" class="form-label">Tanggal Transaksi</label>
                                <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" class="form-input" required>
                                @error('tanggal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="toko_penerima" class="form-label">Toko / Penyedia</label>
                                <input type="text" name="toko_penerima" id="toko_penerima" value="{{ old('toko_penerima') }}" class="form-input" placeholder="Nama toko / penyedia barang">
                                @error('toko_penerima')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="metode_pengadaan" class="form-label">Metode Pengadaan</label>
                                <select name="metode_pengadaan" id="metode_pengadaan" class="form-select">
                                    <option value="">-- Pilih --</option>
                                    <option value="siplah" {{ old('metode_pengadaan') == 'siplah' ? 'selected' : '' }}>SIPLAH</option>
                                    <option value="non_siplah" {{ old('metode_pengadaan') == 'non_siplah' ? 'selected' : '' }}>Non-SIPLAH</option>
                                </select>
                                @error('metode_pengadaan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                <div id="row_no_invoice_siplah" class="mt-3 {{ old('metode_pengadaan') == 'siplah' ? '' : 'hidden' }}">
                                    <label for="no_invoice_siplah" class="form-label">Nomor Invoice SIPLah</label>
                                    <input type="text" name="no_invoice_siplah" id="no_invoice_siplah" value="{{ old('no_invoice_siplah') }}" class="form-input" maxlength="255" placeholder="Contoh: INV/2026/000123">
                                    @error('no_invoice_siplah')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div>
                                <label for="uraian" class="form-label">Uraian / Keterangan</label>
                                <textarea name="uraian" id="uraian" rows="3" class="form-input">{{ old('uraian') }}</textarea>
                                @error('uraian')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <a href="{{ route('nota-bku.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="button" id="btn-lanjut-1" class="btn-primary">Lanjut ke Barang / Jasa</button>
                        </div>
                    </div>

                    {{-- TA HAP 2 : DETAIL BARANG / JASA --}}
                    <div id="step-2" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="kegiatan_id" class="form-label">Kegiatan <span class="text-red-500">*</span></label>
                                <select name="kegiatan_id" id="kegiatan_id" class="form-select" required>
                                    <option value="">-- Pilih Kegiatan --</option>
                                    @foreach($kegiatans as $kegiatan)
                                        <option value="{{ $kegiatan->id }}" {{ old('kegiatan_id') == $kegiatan->id ? 'selected' : '' }}>{{ $kegiatan->kode }} - {{ $kegiatan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('kegiatan_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="kode_rekening_id" class="form-label">Rekening Belanja <span class="text-red-500">*</span></label>
                                <select name="kode_rekening_id" id="kode_rekening_id" class="form-select" required>
                                    <option value="">-- Pilih Kode Rekening --</option>
                                    @foreach($kodeRekenings as $rekening)
                                        <option value="{{ $rekening->id }}" {{ old('kode_rekening_id') == $rekening->id ? 'selected' : '' }}>{{ $rekening->kode }} - {{ $rekening->nama }}</option>
                                    @endforeach
                                </select>
                                @error('kode_rekening_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl mb-4 text-sm text-blue-700">
                            Pilih kegiatan dan rekening belanja sesuai nota fisik, lalu centang item yang dibelanjakan dan isi <strong>Jumlah</strong> + <strong>Harga Satuan</strong>. Nomor nota otomatis dibuat sistem (format NOTA-XXXX). Format angka: titik ribuan, koma desimal.
                        </div>

                        @error('items')<div class="alert-error mb-4">{{ $message }}</div>@enderror

                        <div id="item-list" class="space-y-3 mb-4">
                            <div class="text-center text-slate-400 text-sm py-8">Pilih kegiatan dan rekening untuk memuat daftar item RKAS.</div>
                        </div>

                        <div id="manual-rows" class="space-y-3 mb-4"></div>

                        <div class="mb-4">
                            <button type="button" id="btn-tambah-item" class="btn btn-info btn-sm">+ Tambah Item</button>
                        </div>

                        <div id="items-hidden"></div>

                        <label class="flex items-start gap-3 mt-2 mb-4 cursor-pointer">
                            <input type="checkbox" id="penyelesaian" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-slate-600">Semua item dalam nota untuk kegiatan dan rekening ini sudah dimasukkan semua.</span>
                        </label>

                        <div class="flex items-center justify-between gap-2 pt-4 border-t border-slate-100">
                            <button type="button" id="btn-kembali-1" class="btn btn-secondary">Kembali</button>
                            <button type="button" id="btn-lanjut-2" class="btn-primary">Lanjut ke Ringkasan</button>
                        </div>
                    </div>

                    {{-- TA HAP 3 : RINGKASAN & SIMPAN --}}
                    <div id="step-3" class="hidden">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl mb-4 text-sm">
                            <span id="sum-tanggal"></span> ·
                            <span id="sum-toko"></span> ·
                            <span id="sum-metode"></span>
                        </div>

                        <div class="overflow-x-auto mb-4">
                            <table class="data-table w-full">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Uraian Barang</th>
                                        <th class="text-right">Jumlah</th>
                                        <th class="text-right">Harga Satuan</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="summary-rows"></tbody>
                                <tfoot>
                                    <tr class="bg-slate-100">
                                        <td colspan="4" class="text-right font-semibold">Total</td>
                                        <td class="text-right font-semibold" id="summary-total">Rp 0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-4 border-t border-slate-100">
                            <button type="button" id="btn-kembali-2" class="btn btn-secondary">Kembali</button>
                            <button type="submit" class="btn-primary">Simpan Nota</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const step3 = document.getElementById('step-3');
            const badge1 = document.getElementById('badge-1');
            const badge2 = document.getElementById('badge-2');
            const badge3 = document.getElementById('badge-3');
            const kegiatanSelect = document.getElementById('kegiatan_id');
            const kodeRekeningSelect = document.getElementById('kode_rekening_id');
            const tanggalInput = document.getElementById('tanggal');
            const itemList = document.getElementById('item-list');
            const manualRows = document.getElementById('manual-rows');
            const itemsHidden = document.getElementById('items-hidden');
            const metodePengadaanSelect = document.getElementById('metode_pengadaan');
            const rowNoInvoiceSiplah = document.getElementById('row_no_invoice_siplah');

            let cache = [];

            const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const fmt = n => new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
            const parseBulan = d => d ? String(new Date(d + 'T00:00:00').getMonth() + 1) : '';
            const itemById = id => cache.find(i => i.id === id) || null;

            function showStep(n) {
                step1.classList.toggle('hidden', n !== 1);
                step2.classList.toggle('hidden', n !== 2);
                step3.classList.toggle('hidden', n !== 3);
                [badge1, badge2, badge3].forEach((b, i) => {
                    const on = n === i + 1;
                    b.classList.toggle('step-badge-active', on);
                    b.classList.toggle('text-slate-400', !on);
                });
                if (n === 3) renderSummary();
            }

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
                        renderItems(bulan);
                        clearSelection();
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
                        '<div class="flex items-center gap-2 flex-shrink-0">' +
                            '<input type="text" class="item-qty form-input w-24 text-sm" placeholder="Jumlah" inputmode="decimal" value="1">' +
                            '<input type="text" class="item-harga form-input w-36 text-sm" placeholder="Harga satuan" inputmode="decimal" value="' + (it.tarif > 0 ? fmt(it.tarif) : '') + '">' +
                        '</div>' +
                    '</div>').join('');
                itemList.querySelectorAll('.item-row').forEach(bindRow);
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

            function bindRow(row) {
                const id = row.dataset.id;
                const check = row.querySelector('.item-check');
                const qty = row.querySelector('.item-qty');
                const harga = row.querySelector('.item-harga');
                check.addEventListener('change', () => {
                    const it = itemById(id);
                    check.checked ? addHidden(id, qty.value, harga.value, it ? it.satuan : '') : rmHidden(id);
                });
                qty.addEventListener('input', () => { if (check.checked) updHidden(id, { qty: qty.value }); });
                harga.addEventListener('input', () => { if (check.checked) updHidden(id, { harga: harga.value }); });
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
                }
                sel.addEventListener('change', sync);
                qty.addEventListener('input', () => { if (current) updHidden(current, { qty: qty.value }); });
                harga.addEventListener('input', () => { if (current) updHidden(current, { harga: harga.value }); });
                row.querySelector('.m-remove').addEventListener('click', () => { if (current) rmHidden(current); row.remove(); });
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

            function renderSummary() {
                document.getElementById('sum-tanggal').textContent = tanggalInput.value || '-';
                document.getElementById('sum-toko').textContent = (document.getElementById('toko_penerima').value || 'Toko: -');
                const met = metodePengadaanSelect.value;
                document.getElementById('sum-metode').textContent = met ? (met === 'siplah' ? 'Metode SIPLah' : 'Metode Non-SIPLAH') : 'Metode: -';
                const wraps = itemsHidden.querySelectorAll('div[id^="items-"]');
                let total = 0;
                let html = '';
                let i = 0;
                wraps.forEach(w => {
                    const id = w.id.replace('items-', '');
                    const it = itemById(id);
                    if (!it) return;
                    const qty = parseFloat(String(w.querySelector('.h-qty').value).replace(/,/g, '.'));
                    const harga = parseFloat(String(w.querySelector('.h-harga').value).replace(/\./g, '').replace(',', '.'));
                    const sub = isNaN(qty) || isNaN(harga) ? 0 : qty * harga;
                    total += sub;
                    i++;
                    html += '<tr>' +
                        '<td>' + i + '</td>' +
                        '<td>' + esc(it.uraian) + '</td>' +
                        '<td class="text-right">' + (isNaN(qty) ? 0 : qty) + '</td>' +
                        '<td class="text-right">' + (isNaN(harga) ? 0 : fmt(harga)) + '</td>' +
                        '<td class="text-right">' + fmt(sub) + '</td>' +
                    '</tr>';
                });
                document.getElementById('summary-rows').innerHTML = html || '<tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada item dipilih.</td></tr>';
                document.getElementById('summary-total').textContent = 'Rp ' + fmt(total);
            }

            document.getElementById('btn-lanjut-1').addEventListener('click', () => showStep(2));
            document.getElementById('btn-kembali-1').addEventListener('click', () => showStep(1));
            document.getElementById('btn-kembali-2').addEventListener('click', () => showStep(2));

            document.getElementById('btn-lanjut-2').addEventListener('click', () => {
                const hasItems = itemsHidden.querySelectorAll('div[id^="items-"]').length > 0;
                if (!hasItems) {
                    alert('Pilih minimal satu item belanja sebelum lanjut ke ringkasan.');
                    return;
                }
                if (!document.getElementById('penyelesaian').checked) {
                    alert('Centang konfirmasi bahwa semua item dalam nota sudah dimasukkan.');
                    return;
                }
                showStep(3);
            });

            kegiatanSelect.addEventListener('change', loadItems);
            kodeRekeningSelect.addEventListener('change', loadItems);
            tanggalInput.addEventListener('change', loadItems);
            metodePengadaanSelect.addEventListener('change', () => rowNoInvoiceSiplah.classList.toggle('hidden', metodePengadaanSelect.value !== 'siplah'));
            document.getElementById('btn-tambah-item').addEventListener('click', addManualRow);

            showStep(1);
        })();
    </script>
</x-app-layout>