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

    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar RKAS</span>
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('rkas.index') }}" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="search" class="form-input text-sm py-1.5" placeholder="Cari uraian..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('rkas.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                    @endif
                    <select name="bulan" class="form-select py-1.5 text-sm" onchange="this.form.submit()">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                    @include('transaksi-bku._search-picker', [
                        'spPrefix' => 'program',
                        'spLabel' => 'Program',
                        'spLabelLower' => 'program',
                        'spRequired' => false,
                        'spStatusHint' => 'Semua Program',
                        'spPlaceholder' => 'Cari program (kode / nama)...',
                        'spInitial' => (string) ($programId ?? ''),
                        'spError' => 'program_id',
                        'spAutoSubmit' => true,
                        'spCompact' => true,
                        'spOptions' => $programs->map(fn ($p) => ['id' => (string) $p->id, 'text' => $p->kode . ' - ' . $p->nama])->values()->all(),
                    ])
                    <select name="tahun" class="form-select py-1.5 text-sm" onchange="this.form.submit()">
                        @foreach($tahunList as $t)
                            <option value="{{ $t->tahun }}" {{ request('tahun', $tahunAnggaranAktif->tahun ?? '') == $t->tahun ? 'selected' : '' }}>
                                {{ $t->tahun }}
                            </option>
                        @endforeach
                    </select>
                    <select name="sumber_dana_id" class="form-select py-1.5 text-sm" onchange="this.form.submit()" style="min-width:160px">
                        <option value="">Semua Sumber Dana</option>
                        @foreach($sumberDanaList as $sd)
                            <option value="{{ $sd->id }}" {{ request('sumber_dana_id', $sumberDanaId ?? '') == $sd->id ? 'selected' : '' }}>
                                {{ $sd->kode }} - {{ $sd->nama }}
                            </option>
                        @endforeach
                    </select>
                    @include('transaksi-bku._search-picker', [
                        'spPrefix' => 'kode_rekening',
                        'spLabel' => 'Kode Rekening',
                        'spLabelLower' => 'kode rekening',
                        'spRequired' => false,
                        'spStatusHint' => 'Semua Kode Rekening',
                        'spPlaceholder' => 'Cari rekening (kode / nama)...',
                        'spInitial' => (string) ($kodeRekeningId ?? ''),
                        'spError' => 'kode_rekening_id',
                        'spAutoSubmit' => true,
                        'spCompact' => true,
                        'spOptions' => $kodeRekenings->map(fn ($r) => ['id' => (string) $r->id, 'text' => $r->kode . ' - ' . $r->nama])->values()->all(),
                    ])
                    <select name="jenis_belanja_id" class="form-select py-1.5 text-sm" onchange="this.form.submit()" style="min-width:170px">
                        <option value="">Semua Jenis Belanja</option>
                        @foreach($jenisBelanjas as $jb)
                            <option value="{{ $jb->id }}" {{ request('jenis_belanja_id', $jenisBelanjaId ?? '') == $jb->id ? 'selected' : '' }}>
                                {{ $jb->nama }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('import-rkas.index') }}" class="btn-primary">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import Excel
                </a>
                <button type="button" class="btn btn-danger" onclick="hapusSemuaRkas()">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Semua
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if($rkasItems->count() > 0)
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-10">No</th>
                            <th class="w-1/4">Uraian</th>
                            <th class="w-24">Program</th>
                            <th class="w-28">Kode Rekening</th>
                            <th class="w-20">Sumber Dana</th>
                            <th class="text-right w-16">Volume</th>
                            <th class="w-14">Satuan</th>
                            <th class="text-right w-24">Tarif</th>
                            <th class="text-right w-28">Jumlah</th>
                            <th class="text-right w-28">Realisasi</th>
                            <th class="text-right w-28">Sisa</th>
                            <th class="w-28">Status</th>
                            <th class="text-center w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rkasItems as $item)
                            @php
                                $isLengkap = $item->program_id && $item->kode_rekening_id;
                                $realisasi = $item->transaksiBkus->sum('jumlah') + $item->notaBkuItems->sum('subtotal');
                                $sisa = $item->jumlah - $realisasi;
                                $persen = $item->jumlah > 0 ? ($realisasi / $item->jumlah) * 100 : 0;
                                $realisasiVolume = $item->transaksiBkus->sum('volume') + $item->notaBkuItems->sum('jumlah');
                                $sisaVolume = $item->volume > 0 ? ($item->volume - $realisasiVolume) : null;
                            @endphp
                            <tr class="{{ $isLengkap ? '' : 'bg-amber-50/50' }}">
                                <td class="font-semibold text-slate-700">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="font-medium text-slate-800">{{ $item->uraian }}</div>
                                    @if(!$isLengkap)
                                        <div class="text-xs text-amber-600 mt-0.5">Perlu koreksi</div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->program)
                                        <div class="font-medium text-slate-700 text-xs">{{ $item->program->kode }}</div>
                                        <div class="text-xs text-slate-400">{{ $item->program->nama }}</div>
                                    @else
                                        <span class="badge badge-red">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->kodeRekening)
                                        <div class="font-mono font-medium text-slate-700 text-xs">{{ $item->kodeRekening->kode }}</div>
                                        <div class="text-xs text-slate-400">{{ $item->kodeRekening->nama }}</div>
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
                                <td class="text-right text-slate-700 font-medium whitespace-nowrap">{{ $item->volume > 0 ? number_format($item->volume, 0, ',', '.') : '-' }}</td>
                                <td class="text-slate-600 text-xs">{{ $item->satuan ?: '-' }}</td>
                                <td class="text-right text-slate-700 whitespace-nowrap">{{ $item->tarif > 0 ? 'Rp ' . number_format($item->tarif, 0, ',', '.') : '-' }}</td>
                                <td class="text-right font-bold text-slate-800 whitespace-nowrap">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                <td class="text-right font-semibold text-blue-600 whitespace-nowrap">Rp {{ number_format($realisasi, 0, ',', '.') }}</td>
                                <td class="text-right font-bold whitespace-nowrap {{ $sisa >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    Rp {{ number_format($sisa, 0, ',', '.') }}
                                    @if($sisaVolume !== null)
                                        <br><span class="text-xs font-normal">(sisa {{ number_format($sisaVolume, 0, ',', '.') }} {{ $item->satuan ?: 'item' }})</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sisa < 0)
                                        <span class="badge badge-red">Over ({{ number_format($persen, 0) }}%)</span>
                                    @elseif($persen >= 90)
                                        <span class="badge badge-orange">Hampir Habis</span>
                                    @elseif($persen > 0)
                                        <span class="badge badge-green">{{ number_format($persen, 0) }}%</span>
                                    @elseif(!$isLengkap)
                                        <span class="badge badge-yellow">Koreksi</span>
                                    @else
                                        <span class="badge badge-slate">Belum</span>
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
