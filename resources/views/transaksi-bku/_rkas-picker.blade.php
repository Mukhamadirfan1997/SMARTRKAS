{{-- Picker item RKAS berbasis vanilla JS (fetch endpoint rkas-items.select2). --}}
<div id="row_rkas_item">
    <div class="mb-2">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Item RKAS</h3>
    </div>
    <div class="mb-3 relative">
        <input type="text" id="rkas_search" class="form-input" placeholder="Cari item RKAS (no urut / uraian)..." autocomplete="off">
        <input type="hidden" name="rkas_item_id" id="rkas_item_id" value="{{ $pickerInitial['id'] ?? '' }}">
        <div id="rkas_results" class="hidden absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
        @error('rkas_item_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div id="rkas_detail_card" class="hidden mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-xl">
        <div class="flex items-start justify-between gap-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs flex-1">
                <div>
                    <span class="text-slate-500 font-medium">Program</span>
                    <p class="text-slate-800 font-semibold mt-0.5" id="detail_program">-</p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Kode Rekening</span>
                    <p class="text-slate-800 font-semibold mt-0.5" id="detail_kode">-</p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Tarif / Satuan</span>
                    <p class="text-slate-800 font-semibold mt-0.5" id="detail_tarif">-</p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium" id="detail_sisa_label">Sisa</span>
                    <p class="text-emerald-700 font-bold mt-0.5" id="detail_sisa">-</p>
                </div>
            </div>
            <button type="button" id="rkas_clear" class="btn btn-secondary btn-sm hidden" title="Bersihkan pilihan">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    if (window.__rkasPickerInit) return;
    window.__rkasPickerInit = true;

    var searchEl = document.getElementById('rkas_search');
    var hiddenEl = document.getElementById('rkas_item_id');
    var resultsEl = document.getElementById('rkas_results');
    var detailCard = document.getElementById('rkas_detail_card');
    var clearBtn = document.getElementById('rkas_clear');
    var detailProgram = document.getElementById('detail_program');
    var detailKode = document.getElementById('detail_kode');
    var detailTarif = document.getElementById('detail_tarif');
    var detailSisa = document.getElementById('detail_sisa');
    var detailSisaLabel = document.getElementById('detail_sisa_label');

    var selectedItem = @json($pickerInitial ?? null);
    var debounceTimer = null;

    function bulanDariTanggal() {
        var tanggalEl = document.getElementById('tanggal');
        if (!tanggalEl || !tanggalEl.value) return '';
        return String(new Date(tanggalEl.value).getMonth() + 1);
    }

    function renderDetail(item) {
        if (item) {
            hiddenEl.value = item.id;
            detailProgram.textContent = item.program || '-';
            detailKode.textContent = item.kode || '-';
            var tarifText = (item.tarif && item.tarif > 0)
                ? 'Rp ' + new Intl.NumberFormat('id-ID').format(item.tarif) + ' / ' + (item.satuan || '-')
                : '-';
            detailTarif.textContent = tarifText;
            detailSisaLabel.textContent = item.bulan ? 'Sisa s.d. bulan ' + item.bulan : 'Sisa';
            detailSisa.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(item.sisa || 0);
            detailCard.classList.remove('hidden');
            clearBtn.classList.remove('hidden');
        } else {
            hiddenEl.value = '';
            detailCard.classList.add('hidden');
            clearBtn.classList.add('hidden');
        }
    }

    function selectItem(item) {
        selectedItem = item;
        renderDetail(item);
        if (item) {
            searchEl.value = item.text || '';
        }
        if (window.RkasPicker && typeof window.RkasPicker.onSelect === 'function') {
            window.RkasPicker.onSelect(item);
        }
        resultsEl.classList.add('hidden');
    }

    function showSearching() {
        resultsEl.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Memuat...</div>';
        resultsEl.classList.remove('hidden');
    }

    function renderResults(results) {
        if (!results.length) {
            resultsEl.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Tidak ada item cocok.</div>';
            return;
        }
        var ul = document.createElement('ul');
        ul.className = 'divide-y divide-slate-100';
        results.forEach(function(item) {
            var li = document.createElement('li');
            li.className = 'px-3 py-2 hover:bg-indigo-50 cursor-pointer';
            li.dataset.item = JSON.stringify(item);
            var text = document.createElement('div');
            text.className = 'text-sm text-slate-700';
            text.textContent = item.text;
            li.appendChild(text);
            ul.appendChild(li);
        });
        resultsEl.innerHTML = '';
        resultsEl.appendChild(ul);
    }

    function doSearch(q) {
        var params = new URLSearchParams({ q: q, bulan: bulanDariTanggal() });
        showSearching();
        fetch('{{ route("rkas-items.select2") }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function(res) { return res.json(); })
            .then(function(data) { renderResults(data.results || []); })
            .catch(function() {
                resultsEl.innerHTML = '<div class="px-3 py-2 text-xs text-red-500">Gagal memuat data.</div>';
            });
    }

    searchEl.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = searchEl.value.trim();
        if (q === '') {
            resultsEl.classList.add('hidden');
            return;
        }
        debounceTimer = setTimeout(function() { doSearch(q); }, 300);
    });

    searchEl.addEventListener('focus', function() {
        var q = searchEl.value.trim();
        if (q !== '') { resultsEl.classList.remove('hidden'); }
    });

    resultsEl.addEventListener('click', function(e) {
        var li = e.target.closest('li[data-item]');
        if (!li) return;
        var item = JSON.parse(li.dataset.item);
        selectItem(item);
    });

    clearBtn.addEventListener('click', function() {
        searchEl.value = '';
        selectItem(null);
        searchEl.focus();
    });

    document.addEventListener('click', function(e) {
        if (!resultsEl.contains(e.target) && e.target !== searchEl) {
            resultsEl.classList.add('hidden');
        }
    });

    window.RkasPicker = {
        onSelect: null,
        getSelected: function() { return selectedItem; },
        setSelected: function(item) { selectItem(item); },
        init: function() {
            if (selectedItem) {
                searchEl.value = selectedItem.text || '';
                renderDetail(selectedItem);
                if (typeof this.onSelect === 'function') {
                    this.onSelect(selectedItem);
                }
            }
        }
    };
})();
</script>
