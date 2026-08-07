<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Rekap Pengadaan SIPLAH</div>
    </x-slot>

    <div class="space-y-6">
        <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
            <div class="h-1.5 bg-gradient-to-r from-violet-400 via-violet-500 to-violet-600"></div>
            <div class="card-body" style="padding:20px 24px;">
                <div class="flex items-end gap-4 flex-wrap">
                    <div>
                        <label class="form-label">Periode</label>
                        <form id="filter-form" class="flex items-end gap-2">
                            <select id="tahun-select" class="form-select" style="border-radius:10px;border-color:#e2e8f0;min-width:110px;" onchange="applyFilter()">
                                @foreach($tahunList ?? [TahunAnggaran::getActive()] as $t)
                                    <option value="{{ $t->tahun }}" {{ ($tahunAnggaranAktif?->tahun ?? null) == $t->tahun ? 'selected' : '' }}>
                                        {{ $t->tahun }}
                                    </option>
                                @endforeach
                            </select>
                            <select id="periode-select" class="form-select" style="border-radius:10px;border-color:#e2e8f0;min-width:200px;" onchange="applyFilter()">
                                @foreach(range(1,12) as $m)
                                    <option value="{{ $m }}" {{ count($months) == 1 && $months[0] == $m ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                    </option>
                                @endforeach
                                <option value="h1" {{ $months == [1,2,3,4,5,6] ? 'selected' : '' }}>Januari &ndash; Juni</option>
                                <option value="h2" {{ $months == [7,8,9,10,11,12] ? 'selected' : '' }}>Juli &ndash; Desember</option>
                                <option value="all" {{ count($months) == 12 ? 'selected' : '' }}>Seluruh Tahun</option>
                            </select>
                            <select name="sumber_dana_id" class="form-select" style="border-radius:10px;border-color:#e2e8f0;min-width:160px;" onchange="applyFilter()">
                                <option value="">Semua Sumber Dana</option>
                                @foreach($sumberDanaList as $sd)
                                    <option value="{{ $sd->id }}" {{ request('sumber_dana_id', $sumberDanaId ?? '') == $sd->id ? 'selected' : '' }}>
                                        {{ $sd->kode }} - {{ $sd->nama }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="tanggal_cetak" id="tanggal-cetak" value="{{ $tanggalCetak ?? date('Y-m-d') }}">
                        </form>
                    </div>
                    <div class="flex-1"></div>
                    <div class="flex items-center gap-2">
                        <button onclick="cetakPdf()" class="btn btn-secondary btn-sm" style="border-radius:10px;">
                            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Cetak PDF
                        </button>
                        <button onclick="exportExcel()" class="btn btn-success btn-sm" style="border-radius:10px;">
                            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export Excel
                        </button>
                        <a href="{{ route('laporan.index') }}"
                           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all ml-1">
                            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius:16px;">
                <div class="h-2 bg-gradient-to-r from-slate-400 to-slate-600"></div>
                <div class="card-body text-center" style="padding:28px 24px;">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center shadow-sm">
                        <svg aria-hidden="true" class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Total Pengeluaran</p>
                    <p class="text-3xl font-extrabold text-slate-800 mt-3">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</p>
                    <p class="text-sm text-slate-400 mt-2">{{ $periodeLabel }} {{ $tahunAnggaranAktif?->tahun ?? date('Y') }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius:16px;">
                <div class="h-2 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
                <div class="card-body text-center" style="padding:28px 24px;">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 flex items-center justify-center shadow-sm">
                        <svg aria-hidden="true" class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <p class="text-xs text-emerald-600 uppercase tracking-wide font-semibold">SIPLAH</p>
                    <p class="text-3xl font-extrabold text-emerald-700 mt-3">Rp {{ number_format($totalSiplah, 0, ',', '.') }}</p>
                    <div class="mt-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-emerald-100 text-emerald-800">
                            {{ $persenSiplah }}%
                        </span>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius:16px;">
                <div class="h-2 bg-gradient-to-r from-orange-400 to-orange-600"></div>
                <div class="card-body text-center" style="padding:28px 24px;">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-orange-50 to-orange-100 flex items-center justify-center shadow-sm">
                        <svg aria-hidden="true" class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-xs text-orange-600 uppercase tracking-wide font-semibold">Non-SIPLAH</p>
                    <p class="text-3xl font-extrabold text-orange-700 mt-3">Rp {{ number_format($totalNonSiplah, 0, ',', '.') }}</p>
                    <div class="mt-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-orange-100 text-orange-800">
                            {{ $persenNonSiplah }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @if($totalBelumDiisi > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
            <svg aria-hidden="true" class="w-6 h-6 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">Ada Rp {{ number_format($totalBelumDiisi, 0, ',', '.') }} yang belum ditandai metode pengadaannya.</p>
                <p class="text-xs text-amber-600 mt-1">Harap update transaksi BKU dan isi kolom "Metode Pengadaan".</p>
            </div>
        </div>
        @endif

        <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
            <div class="h-1.5 bg-gradient-to-r from-violet-400 via-violet-500 to-violet-600"></div>
            <div class="card-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);">
                <span class="card-title" style="font-size:14px;">Proporsi Pengadaan</span>
            </div>
            <div class="card-body" style="padding:32px;">
                @if($totalPengeluaran > 0)
                    @php
                        $pSiplah = $persenSiplah;
                        $pNon = $persenNonSiplah;
                        $pBelum = $totalBelumDiisi > 0 ? max(0, 100 - $pSiplah - $pNon) : 0;
                    @endphp
                    <div class="flex h-6 w-full rounded-full overflow-hidden bg-slate-100">
                        @if($pSiplah > 0)
                            <div class="bg-emerald-500 h-full" style="width:{{ $pSiplah }}%" title="SIPLAH {{ $pSiplah }}%"></div>
                        @endif
                        @if($pNon > 0)
                            <div class="bg-orange-500 h-full" style="width:{{ $pNon }}%" title="Non-SIPLAH {{ $pNon }}%"></div>
                        @endif
                        @if($pBelum > 0)
                            <div class="bg-slate-300 h-full" style="width:{{ $pBelum }}%" title="Belum Diisi {{ $pBelum }}%"></div>
                        @endif
                    </div>
                    <div class="flex items-center justify-center gap-6 mt-6 flex-wrap text-sm">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            SIPLAH <strong class="text-slate-700">{{ $persenSiplah }}%</strong>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-orange-500"></span>
                            Non-SIPLAH <strong class="text-slate-700">{{ $persenNonSiplah }}%</strong>
                        </span>
                        @if($pBelum > 0)
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-slate-300"></span>
                            Belum Diisi <strong class="text-slate-700">{{ $pBelum }}%</strong>
                        </span>
                        @endif
                    </div>
                @else
                    <div class="text-center py-16 text-slate-400">
                        <svg aria-hidden="true" class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm">Belum ada data pengeluaran</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
            <div class="h-1.5 bg-gradient-to-r from-violet-400 via-violet-500 to-violet-600"></div>
            <div class="card-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);">
                <span class="card-title" style="font-size:14px;">Breakdown per Jenis Belanja</span>
            </div>
            <div class="overflow-x-auto">
                @if($breakdown->count() > 0)
                    <table class="data-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:45px">No</th>
                            <th style="min-width:180px">Jenis Belanja</th>
                            <th class="text-right whitespace-nowrap" style="min-width:150px">Total Pengeluaran</th>
                            <th class="text-right whitespace-nowrap" style="min-width:150px">SIPLAH</th>
                            <th class="text-right whitespace-nowrap" style="min-width:150px">Non-SIPLAH</th>
                            <th class="text-center whitespace-nowrap" style="min-width:90px">% SIPLAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($breakdown as $item)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="whitespace-nowrap">
                                <div class="font-medium text-slate-800">{{ $item->jenis_belanja }}</div>
                            </td>
                            <td class="text-right font-semibold text-slate-700 whitespace-nowrap">
                                Rp {{ number_format($item->total, 0, ',', '.') }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <span class="text-emerald-700 font-semibold">Rp {{ number_format($item->siplah, 0, ',', '.') }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <span class="text-orange-700 font-semibold">Rp {{ number_format($item->non_siplah, 0, ',', '.') }}</span>
                            </td>
                            <td class="text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                    {{ $item->persen_siplah }}%
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);">
                            <td colspan="2" class="text-right font-bold text-slate-700">TOTAL</td>
                            <td class="text-right font-bold text-slate-800 whitespace-nowrap">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                            <td class="text-right font-bold text-emerald-700 whitespace-nowrap">Rp {{ number_format($totalSiplah, 0, ',', '.') }}</td>
                            <td class="text-right font-bold text-orange-700 whitespace-nowrap">Rp {{ number_format($totalNonSiplah, 0, ',', '.') }}</td>
                            <td class="text-center font-bold text-slate-700">{{ $persenSiplah }}%</td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <div class="text-center py-16 text-slate-400">
                    <svg aria-hidden="true" class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm">Belum ada data pengeluaran pada periode ini.</p>
                </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
            <div class="card-body text-center py-5">
                <p class="text-sm text-slate-400">
                    Data diperbarui secara otomatis dari transaksi BKU.
                    Pastikan semua transaksi pengeluaran sudah diisi kolom <strong>Metode Pengadaan</strong>-nya.
                </p>
            </div>
        </div>
    </div>

    <script>
        function buildPeriodeParam() {
            var val = document.getElementById('periode-select').value;
            var tahun = document.getElementById('tahun-select').value;
            var sd = document.querySelector('select[name="sumber_dana_id"]')?.value || '';
            var params = 'tahun=' + tahun;
            if (sd) params += '&sumber_dana_id=' + sd;
            if (val === 'h1' || val === 'h2' || val === 'all') {
                return params + '&periode=' + val;
            }
            return params + '&bulan=' + val;
        }

        function applyFilter() {
            window.location.href = '{{ route('laporan.rekap-siplah.preview') }}?' + buildPeriodeParam();
        }

        function cetakPdf() {
            var tgl = document.getElementById('tanggal-cetak').value;
            var url = '{{ route('laporan.rekap-siplah') }}?' + buildPeriodeParam() + '&tanggal_cetak=' + tgl;
            if (window.SmartRKAS && window.SmartRKAS.saveDownload) {
                window.SmartRKAS.saveDownload(url + '&cetak=pdf');
            } else {
                window.open(url + '&cetak=pdf', '_blank');
            }
        }

        function exportExcel() {
            window.location.href = '{{ route('laporan.rekap-siplah.export-excel') }}?' + buildPeriodeParam();
        }
    </script>
</x-app-layout>
