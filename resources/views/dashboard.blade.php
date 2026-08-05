<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Dashboard</div>
    </x-slot>

    @if(!$tahunAnggaranAktif)
        <div class="alert-warning mb-6">
            <svg aria-hidden="true" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Tahun anggaran belum diaktifkan. Silakan aktifkan di menu <a href="{{ route('tahun-anggaran.index') }}" class="underline font-semibold hover:text-amber-900">Tahun Anggaran</a> terlebih dahulu.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card indigo">
            <div class="stat-icon bg-indigo-50">
                <svg aria-hidden="true" class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="stat-label">Total Rencana Anggaran</div>
            <div class="stat-value text-indigo-700">Rp {{ number_format($totalRencana, 0, ',', '.') }}</div>
        </div>

        <div class="stat-card blue">
            <div class="stat-icon bg-blue-50">
                <svg aria-hidden="true" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <div class="stat-label">Total Realisasi</div>
            <div class="stat-value text-blue-700">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon bg-emerald-50">
                <svg aria-hidden="true" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="stat-label">Sisa Anggaran</div>
            <div class="stat-value text-emerald-700">Rp {{ number_format($totalSisa, 0, ',', '.') }}</div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon bg-amber-50">
                <svg aria-hidden="true" class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="stat-label">Persentase Capaian</div>
            <div class="stat-value text-amber-700">{{ $persentaseCapaian }}%</div>
            <div class="progress-bar">
                <div class="progress-fill bg-amber-400" style="width: {{ min(100, $persentaseCapaian) }}%"></div>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Filter Dashboard</span>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="px-6 py-4 bg-slate-50 border-b border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select" onchange="this.form.submit()">
                        @foreach($tahunList as $t)
                            <option value="{{ $t->tahun }}" {{ request('tahun', $tahunAnggaranAktif->tahun ?? '') == $t->tahun ? 'selected' : '' }}>
                                {{ $t->tahun }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <option value="">Semua Bulan</option>
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ request('bulan') == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">Semua Program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" {{ request('program_id') == $program->id ? 'selected' : '' }}>
                                {{ $program->kode }} - {{ $program->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Kode Rekening</label>
                    <select name="kode_rekening_id" class="form-select">
                        <option value="">Semua Rekening</option>
                        @foreach($kodeRekenings as $kr)
                            <option value="{{ $kr->id }}" {{ request('kode_rekening_id') == $kr->id ? 'selected' : '' }}>
                                {{ $kr->kode }} - {{ $kr->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Sumber Dana</label>
                    <select name="sumber_dana_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Sumber Dana</option>
                        @foreach($sumberDanas as $sd)
                            <option value="{{ $sd->id }}" {{ request('sumber_dana_id', $sumberDanaId ?? '') == $sd->id ? 'selected' : '' }}>
                                {{ $sd->kode }} - {{ $sd->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Jenis Belanja</label>
                    <div class="flex gap-2">
                        <select name="jenis_belanja_id" class="form-select flex-1">
                            <option value="">Semua</option>
                            @foreach($jenisBelanjas as $jenisB)
                                <option value="{{ $jenisB->id }}" {{ request('jenis_belanja_id') == $jenisB->id ? 'selected' : '' }}>
                                    {{ $jenisB->nama }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary btn-sm whitespace-nowrap">Filter</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Detail Rencana &amp; Realisasi per Item</span>
        </div>
        <div class="overflow-x-auto">
            @if($rkasItems->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Uraian</th>
                        <th>Program</th>
                        <th>Kode Rekening</th>
                        <th class="text-right whitespace-nowrap" style="min-width:130px">Rencana</th>
                        <th class="text-right whitespace-nowrap" style="min-width:130px">Realisasi</th>
                        <th class="text-right whitespace-nowrap" style="min-width:130px">Sisa</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rkasItems as $item)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-800 text-sm">{{ $item->uraian }}</div>
                            <div class="text-xs text-slate-400">No. {{ $loop->iteration }}</div>
                        </td>
                        <td>
                            @if($item->program)
                                <div class="font-medium text-slate-700 text-xs">{{ $item->program->kode }}</div>
                                <div class="text-xs text-slate-400">{{ $item->program->nama }}</div>
                            @else
                                <span class="text-slate-400 text-xs">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <div class="font-mono text-xs text-slate-600">{{ $item->kodeRekening->kode ?? '-' }}</div>
                            <span class="badge badge-blue mt-1">{{ $item->kodeRekening->jenisBelanja->nama ?? '-' }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="font-semibold text-slate-700 text-sm">Rp {{ number_format($item->dynamic_rencana, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-400">{{ $item->dynamic_rencana_volume }} {{ $item->satuan }}</div>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="font-semibold text-blue-600 text-sm">Rp {{ number_format($item->dynamic_realisasi, 0, ',', '.') }}</div>
                            <div class="text-xs text-blue-300">{{ $item->dynamic_realisasi_volume }} {{ $item->satuan }}</div>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="font-semibold text-sm {{ $item->dynamic_sisa >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                Rp {{ number_format($item->dynamic_sisa, 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="text-center">
                            @if($item->persentase > 100)
                                <span class="badge badge-red">Over Budget ({{ number_format($item->persentase, 0) }}%)</span>
                            @elseif($item->persentase >= 90)
                                <span class="badge badge-orange">Hampir Habis ({{ number_format($item->persentase, 0) }}%)</span>
                            @elseif($item->persentase == 0)
                                <span class="badge badge-yellow">Belum Realisasi</span>
                            @else
                                <span class="badge badge-green">Normal ({{ number_format($item->persentase, 0) }}%)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if(method_exists($rkasItems, 'links'))
                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $rkasItems->links() }}
                </div>
            @endif
            @else
            <div class="text-center py-12 text-slate-400">
                <p class="text-sm">Belum ada data RKAS.</p>
            </div>
            @endif
        </div>
    </div>

    @if($transaksiBulanIni == 0)
        <div class="alert-warning mb-6">
            <svg aria-hidden="true" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Belum ada transaksi BKU bulan {{ \Carbon\Carbon::now()->translatedFormat('F') }}. Silakan segera input transaksi agar data Realisasi Anggaran tetap up-to-date.</span>
        </div>
    @endif

    @if($tahunAnggaranAktif && $importStatus->isNotEmpty())
    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Status Import RKAS</span>
        </div>
        <div class="card-body">
            @php
                $importedCount = $importStatus->filter(fn($s) => $s->status === 'success')->count();
            @endphp
            <div class="flex items-center gap-3 mb-4">
                <div class="text-sm text-slate-600">{{ $importedCount }} / 12 bulan berhasil diimport</div>
                <div class="flex-1 bg-slate-100 rounded-full h-2">
                    <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: {{ round($importedCount / 12 * 100) }}%"></div>
                </div>
                <div class="text-xs font-semibold text-slate-500">{{ round($importedCount / 12 * 100) }}%</div>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                @foreach($importStatus as $imp)
                <div class="block rounded-xl border p-3 text-center transition-all hover:shadow-md
                    {{ $imp->status === 'success' ? 'border-emerald-200 bg-emerald-50' : ($imp->status === 'failed' ? 'border-red-200 bg-red-50' : ($imp->status === 'processing' || $imp->status === 'pending' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50')) }}">
                    <div class="text-xs font-medium text-slate-500 mb-1">{{ $imp->nama }}</div>
                    @if($imp->status === 'success')
                        <svg aria-hidden="true" class="w-5 h-5 mx-auto text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="text-xs text-emerald-600 font-medium mt-1">{{ $imp->baris_berhasil }} baris</div>
                    @elseif($imp->status === 'failed')
                        <svg aria-hidden="true" class="w-5 h-5 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="text-xs text-red-600 font-medium mt-1">Gagal</div>
                    @elseif($imp->status === 'processing' || $imp->status === 'pending')
                        <svg aria-hidden="true" class="w-5 h-5 mx-auto text-amber-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <div class="text-xs text-amber-600 font-medium mt-1">Proses</div>
                    @else
                        <svg aria-hidden="true" class="w-5 h-5 mx-auto text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        <div class="text-xs text-slate-500 font-medium mt-1">-</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Tren Realisasi Per Bulan</span>
            </div>
            <div class="card-body">
                <canvas id="trenChart" style="max-height:240px"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Realisasi per Jenis Belanja</span>
            </div>
            <div class="card-body flex items-center justify-center">
                @if(count($chartValues) > 0)
                    <canvas id="jenisBelanjaChart" style="max-height:240px"></canvas>
                @else
                    <div class="text-center py-8 text-slate-400">
                        <p class="text-sm">Belum ada realisasi</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Transaksi Terkini</span>
            </div>
            <div class="overflow-x-auto">
                @if($recentTransaksi->count() > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Uraian</th>
                            <th>Item RKAS</th>
                            <th class="text-right whitespace-nowrap" style="min-width:120px">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransaksi as $trx)
                        <tr>
                            <td class="text-sm text-slate-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($trx->created_at)->translatedFormat('d M Y') }}
                            </td>
                            <td>
                                <div class="font-medium text-slate-800 text-sm">{{ Str::limit($trx->uraian ?? '-', 40) }}</div>
                                <div class="text-xs text-slate-400">{{ $trx->rkasItem->program->nama ?? '-' }} / {{ $trx->rkasItem->kodeRekening->jenisBelanja->nama ?? '-' }}</div>
                            </td>
                            <td class="text-sm text-slate-600">{{ Str::limit($trx->rkasItem->uraian ?? '-', 40) }}</td>
                            <td class="text-right font-semibold text-blue-600 text-sm whitespace-nowrap">
                                Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="text-center py-8 text-slate-400">
                    <p class="text-sm">Belum ada transaksi pengeluaran</p>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Ringkasan Capaian</span>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-600">Realisasi / Rencana</span>
                        <span class="font-semibold text-slate-800">{{ $persentaseCapaian }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-3">
                        <div class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-3 rounded-full transition-all duration-500" style="width: {{ min(100, $persentaseCapaian) }}%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Rencana</div>
                        <div class="font-bold text-indigo-700 text-sm">Rp {{ number_format($totalRencana, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Realisasi</div>
                        <div class="font-bold text-emerald-700 text-sm">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="bg-slate-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-slate-400 mb-1">Sisa Anggaran</div>
                    <div class="font-bold {{ $totalSisa >= 0 ? 'text-emerald-600' : 'text-red-600' }} text-sm">Rp {{ number_format($totalSisa, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const bulanLabels = @json(array_map(fn ($m) => \Carbon\Carbon::create()->month($m)->translatedFormat('F'), range(1, 12)));
        const bulanValues = @json($trenBulanValues);

        new Chart(document.getElementById('trenChart'), {
            type: 'bar',
            data: {
                labels: bulanLabels,
                datasets: [{
                    label: 'Realisasi per Bulan',
                    data: bulanValues,
                    backgroundColor: 'rgba(99,102,241,0.75)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(v); }
                        },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    }
                }
            }
        });

        @if(count($chartValues) > 0)
        new Chart(document.getElementById('jenisBelanjaChart'), {
            type: 'doughnut',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    data: @json($chartValues),
                    backgroundColor: ['#6366f1','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(ctx.parsed);
                            }
                        }
                    }
                }
            }
        });
        @endif
    });
    </script>
</x-app-layout>
