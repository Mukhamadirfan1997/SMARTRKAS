<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="page-title">
                Data RKAS
                @if($tahunAnggaranAktif)
                    <span class="text-slate-400 font-normal">({{ $tahunAnggaranAktif->tahun }})</span>
                @endif
            </div>
        </div>
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

    @if(!$tahunAnggaranAktif)
        <div class="alert-warning mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Silakan aktifkan tahun anggaran terlebih dahulu di menu Tahun Anggaran.
        </div>
    @endif

    @if($totalJumlah > 0)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
            <div class="stat-card indigo">
                <div class="stat-icon bg-indigo-50">
                    <svg aria-hidden="true" class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="stat-label">Total Item</div>
                <div class="stat-value text-indigo-700">{{ number_format($rkasItems->total()) }}</div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon bg-emerald-50">
                    <svg aria-hidden="true" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-label">Total Rencana</div>
                <div class="stat-value text-emerald-700">Rp {{ number_format($totalJumlah, 0, ',', '.') }}</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon bg-blue-50">
                    <svg aria-hidden="true" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div class="stat-label">Total Realisasi</div>
                <div class="stat-value text-blue-700">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon bg-amber-50">
                    <svg aria-hidden="true" class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="stat-label">Belum Lengkap</div>
                <div class="stat-value text-amber-700">{{ number_format($belumLengkapCount) }}</div>
            </div>
        </div>
    @endif

    @if($totalJumlah > 0 || $jenisBelanjaRealisasi->isNotEmpty())
        @php $totalSisa = $totalJumlah - $totalRealisasi; @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
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
                            <div class="font-bold text-indigo-700 text-sm">Rp {{ number_format($totalJumlah, 0, ',', '.') }}</div>
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

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Realisasi per Jenis Belanja</span>
                </div>
                <div class="card-body">
                    @if($jenisBelanjaRealisasi->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($jenisBelanjaRealisasi as $jb)
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-slate-600">{{ $jb['label'] }}</span>
                                        <span class="font-semibold text-slate-800">Rp {{ number_format($jb['total'], 0, ',', '.') }} ({{ $jb['persen'] }}%)</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $jb['persen']) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-slate-400">
                            <p class="text-sm">Belum ada realisasi</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar RKAS</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('import-rkas.index') }}" class="btn-primary btn-sm">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import Excel
                </a>
                <button type="button" class="btn btn-danger btn-sm" onclick="hapusSemuaRkas()">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Semua
                </button>
            </div>
        </div>
        <form method="GET" action="{{ route('rkas.index') }}" class="px-6 py-4 bg-slate-50 border-b border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="form-label">Cari Uraian</label>
                    <input type="text" name="search" id="search" class="form-input" placeholder="Cari uraian item..." value="{{ request('search') }}">
                </div>
                <div>
                    <label for="bulan" class="form-label">Bulan</label>
                    <select name="bulan" id="bulan" class="form-select">
                        <option value="">Semua Bulan</option>
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ request('bulan', $bulan) == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="program_search" class="form-label">Program</label>
                    @include('transaksi-bku._search-picker', [
                        'spPrefix' => 'program',
                        'spLabel' => '',
                        'spLabelLower' => 'program',
                        'spRequired' => false,
                        'spCompact' => true,
                        'spPlaceholder' => 'Cari program (kode / nama)...',
                        'spInitial' => (string) ($programId ?? ''),
                        'spError' => 'program_id',
                        'spAutoSubmit' => false,
                        'spOptions' => $programs->map(fn ($p) => ['id' => (string) $p->id, 'text' => $p->kode . ' - ' . $p->nama])->values()->all(),
                    ])
                </div>
                <div>
                    <label for="tahun" class="form-label">Tahun</label>
                    <select name="tahun" id="tahun" class="form-select">
                        @foreach($tahunList as $t)
                            <option value="{{ $t->tahun }}" {{ request('tahun', $tahunAnggaranAktif->tahun ?? '') == $t->tahun ? 'selected' : '' }}>
                                {{ $t->tahun }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sumber_dana_id" class="form-label">Sumber Dana</label>
                    <select name="sumber_dana_id" id="sumber_dana_id" class="form-select">
                        <option value="">Semua Sumber Dana</option>
                        @foreach($sumberDanaList as $sd)
                            <option value="{{ $sd->id }}" {{ request('sumber_dana_id', $sumberDanaId ?? '') == $sd->id ? 'selected' : '' }}>
                                {{ $sd->kode }} - {{ $sd->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="kode_rekening_search" class="form-label">Kode Rekening</label>
                    @include('transaksi-bku._search-picker', [
                        'spPrefix' => 'kode_rekening',
                        'spLabel' => '',
                        'spLabelLower' => 'kode rekening',
                        'spRequired' => false,
                        'spCompact' => true,
                        'spPlaceholder' => 'Cari rekening (kode / nama)...',
                        'spInitial' => (string) ($kodeRekeningId ?? ''),
                        'spError' => 'kode_rekening_id',
                        'spAutoSubmit' => false,
                        'spOptions' => $kodeRekenings->map(fn ($r) => ['id' => (string) $r->id, 'text' => $r->kode . ' - ' . $r->nama])->values()->all(),
                    ])
                </div>
                <div>
                    <label for="jenis_belanja_id" class="form-label">Jenis Belanja</label>
                    <div class="flex gap-2">
                        <select name="jenis_belanja_id" id="jenis_belanja_id" class="form-select flex-1">
                            <option value="">Semua Jenis Belanja</option>
                            @foreach($jenisBelanjas as $jb)
                                <option value="{{ $jb->id }}" {{ request('jenis_belanja_id', $jenisBelanjaId ?? '') == $jb->id ? 'selected' : '' }}>
                                    {{ $jb->nama }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary btn-sm whitespace-nowrap">Filter</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            @if($rkasItems->count() > 0)
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-10">No</th>
                            <th>Uraian</th>
                            <th class="w-40">Program</th>
                            <th class="w-40">Kode Rekening</th>
                            <th class="w-24">Sumber Dana</th>
                            <th class="text-right whitespace-nowrap" style="min-width:140px">Rencana</th>
                            <th class="text-right whitespace-nowrap" style="min-width:140px">Realisasi</th>
                            <th class="text-right whitespace-nowrap" style="min-width:140px">Sisa</th>
                            <th class="text-center w-32">Status</th>
                            <th class="text-center w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rkasItems as $item)
                            @php
                                $isLengkap = $item->program_id && $item->kode_rekening_id;
                                $rencanaBulan = $bulan ? (float) ($item->bulanRencana->first()?->rencana ?? 0) : null;
                                $rencana = $rencanaBulan ?? (float) $item->jumlah;
                                $realisasi = $item->transaksiBkus->sum('jumlah') + $item->notaBkuItems->sum('subtotal');
                                $sisa = $rencana - $realisasi;
                                $persen = $rencana > 0 ? ($realisasi / $rencana) * 100 : 0;
                                $rencanaVolume = ($rencana > 0 && $item->tarif > 0) ? round($rencana / $item->tarif, 2) : null;
                                $realisasiVolume = ($realisasi > 0 && $item->tarif > 0) ? round($realisasi / $item->tarif, 2) : 0;
                                $sisaVolume = $rencanaVolume !== null ? $rencanaVolume - $realisasiVolume : null;
                                $satuan = $item->satuan ?: 'item';
                                $subRencana = ($rencanaVolume !== null && $rencanaVolume > 0 && $item->tarif > 0)
                                    ? number_format($rencanaVolume, 0, ',', '.') . ' ' . $satuan . ' × Rp ' . number_format($item->tarif, 0, ',', '.')
                                    : '';
                            @endphp
                            <tr class="{{ $isLengkap ? '' : 'bg-amber-50/50' }}">
                                <td class="font-semibold text-slate-700">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="font-medium text-slate-800">{{ $item->uraian }}</div>
                                    <div class="text-xs text-slate-400">No. {{ $item->no_urut ?? $loop->iteration }}</div>
                                    @if(!$isLengkap)
                                        <span class="badge badge-yellow mt-1">Perlu koreksi</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->program)
                                        <div class="font-medium text-slate-700 text-xs">{{ $item->program->kode }}</div>
                                        <div class="text-xs text-slate-400 line-clamp-2">{{ $item->program->nama }}</div>
                                    @else
                                        <span class="badge badge-red">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->kodeRekening)
                                        <div class="font-mono font-medium text-slate-700 text-xs">{{ $item->kodeRekening->kode }}</div>
                                        <div class="text-xs text-slate-400 line-clamp-2">{{ $item->kodeRekening->nama }}</div>
                                        <span class="badge badge-blue mt-1">{{ $item->kodeRekening->jenisBelanja->nama ?? 'Belum dikategorikan' }}</span>
                                    @else
                                        <span class="badge badge-red">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->sumberDana)
                                        <span class="badge badge-blue whitespace-nowrap text-xs">{{ $item->sumberDana->kode }}</span>
                                    @else
                                        <span class="text-slate-300 text-xs">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="font-semibold text-slate-700">Rp {{ number_format($rencana, 0, ',', '.') }}</div>
                                    @if($subRencana)
                                        <div class="text-xs text-slate-400">{{ $subRencana }}</div>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="font-semibold text-blue-600">Rp {{ number_format($realisasi, 0, ',', '.') }}</div>
                                    @if($realisasiVolume > 0)
                                        <div class="text-xs text-blue-300">{{ number_format($realisasiVolume, 0, ',', '.') }} {{ $satuan }}</div>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="font-semibold {{ $sisa >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                        Rp {{ number_format($sisa, 0, ',', '.') }}
                                    </div>
                                    @if($sisaVolume !== null)
                                        <div class="text-xs {{ $sisaVolume >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            sisa {{ number_format($sisaVolume, 0, ',', '.') }} {{ $satuan }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($persen > 100)
                                        <span class="badge badge-red">Over Budget ({{ number_format($persen, 0) }}%)</span>
                                    @elseif($persen >= 90)
                                        <span class="badge badge-orange">Hampir Habis ({{ number_format($persen, 0) }}%)</span>
                                    @elseif($persen == 0)
                                        @if(!$isLengkap)
                                            <span class="badge badge-yellow">Koreksi</span>
                                        @else
                                            <span class="badge badge-yellow">Belum Realisasi</span>
                                        @endif
                                    @else
                                        <span class="badge badge-green">Normal ({{ number_format($persen, 0) }}%)</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('rkas.edit', $item) }}" class="btn btn-secondary btn-xs" title="Edit">
                                            <svg aria-hidden="true" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>
                                        <form action="{{ route('rkas.destroy', $item) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs" title="Hapus" onclick="return confirm('Yakin ingin menghapus item RKAS ini?')">
                                                <svg aria-hidden="true" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $rkasItems->links() }}
                </div>
            @else
                <div class="text-center py-12 text-slate-400">
                    <svg aria-hidden="true" class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm">Belum ada data RKAS. Silakan import file Excel.</p>
                </div>
            @endif
        </div>

        <form id="form-hapus-semua-rkas" method="POST" action="{{ route('rkas.hapus-semua') }}" class="hidden">
            @csrf
            <input type="hidden" name="search" value="{{ request('search') }}">
            <input type="hidden" name="program_id" value="{{ $programId ?? '' }}">
            <input type="hidden" name="tahun" value="{{ request('tahun', $tahunAnggaranAktif->tahun ?? '') }}">
            <input type="hidden" name="sumber_dana_id" value="{{ $sumberDanaId ?? '' }}">
            <input type="hidden" name="kode_rekening_id" value="{{ $kodeRekeningId ?? '' }}">
            <input type="hidden" name="jenis_belanja_id" value="{{ $jenisBelanjaId ?? '' }}">
        </form>
    </div>

    <script>
        function hapusSemuaRkas() {
            if (!confirm('Hapus SEMUA item RKAS pada filter aktif?\nTindakan ini tidak bisa dibatalkan.')) return;
            document.getElementById('form-hapus-semua-rkas').submit();
        }
    </script>
</x-app-layout>
