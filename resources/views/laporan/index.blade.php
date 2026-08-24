<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Laporan</div>
    </x-slot>

    @if(!$tahunAnggaranAktif)
        <div class="alert-warning mb-6">
            <svg aria-hidden="true" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Tahun anggaran belum diaktifkan. Laporan tidak akan menampilkan data. Silakan aktifkan di menu <a href="{{ route('tahun-anggaran.index') }}" class="underline font-semibold hover:text-amber-900">Tahun Anggaran</a> terlebih dahulu.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <a href="{{ route('laporan.bku.preview', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-emerald-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">BKU Bulanan</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Buku Kas Umum per bulan dengan saldo berjalan</p>
            </div>
        </a>

        <a href="{{ route('laporan.rekap-rekening.preview', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-blue-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-blue-400 to-blue-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Rekap Realisasi</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Rekap realisasi per kode rekening per bulan</p>
            </div>
        </a>

        <a href="{{ route('laporan.rekap-kuartal.preview', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-amber-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-amber-400 to-amber-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-amber-50 to-amber-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Rekap Tribulan</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Rekap realisasi per tribulan (3 bulan)</p>
            </div>
        </a>

        <a href="{{ route('laporan.rekap-siplah.preview', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-violet-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-violet-400 to-violet-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-violet-50 to-violet-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Rekap SIPLAH</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Proporsi pengeluaran SIPLAH vs Non-SIPLAH</p>
            </div>
        </a>

        <a href="{{ route('laporan.k7b', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-teal-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-teal-400 to-teal-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-teal-50 to-teal-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Register Kas (K-7b)</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Penutupan kas bulanan &amp; rincian fisik uang kas</p>
            </div>
        </a>

        <a href="{{ route('laporan.k7c', ['bulan' => date('n'), 'tahun' => ($tahunAnggaranAktif?->tahun ?? date('Y')), 'sumber_dana_id' => request('sumber_dana_id')]) }}"
           class="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-rose-300 transition-all duration-300 overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-rose-400 to-rose-600"></div>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-rose-50 to-rose-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm">
                    <svg aria-hidden="true" class="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Pemeriksaan Kas (K-7c)</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Berita Acara Pemeriksaan Kas oleh Kepala Sekolah</p>
            </div>
        </a>
    </div>
</x-app-layout>
