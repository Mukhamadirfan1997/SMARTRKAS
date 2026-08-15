<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Rekap Tribulan {{ $qLabel }}</div>
    </x-slot>

    <div class="card mb-6 border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
        <div class="h-1.5 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600"></div>
        <div class="card-body" style="padding:20px 24px;">
            <form method="GET" action="{{ route('laporan.rekap-kuartal.preview') }}" class="flex items-end gap-4 flex-wrap">
                <div class="min-w-[140px]">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select" onchange="this.form.submit()" style="border-radius:10px;border-color:#e2e8f0;">
                        @foreach($tahunList ?? [TahunAnggaran::getActive()] as $t)
                            <option value="{{ $t->tahun }}" {{ ($tahunAnggaranAktif?->tahun ?? null) == $t->tahun ? 'selected' : '' }}>
                                {{ $t->tahun }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="form-label">Bulan (menentukan tribulan)</label>
                    <select name="bulan" class="form-select" onchange="this.form.submit()" style="border-radius:10px;border-color:#e2e8f0;">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="form-label">Cari Uraian</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik uraian anggaran..." class="form-input" style="border-radius:10px;border-color:#e2e8f0;padding:8px 12px;">
                </div>
                <div class="min-w-[160px]">
                    <label class="form-label">Program</label>
                    @include('transaksi-bku._search-picker', [
                        'spPrefix' => 'program',
                        'spLabel' => '',
                        'spLabelLower' => 'program',
                        'spRequired' => false,
                        'spStatusHint' => 'Semua Program',
                        'spPlaceholder' => 'Cari program (kode / nama)...',
                        'spInitial' => (string) request('program_id', $programId ?? ''),
                        'spError' => 'program_id',
                        'spAutoSubmit' => true,
                        'spOptions' => collect($programs ?? [])->map(fn ($p) => ['id' => (string) $p->id, 'text' => $p->kode . ' - ' . $p->nama])->values()->all(),
                    ])
                </div>
                <div class="min-w-[160px]">
                    <label class="form-label">Sumber Dana</label>
                    <select name="sumber_dana_id" class="form-select" onchange="this.form.submit()" style="border-radius:10px;border-color:#e2e8f0;min-width:160px;">
                        <option value="">Semua Sumber Dana</option>
                        @foreach($sumberDanaList as $sd)
                            <option value="{{ $sd->id }}" {{ request('sumber_dana_id', $sumberDanaId ?? '') == $sd->id ? 'selected' : '' }}>
                                {{ $sd->kode }} - {{ $sd->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="form-label">Tanggal Cetak</label>
                    <input type="date" id="tanggal-cetak" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" class="form-input" style="border-radius:10px;border-color:#e2e8f0;padding:8px 12px;">
                </div>
                <div class="flex gap-2 items-center">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white text-sm font-semibold rounded-xl hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Cari
                    </button>
                    <button type="button" onclick="cetakPdf()" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-sm font-semibold rounded-xl hover:from-emerald-600 hover:to-emerald-700 shadow-sm transition-all">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak PDF
                    </button>
                    <a href="{{ route('laporan.rekap-kuartal.export-excel', ['bulan' => $bulan, 'tahun' => $tahunAnggaranAktif?->tahun ?? date('Y'), 'sumber_dana_id' => $sumberDanaId ?? '', 'search' => request('search', ''), 'program_id' => $programId ?? '']) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-semibold rounded-xl hover:from-blue-600 hover:to-blue-700 shadow-sm transition-all">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Excel
                    </a>
                    <a href="{{ route('laporan.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all ml-1">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($subtotals->count() > 0)
    <div class="card mb-6 border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
        <div class="card-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);">
            <span class="card-title" style="font-size:14px;">Rekap Tribulan {{ $qLabel }} &mdash; {{ $periodeLabel }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width:180px">Jenis Belanja</th>
                        @foreach($bulanNames as $name)
                            <th class="text-right" style="width:14%">{{ $name }}</th>
                        @endforeach
                        <th class="text-right" style="width:14%">Total {{ $qLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subtotals as $jenis => $data)
                    <tr>
                        <td class="font-medium text-slate-800">{{ $jenis }}</td>
                        @foreach($bulanMonths as $b)
                            <td class="text-right whitespace-nowrap">{!! $data['per_bulan'][$b] > 0 ? 'Rp ' . number_format($data['per_bulan'][$b], 0, ',', '.') : '&mdash;' !!}</td>
                        @endforeach
                        <td class="text-right font-semibold whitespace-nowrap">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#78350f 0%,#92400e 100%);">
                        <td class="text-right font-bold text-white">TOTAL KESELURUHAN</td>
                        @foreach($bulanMonths as $b)
                            <td class="text-right font-bold text-white whitespace-nowrap">Rp {{ number_format($grandTotalPerBulan[$b], 0, ',', '.') }}</td>
                        @endforeach
                        <td class="text-right font-bold text-white whitespace-nowrap">Rp {{ number_format($grandTotalAll, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
        <div class="card-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);">
            <span class="card-title" style="font-size:14px;">Detail Anggaran {{ $qLabel }}</span>
            <span class="text-xs text-slate-400 ml-2">({{ $quarterlyItems->total() ?? 0 }} item ditemukan)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width:4%">No</th>
                        <th style="width:14%">Kode Rekening</th>
                        <th style="width:22%">Uraian Anggaran</th>
                        @foreach($bulanNames as $name)
                            <th class="text-right" style="width:14%">{{ $name }}</th>
                        @endforeach
                        <th class="text-right" style="width:14%">Total {{ $qLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quarterlyItems as $item)
                    <tr>
                        <td class="text-center">{{ $quarterlyItems->firstItem() + $loop->index }}</td>
                        <td style="font-family:monospace">{{ $item->kodeRekening?->kode ?? '-' }}</td>
                        <td>{{ $item->uraian }}</td>
                        @foreach($bulanMonths as $b)
                            @php $r = $item->realisasi_per_bulan[$b] ?? 0; @endphp
                            <td class="text-right whitespace-nowrap">{!! $r > 0 ? 'Rp ' . number_format($r, 0, ',', '.') : '&mdash;' !!}</td>
                        @endforeach
                        <td class="text-right font-semibold whitespace-nowrap">Rp {{ number_format($item->total_realisasi, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 3 + count($bulanNames) + 1 }}" class="text-center py-12 text-slate-400">
                            <svg aria-hidden="true" class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @if(request('search'))
                                Tidak ada anggaran yang cocok dengan pencarian.
                            @else
                                Belum ada data anggaran untuk Tribulan <strong>{{ $qLabel ?? '' }}</strong> ({{ $periodeLabel ?? '' }}) {{ $tahunAnggaranAktif?->tahun ?? '' }}. Coba pilih periode lain atau pastikan data RKAS sudah diimpor.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($quarterlyItems, 'hasPages') && $quarterlyItems->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $quarterlyItems->links() }}
        </div>
        @endif
    </div>

    <script>
    function cetakPdf() {
        var tgl = document.getElementById('tanggal-cetak').value;
        var bulan = {{ $bulan }};
        var tahun = new URLSearchParams(window.location.search).get('tahun') || '{{ $tahunAnggaranAktif?->tahun ?? date('Y') }}';
        var search = document.querySelector('input[name="search"]')?.value || '';
        var sd = document.querySelector('select[name="sumber_dana_id"]')?.value || '';
        var program = document.querySelector('select[name="program_id"]')?.value || '';
        var url = '{{ route('laporan.rekap-kuartal') }}?bulan=' + bulan + '&tahun=' + tahun + '&tanggal_cetak=' + tgl + '&search=' + encodeURIComponent(search) + '&sumber_dana_id=' + sd + '&program_id=' + program;
        if (window.SmartRKAS && window.SmartRKAS.saveDownload) {
            window.SmartRKAS.saveDownload(url + '&cetak=pdf');
        } else {
            window.open(url + '&cetak=pdf', '_blank');
        }
    }
    </script>
</x-app-layout>
