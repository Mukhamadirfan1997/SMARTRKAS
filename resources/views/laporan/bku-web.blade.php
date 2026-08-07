<x-app-layout>
    <x-slot name="header">
        <div class="page-title">BKU Bulanan</div>
    </x-slot>

    <div class="card mb-6 border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
        <div class="h-1.5 bg-gradient-to-r from-emerald-400 via-emerald-500 to-emerald-600"></div>
        <div class="card-body" style="padding:20px 24px;">
            <form method="GET" action="{{ route('laporan.bku.preview') }}" class="flex items-end gap-4 flex-wrap">
                <div class="min-w-[140px]">
                    <label class="form-label" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Tahun</label>
                    <select name="tahun" class="form-select" onchange="this.form.submit()" style="border-radius:10px;border-color:#e2e8f0;">
                        @foreach($tahunList ?? [TahunAnggaran::getActive()] as $t)
                            <option value="{{ $t->tahun }}" {{ ($tahunAnggaranAktif?->tahun ?? null) == $t->tahun ? 'selected' : '' }}>
                                {{ $t->tahun }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="form-label" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Bulan</label>
                    <select name="bulan" class="form-select" onchange="this.form.submit()" style="border-radius:10px;border-color:#e2e8f0;">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[160px]">
                    <label class="form-label" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Sumber Dana</label>
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
                    <label class="form-label" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Tanggal Cetak</label>
                    <input type="date" id="tanggal-cetak" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" class="form-input" style="border-radius:10px;border-color:#e2e8f0;padding:8px 12px;">
                </div>
                <div class="flex gap-2 items-center">
                    <button type="button" onclick="cetakPdf()" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-sm font-semibold rounded-xl hover:from-emerald-600 hover:to-emerald-700 shadow-sm transition-all">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak PDF
                    </button>
                    <a href="{{ route('laporan.bku.export-excel', ['bulan' => $bulan, 'tahun' => $tahunAnggaranAktif?->tahun ?? date('Y'), 'sumber_dana_id' => $sumberDanaId ?? '']) }}"
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

    <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
        <div class="card-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);">
            <span class="card-title" style="font-size:14px;">Buku Kas Umum &mdash; {{ \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') }} {{ $tahunAnggaranAktif?->tahun ?? date('Y') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center">No</th>
                        <th>Tanggal</th>
                        <th>No Bukti</th>
                        <th>Kode Kegiatan</th>
                        <th>Kode Rekening</th>
                        <th>Jenis Belanja</th>
                        <th>Uraian</th>
                        <th>Toko/Penerima</th>
                        <th class="text-center">Pengadaan</th>
                        <th class="text-right whitespace-nowrap" style="min-width:120px">Penerimaan</th>
                        <th class="text-right whitespace-nowrap" style="min-width:120px">Pengeluaran</th>
                        <th class="text-right whitespace-nowrap" style="min-width:120px">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaksis as $no => $t)
                        <tr>
                            <td class="text-center">{{ $no + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($t->tanggal)->format('d/m/Y') }}</td>
                            <td style="font-size:12px">{{ $t->no_bukti }}</td>
                            <td>{{ $t->rkasItem?->program?->kode ?? '-' }}</td>
                            <td>{{ $t->rkasItem?->kodeRekening?->kode ?? '-' }}</td>
                            <td>{{ $t->rkasItem?->kodeRekening?->jenisBelanja?->nama ?? '-' }}</td>
                            <td>{{ $t->uraian ?? $t->rkasItem?->uraian ?? '-' }}</td>
                            <td>{{ $t->toko_penerima ?? '-' }}</td>
                            <td class="text-center whitespace-nowrap">
                                @if($t->metode_pengadaan === 'siplah')
                                    <span class="badge badge-blue">SIPLAH</span>
                                @elseif($t->metode_pengadaan === 'non_siplah')
                                    <span class="badge badge-gray">Non-SIPLAH</span>
                                @else
                                    <span class="text-slate-400 text-xs">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if(strtolower($t->jenis) == 'penerimaan')
                                    <span class="text-emerald-600 font-semibold">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</span>
                                @else &mdash;
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if(strtolower($t->jenis) == 'pengeluaran')
                                    <span class="text-red-600 font-semibold">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</span>
                                @else &mdash;
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <span class="font-semibold {{ $t->saldo_berjalan >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                                    Rp {{ number_format($t->saldo_berjalan, 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-12 text-slate-400">
                                <svg aria-hidden="true" class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Tidak ada transaksi pada bulan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);">
                        <td colspan="9" class="text-right font-bold text-slate-700">TOTAL</td>
                        <td class="text-right text-emerald-700 font-bold whitespace-nowrap">Rp {{ number_format($totalPenerimaan, 0, ',', '.') }}</td>
                        <td class="text-right text-red-700 font-bold whitespace-nowrap">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                        <td class="text-right text-blue-700 font-bold whitespace-nowrap">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <script>
    async function cetakPdf() {
        var tgl = document.getElementById('tanggal-cetak').value;
        var bulan = {{ $bulan }};
        var tahun = new URLSearchParams(window.location.search).get('tahun') || '{{ $tahunAnggaranAktif?->tahun ?? date('Y') }}';
        var sd = document.querySelector('select[name="sumber_dana_id"]')?.value || '';
        var url = '{{ route('laporan.bku') }}?bulan=' + bulan + '&tahun=' + tahun + '&tanggal_cetak=' + tgl + '&sumber_dana_id=' + sd;
        if (window.SmartRKAS && window.SmartRKAS.saveDownload) {
            await window.SmartRKAS.saveDownload(url + '&cetak=pdf');
        } else {
            window.open(url + '&cetak=pdf', '_blank');
        }
    }
    </script>
</x-app-layout>
