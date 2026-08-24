<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="page-title">{{ __('Monitoring Kepatuhan Juknis BOSP') }}</div>
            <p class="text-sm text-slate-500 mt-0.5">Proporsi rencana &amp; realisasi terhadap Total Pagu dibanding batas juknis.</p>
        </div>
    </x-slot>

        {{-- Filter tahun --}}
        <div class="card mb-6">
            <div class="card-body py-4">
                <form method="GET" action="{{ route('laporan.monitoring-juknis') }}" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="basis" value="{{ $basis }}">
                    <label for="tahun" class="text-sm font-medium text-slate-600">Tahun Anggaran</label>
                    <select name="tahun" id="tahun" class="form-select w-auto" onchange="this.form.submit()">
                        @forelse($tahunList as $ta)
                            <option value="{{ $ta->tahun }}" {{ ($tahunAnggaranAktif?->tahun ?? null) === $ta->tahun ? 'selected' : '' }}>
                                {{ $ta->tahun }}{{ $ta->status ? ' (aktif)' : '' }}
                            </option>
                        @empty
                            <option value="">-</option>
                        @endforelse
                    </select>
                </form>
            </div>
        </div>

        @if (! $tahunAnggaranAktif)
            <div class="alert alert-warning">
                Belum ada tahun anggaran. Tambahkan terlebih dahulu di menu Referensi &amp; Master → Tahun Anggaran.
            </div>
        @else

            {{-- Kartu ringkasan atas: Total Pagu + toggle basis --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                <div class="stat-card blue">
                    <div class="stat-icon bg-blue-50">
                        <svg aria-hidden="true" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="stat-label">Total Pagu {{ $tahunAnggaranAktif->tahun }}</div>
                        <div class="stat-value text-slate-900">Rp {{ number_format($totalPagu, 0, ',', '.') }}</div>
                    </div>
                </div>

                <div class="card flex flex-col justify-center">
                    <div class="px-6 py-4">
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Dasar Perhitungan Persentase</div>
                        <div class="inline-flex rounded-lg overflow-hidden border border-slate-200">
                            <a href="{{ request()->fullUrlWithQuery(['basis' => 'rencana']) }}"
                               class="px-4 py-2 text-sm font-medium transition-colors {{ $basis === 'rencana' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                                Berdasarkan Rencana
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['basis' => 'realisasi']) }}"
                               class="px-4 py-2 text-sm font-medium transition-colors {{ $basis === 'realisasi' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                                Berdasarkan Realisasi
                            </a>
                        </div>
                        <p class="text-xs text-slate-400 mt-3">
                            Persentase = {{ $basis === 'rencana' ? 'rencana item RKAS' : 'realisasi pengeluaran' }} pada kode rekening ter-mapping ÷ Total Pagu.
                        </p>
                    </div>
                </div>
            </div>

            @if ($belumDikategorikanCount > 0)
                <div class="alert alert-info mb-6">
                    {{ $belumDikategorikanCount }} kode rekening yang punya {{ $basis === 'rencana' ? 'rencana' : 'realisasi' }} belum dikategorikan dan tidak ikut dihitung dalam kategori mana pun.
                    <a href="{{ route('pengaturan.kategori-juknis.pemetaan') }}" class="underline font-semibold ml-1">Petakan sekarang</a>.
                </div>
            @endif

            {{-- Kartu per kategori juknis --}}
            @if ($kategoriCards->isEmpty())
                <div class="card mb-6">
                    <div class="card-body text-center py-12 text-slate-400">
                        <p class="text-sm font-medium">Belum ada kategori juknis yang dipetakan ke kode rekening.</p>
                        <p class="text-xs mt-1">Atur di menu Pengaturan → Kategori Juknis BOSP.</p>
                        <a href="{{ route('pengaturan.kategori-juknis.pemetaan') }}" class="btn btn-primary btn-sm mt-4 inline-block">Pemetaan Kode Rekening</a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
                    @foreach ($kategoriCards as $card)
                        @php
                            $statusColor = match ($card['status']) {
                                'sesuai' => '#10b981',
                                'melebihi' => '#ef4444',
                                'kurang' => '#f59e0b',
                                default => '#64748b',
                            };
                        @endphp
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">{{ $card['nama'] }}</span>
                                <span class="badge {{ $card['arah'] === 'minimal' ? 'badge-green' : 'badge-red' }} whitespace-nowrap">
                                    {{ $card['arah'] === 'minimal' ? 'Minimal' : 'Maksimal' }} {{ rtrim(rtrim(number_format($card['batas'], 2, ',', '.'), '0'), ',') }}%
                                </span>
                            </div>
                            <div class="card-body space-y-4">
                                <div class="relative w-44 h-44 mx-auto">
                                    <canvas id="juknis-donut-{{ $loop->index }}" width="176" height="176"></canvas>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                        <div class="text-2xl font-bold" style="color: {{ $statusColor }}">{{ number_format($card['persen'], 1, ',', '.') }}%</div>
                                        <div class="text-[10px] text-slate-400">dari Total Pagu</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-center">
                                    <div class="bg-slate-50 rounded-lg p-2">
                                        <div class="text-[10px] text-slate-400 mb-0.5">Nominal</div>
                                        <div class="text-sm font-bold text-slate-800">Rp {{ number_format($card['nominal'], 0, ',', '.') }}</div>
                                    </div>
                                    <div class="bg-slate-50 rounded-lg p-2">
                                        <div class="text-[10px] text-slate-400 mb-0.5">Kode Rekening</div>
                                        <div class="text-sm font-bold text-slate-800">{{ $card['jumlah_rekening'] }} kode</div>
                                    </div>
                                </div>

                                @if ($card['status'] === 'sesuai')
                                    <div class="rounded-lg px-3 py-2 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Sudah sesuai juknis ({{ $card['arah'] === 'minimal' ? 'minimal' : 'maksimal' }} {{ rtrim(rtrim(number_format($card['batas'], 2, ',', '.'), '0'), ',') }}%).
                                    </div>
                                @elseif ($card['status'] === 'melebihi')
                                    <div class="rounded-lg px-3 py-2 text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                        Melebihi batas maksimal {{ rtrim(rtrim(number_format($card['batas'], 2, ',', '.'), '0'), ',') }}%.
                                    </div>
                                @else
                                    <div class="rounded-lg px-3 py-2 text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Belum mencapai minimal {{ rtrim(rtrim(number_format($card['batas'], 2, ',', '.'), '0'), ',') }}%.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Bonus: proporsi antar jenis belanja --}}
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Proporsi Antar Jenis Belanja ({{ $basis === 'rencana' ? 'Rencana' : 'Realisasi' }})</span>
                    <span class="text-xs text-slate-400">Informasi &mdash; dari jenis belanja kode rekening</span>
                </div>
                <div class="card-body">
                    @if ($jenisBelanjaBreakdown->isEmpty())
                        <div class="text-center py-8 text-slate-400">
                            <p class="text-sm">Belum ada data {{ $basis === 'rencana' ? 'rencana' : 'realisasi' }} pada tahun anggaran ini.</p>
                        </div>
                    @else
                        <div class="flex flex-col md:flex-row items-center gap-6">
                            <div class="relative w-56 h-56 flex-shrink-0">
                                <canvas id="jenis-belanja-donut" width="224" height="224"></canvas>
                            </div>
                            <div class="w-full space-y-2">
                                @foreach ($jenisBelanjaBreakdown as $jb)
                                    <div class="flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 jb-dot" data-index="{{ $loop->index }}"></span>
                                            <span class="text-slate-600 truncate">{{ $jb['label'] }}</span>
                                        </div>
                                        <div class="flex-shrink-0 ml-3">
                                            <span class="font-semibold text-slate-800">Rp {{ number_format($jb['total'], 0, ',', '.') }}</span>
                                            <span class="text-xs text-slate-400">({{ number_format($jb['persen'], 1, ',', '.') }}%)</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    @if ($tahunAnggaranAktif && $kategoriCards->isNotEmpty())
        @php
            $donutDataJs = $kategoriCards->map(fn ($c) => [
                'nominal' => round((float) $c['nominal'], 2),
                'sisa' => round(max($totalPagu - (float) $c['nominal'], 0), 2),
                'status' => $c['status'],
            ])->values();
            $jenisDataJs = $jenisBelanjaBreakdown->map(fn ($jb) => ['total' => round($jb['total'], 2)]);
        @endphp
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var statusColor = { sesuai: '#10b981', melebihi: '#ef4444', kurang: '#f59e0b' };
                var sisaColor = '#e2e8f0';

                var kategoriData = @json($donutDataJs);
                kategoriData.forEach(function (k, i) {
                    var el = document.getElementById('juknis-donut-' + i);
                    if (!el || typeof Chart === 'undefined') return;
                    new Chart(el, {
                        type: 'doughnut',
                        data: {
                            labels: ['Kategori', 'Sisanya'],
                            datasets: [{
                                data: [k.nominal, k.sisa],
                                backgroundColor: [statusColor[k.status] || '#64748b', sisaColor],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            cutout: '68%',
                            plugins: { legend: { display: false }, tooltip: { enabled: true } },
                            animation: { duration: 500 },
                        },
                    });
                });

                var jenisEl = document.getElementById('jenis-belanja-donut');
                var jenisData = @json($jenisDataJs);
                if (jenisEl && jenisData.length > 0 && typeof Chart !== 'undefined') {
                    var palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#14b8a6'];
                    var colors = jenisData.map(function (_, i) { return palette[i % palette.length]; });
                    document.querySelectorAll('.jb-dot').forEach(function (dot) {
                        var idx = parseInt(dot.getAttribute('data-index'), 10);
                        if (!isNaN(idx)) dot.style.backgroundColor = colors[idx] || '#64748b';
                    });
                    new Chart(jenisEl, {
                        type: 'doughnut',
                        data: {
                            datasets: [{ data: jenisData.map(function (j) { return j.total; }), backgroundColor: colors, borderWidth: 0 }],
                        },
                        options: {
                            cutout: '60%',
                            plugins: { legend: { display: false } },
                            animation: { duration: 500 },
                        },
                    });
                }
            });
        </script>
    @elseif ($tahunAnggaranAktif && $jenisBelanjaBreakdown->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var jenisEl = document.getElementById('jenis-belanja-donut');
                var jenisData = @json($jenisBelanjaBreakdown->map(fn ($jb) => ['total' => round($jb['total'], 2)]));
                if (jenisEl && jenisData.length > 0 && typeof Chart !== 'undefined') {
                    var palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#14b8a6'];
                    var colors = jenisData.map(function (_, i) { return palette[i % palette.length]; });
                    document.querySelectorAll('.jb-dot').forEach(function (dot) {
                        var idx = parseInt(dot.getAttribute('data-index'), 10);
                        if (!isNaN(idx)) dot.style.backgroundColor = colors[idx] || '#64748b';
                    });
                    new Chart(jenisEl, {
                        type: 'doughnut',
                        data: {
                            datasets: [{ data: jenisData.map(function (j) { return j.total; }), backgroundColor: colors, borderWidth: 0 }],
                        },
                        options: {
                            cutout: '60%',
                            plugins: { legend: { display: false } },
                            animation: { duration: 500 },
                        },
                    });
                }
            });
        </script>
    @endif
</x-app-layout>
