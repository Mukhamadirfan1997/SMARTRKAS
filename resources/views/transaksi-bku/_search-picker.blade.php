@php
    $spId = $spPrefix . '_id';
    $spCompact = $spCompact ?? false;
@endphp
<div>
    @if(!$spCompact && !empty($spLabel))
    <label for="{{ $spPrefix }}_search" class="form-label">{{ $spLabel }}@if($spRequired ?? true) <span class="text-red-500">*</span>@endif</label>
    @endif
    <div class="relative">
        <input type="text" id="{{ $spPrefix }}_search" class="{{ $spCompact ? 'form-input py-1.5 text-sm' : 'form-input' }} pr-8" placeholder="{{ $spPlaceholder }}" autocomplete="off">
        <input type="hidden" name="{{ $spId }}" id="{{ $spId }}" value="{{ $spInitial }}">
        @if($spCompact)
            <button type="button" id="{{ $spPrefix }}_clear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400 hover:text-red-500" title="Bersihkan pilihan">&times;</button>
        @endif
        <div id="{{ $spPrefix }}_results" class="hidden absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
    </div>
    @if(!$spCompact)
    <div class="flex items-center justify-between mt-1">
        <p class="text-xs text-slate-400" id="{{ $spPrefix }}_status">{{ $spStatusHint ?? ('Ketik untuk memilih ' . $spLabelLower . '...') }}</p>
        <button type="button" id="{{ $spPrefix }}_clear" class="hidden text-xs font-medium text-slate-400 hover:text-red-500" title="Bersihkan pilihan">Bersihkan</button>
    </div>
    @endif
    @if($spShowError ?? true)
        @error($spError)
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>

<script>
(function () {
    if (window.__entityPickerInit) return;
    window.__entityPickerInit = true;

    window.initEntityPicker = function (cfg) {
        var searchEl = document.getElementById(cfg.searchId);
        var hiddenEl = document.getElementById(cfg.hiddenId);
        var resultsEl = document.getElementById(cfg.resultsId);
        var clearEl = cfg.clearId ? document.getElementById(cfg.clearId) : null;
        var statusEl = cfg.statusId ? document.getElementById(cfg.statusId) : null;
        if (!searchEl || !hiddenEl) return null;

        var options = cfg.options || [];

        function findOpt(id) {
            var s = String(id);
            for (var i = 0; i < options.length; i++) {
                if (String(options[i].id) === s) return options[i];
            }
            return null;
        }

        function renderResults(list) {
            if (!list.length) {
                resultsEl.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Tidak ada data cocok.</div>';
                return;
            }
            var ul = document.createElement('ul');
            ul.className = 'divide-y divide-slate-100';
            list.forEach(function (opt) {
                var li = document.createElement('li');
                li.className = 'px-3 py-2 hover:bg-indigo-50 cursor-pointer text-sm text-slate-700';
                li.textContent = opt.text;
                li.addEventListener('click', function () { select(opt); });
                ul.appendChild(li);
            });
            resultsEl.innerHTML = '';
            resultsEl.appendChild(ul);
        }

        function doFilter() {
            var needle = searchEl.value.trim().toLowerCase();
            if (needle === '') { resultsEl.classList.add('hidden'); return; }
            var list = options.filter(function (o) { return o.text.toLowerCase().indexOf(needle) !== -1; });
            resultsEl.classList.remove('hidden');
            renderResults(list);
        }

        function select(opt) {
            hiddenEl.value = opt.id;
            searchEl.value = opt.text;
            resultsEl.classList.add('hidden');
            if (clearEl) clearEl.classList.remove('hidden');
            if (statusEl) statusEl.classList.add('hidden');
            document.dispatchEvent(new CustomEvent('entitypicker:change', { detail: { id: cfg.hiddenId, value: opt.id } }));
            if (typeof cfg.onSelect === 'function') cfg.onSelect(opt);
            if (cfg.autoSubmit) { var f = searchEl.closest('form'); if (f) f.submit(); }
        }

        function clear() {
            hiddenEl.value = '';
            searchEl.value = '';
            resultsEl.classList.add('hidden');
            if (clearEl) clearEl.classList.add('hidden');
            if (statusEl) statusEl.classList.remove('hidden');
            document.dispatchEvent(new CustomEvent('entitypicker:change', { detail: { id: cfg.hiddenId, value: '' } }));
            if (cfg.autoSubmit) { var f = searchEl.closest('form'); if (f) f.submit(); }
        }

        var debounce = null;
        searchEl.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(doFilter, 150);
        });
        searchEl.addEventListener('focus', function () {
            if (searchEl.value.trim() !== '') doFilter();
        });
        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var first = resultsEl.querySelector('li');
                if (first) { e.preventDefault(); first.click(); }
            }
        });
        document.addEventListener('click', function (e) {
            if (!resultsEl.contains(e.target) && e.target !== searchEl) resultsEl.classList.add('hidden');
        });
        if (clearEl) clearEl.addEventListener('click', clear);

        var initial = findOpt(hiddenEl.value);
        if (initial) {
            searchEl.value = initial.text;
            if (clearEl) clearEl.classList.remove('hidden');
            if (statusEl) statusEl.classList.add('hidden');
        }

        return {
            getValue: function () { return hiddenEl.value; },
            setValue: function (id) { var o = findOpt(id); if (o) select(o); }
        };
    };
})();
</script>

<script>
(function () {
    window.initEntityPicker({
        searchId: '{{ $spPrefix }}_search',
        hiddenId: '{{ $spId }}',
        resultsId: '{{ $spPrefix }}_results',
        clearId: '{{ $spPrefix }}_clear',
        statusId: {!! $spCompact ? 'null' : "'{$spPrefix}_status'" !!},
        autoSubmit: {{ ($spAutoSubmit ?? false) ? 'true' : 'false' }},
        options: @json($spOptions)
    });
})();
</script>
