<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="page-title">
                Formulir BOS-K7b (Register Penutupan Kas)
                @if($tahunAnggaranAktif)
                    <span class="text-slate-400 font-normal">({{ $tahunAnggaranAktif->tahun }})</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan.k7c', request()->query()) }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Buka Berita Acara (K-7c)
                </a>
                <a href="{{ route('laporan.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Semua Laporan
                </a>
            </div>
        </div>
    </x-slot>

    {{-- Filter Bar --}}
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.k7b') }}" id="form-filter">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div>
                        <label for="bulan" class="form-label">Bulan Penutupan</label>
                        <select name="bulan" id="bulan" class="form-select" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun" class="form-label">Tahun Anggaran</label>
                        <select name="tahun" id="tahun" class="form-select" onchange="this.form.submit()">
                            @foreach($tahunList as $t)
                                <option value="{{ $t->tahun }}" {{ ($tahunAnggaranAktif?->tahun ?? date('Y')) == $t->tahun ? 'selected' : '' }}>
                                    {{ $t->tahun }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sumber_dana_id" class="form-label">Sumber Dana</label>
                        <select name="sumber_dana_id" id="sumber_dana_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Sumber Dana</option>
                            @foreach($sumberDanaList as $sd)
                                <option value="{{ $sd->id }}" {{ $sumberDanaId == $sd->id ? 'selected' : '' }}>
                                    {{ $sd->kode }} - {{ $sd->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tanggal_penutupan" class="form-label">Tgl Penutupan Bulan Ini</label>
                        <input type="date" name="tanggal_penutupan" id="tanggal_penutupan" value="{{ $tanggalPenutupanInput }}" class="form-input">
                    </div>

                    <div>
                        <label for="tanggal_penutupan_lalu" class="form-label">Tgl Penutupan Bulan Lalu</label>
                        <input type="date" name="tanggal_penutupan_lalu" id="tanggal_penutupan_lalu" value="{{ $tanggalPenutupanLaluInput }}" class="form-input">
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Workspace: Form Input Kiri + Preview Kanan --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Sisi Kiri: Form Input Fisik Kas & Bank --}}
        <div class="lg:col-span-5 space-y-6">
            <div class="card shadow-sm border-slate-200">
                <div class="card-header bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="card-title text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Hitung Fisik Kas & Bank
                    </span>
                    <span class="text-xs text-slate-500 font-normal">Kalkulator Realtime</span>
                </div>
                <div class="card-body p-4 space-y-5">
                    {{-- Status Saldo BKU --}}
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <div class="text-xs text-emerald-700 font-medium">Saldo BKU Sistem (A)</div>
                        <div class="text-lg font-bold text-emerald-800" id="info-saldo-bku">
                            Rp {{ number_format($saldoBkuA, 2, ',', '.') }}
                        </div>
                        <div class="text-[11px] text-emerald-600 mt-0.5">
                            Penerimaan (D): Rp {{ number_format($totalPenerimaanD, 0, ',', '.') }} | Pengeluaran (K): Rp {{ number_format($totalPengeluaranK, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Form Uang Kertas --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">1. Lembaran Uang Kertas</label>
                        <div class="space-y-2">
                            @foreach($rincianKertas as $key => $k)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-24 font-mono text-slate-600 font-semibold">Rp {{ $k['label'] }}</span>
                                    <span class="text-slate-400">×</span>
                                    <input type="number" min="0" step="1" name="kertas_{{ $key }}" value="{{ $k['lembar'] }}" class="form-input py-1 px-2 text-right w-24 text-xs font-mono input-kertas" data-nominal="{{ $k['nominal'] }}" data-key="{{ $key }}" placeholder="0">
                                    <span class="text-slate-500 w-12">lembar</span>
                                    <span class="text-slate-400">=</span>
                                    <span class="flex-1 text-right font-mono font-semibold text-slate-700 subtotal-kertas-item" id="sub-kertas-{{ $key }}">
                                        Rp {{ number_format($k['total'], 0, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-2 border-t border-slate-100 text-xs font-bold text-slate-700">
                            <span>Sub Jumlah Lembar Uang Kertas (1)</span>
                            <span class="font-mono text-indigo-700" id="total-kertas-display">Rp {{ number_format($subtotalKertas, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Form Uang Logam --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">2. Keping Uang Logam</label>
                        <div class="space-y-2">
                            @foreach($rincianLogam as $key => $l)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-24 font-mono text-slate-600 font-semibold">Rp {{ $l['label'] }}</span>
                                    <span class="text-slate-400">×</span>
                                    <input type="number" min="0" step="1" name="logam_{{ $key }}" value="{{ $l['keping'] }}" class="form-input py-1 px-2 text-right w-24 text-xs font-mono input-logam" data-nominal="{{ $l['nominal'] }}" data-key="{{ $key }}" placeholder="0">
                                    <span class="text-slate-500 w-12">keping</span>
                                    <span class="text-slate-400">=</span>
                                    <span class="flex-1 text-right font-mono font-semibold text-slate-700 subtotal-logam-item" id="sub-logam-{{ $key }}">
                                        Rp {{ number_format($l['total'], 0, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-2 border-t border-slate-100 text-xs font-bold text-slate-700">
                            <span>Sub Jumlah Keping Uang Logam (2)</span>
                            <span class="font-mono text-indigo-700" id="total-logam-display">Rp {{ number_format($subtotalLogam, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- 3. Saldo Bank & Total Kas B --}}
                    <div class="space-y-3 pt-3 border-t border-slate-200">
                        <div>
                            <label for="input_saldo_bank" class="form-label text-xs">3. Saldo Rekening Bank (Rp)</label>
                            <input type="text" id="input_saldo_bank" value="{{ number_format($saldoBank, 0, ',', '.') }}" class="form-input text-sm font-bold font-mono text-slate-800" placeholder="0">
                            <p class="text-[11px] text-slate-400 mt-1">Sesuai saldo pada mutasi Rekening Koran Bank.</p>
                        </div>

                        <div>
                            <label for="input_penjelasan" class="form-label text-xs">Penjelasan Perbedaan</label>
                            <input type="text" id="input_penjelasan" value="{{ $penjelasanPerbedaan }}" class="form-input text-xs" placeholder="NIHIL">
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-600">Total Kas & Bank (B = 1+2+3):</span>
                                <span class="font-bold font-mono text-slate-800" id="display-total-b">Rp {{ number_format($totalKasB, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-bold pt-1 border-t border-slate-200" id="row-selisih">
                                <span class="text-slate-700">Perbedaan (A - B):</span>
                                <span class="font-mono {{ abs($perbedaan) < 0.01 ? 'text-emerald-700' : 'text-red-600' }}" id="display-perbedaan">
                                    Rp {{ number_format($perbedaan, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sisi Kanan: Pratinjau Lembar Fisik K-7b --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-slate-200 shadow-sm no-print">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    Pratinjau Lembar Cetak
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak Langsung
                    </button>
                    <a href="#" id="btn-unduh-pdf" target="_blank" class="btn-primary btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh PDF
                    </a>
                </div>
            </div>

            {{-- Kertas Dokumen Preview --}}
            <div class="bg-white p-8 md:p-12 rounded-2xl border border-slate-300 shadow-md font-serif text-black text-xs leading-relaxed max-w-full overflow-x-auto" id="preview-container">
                <div class="flex justify-end mb-2">
                    <div class="border border-black px-2 py-0.5 font-bold text-xs">FORMULIR BOS-K7b</div>
                </div>

                <div class="text-center font-bold text-sm underline mb-4 tracking-wide">REGISTER PENUTUPAN KAS</div>

                <table class="w-full mb-3 text-xs">
                    <tr>
                        <td class="w-56 py-0.5">Tanggal Penutupan Kas Bulan ini</td>
                        <td class="w-4 text-center">:</td>
                        <td class="font-sans" id="pv-tgl-ini">{{ $tanggalPenutupan }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Nama Penutup KAS (Pemegang KAS)</td>
                        <td class="text-center">:</td>
                        <td class="font-sans font-medium">{{ $profil?->nama_bendahara ?? 'Bendahara' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Tanggal Penutupan KAS Bulan Lalu</td>
                        <td class="text-center">:</td>
                        <td class="font-sans" id="pv-tgl-lalu">{{ $tanggalPenutupanLalu }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Jumlah Total Penerimaan BKU (D)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono">Rp. {{ number_format($totalPenerimaanD, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Jumlah Total Pengeluaran BKU (K)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono">Rp. {{ number_format($totalPengeluaranK, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold">
                        <td class="py-0.5">Saldo Buku Kas Umum (A=D-K)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono" id="pv-saldo-a">Rp. {{ number_format($saldoBkuA, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold">
                        <td class="py-0.5">Saldo Kas Tunai</td>
                        <td class="text-center">:</td>
                        <td class="font-mono" id="pv-saldo-tunai">Rp. {{ number_format($subtotalFisikKas, 2, ',', '.') }}</td>
                    </tr>
                </table>

                {{-- Tabel 1. Uang Kertas --}}
                <table class="w-full text-xs mb-1">
                    @php $firstK = true; @endphp
                    @foreach($rincianKertas as $key => $k)
                        <tr>
                            <td class="w-6">{{ $firstK ? '1.' : '' }}</td>
                            <td class="w-48">Lembaran uang kertas</td>
                            <td class="w-24">Rp {{ $k['label'] }}</td>
                            <td class="w-16 text-right font-mono pv-qty-kertas" id="pv-qty-kertas-{{ $key }}">{{ number_format($k['lembar'], 0, ',', '.') }}</td>
                            <td class="w-16 pl-2">Lembar</td>
                            <td class="text-right font-mono pv-sub-kertas" id="pv-sub-kertas-{{ $key }}">Rp. {{ number_format($k['total'], 2, ',', '.') }}</td>
                        </tr>
                        @php $firstK = false; @endphp
                    @endforeach
                    <tr class="font-bold border-t border-black border-b border-double">
                        <td colspan="4" class="text-right py-1">Sub Jumlah Lembar uang kertas (1)</td>
                        <td colspan="2" class="text-right font-mono py-1" id="pv-sub-kertas-total">Rp. {{ number_format($subtotalKertas, 2, ',', '.') }}</td>
                    </tr>
                </table>

                {{-- Tabel 2. Uang Logam --}}
                <table class="w-full text-xs mt-1 mb-1">
                    @php $firstL = true; @endphp
                    @foreach($rincianLogam as $key => $l)
                        <tr>
                            <td class="w-6">{{ $firstL ? '2.' : '' }}</td>
                            <td class="w-48">Keping uang logam</td>
                            <td class="w-24">Rp {{ $l['label'] }}</td>
                            <td class="w-16 text-right font-mono pv-qty-logam" id="pv-qty-logam-{{ $key }}">{{ number_format($l['keping'], 0, ',', '.') }}</td>
                            <td class="w-16 pl-2">Keping</td>
                            <td class="text-right font-mono pv-sub-logam" id="pv-sub-logam-{{ $key }}">Rp. {{ number_format($l['total'], 2, ',', '.') }}</td>
                        </tr>
                        @php $firstL = false; @endphp
                    @endforeach
                    <tr class="font-bold border-t border-black border-b border-double">
                        <td colspan="4" class="text-right py-1">Sub Jumlah Keping uang logam (2)</td>
                        <td colspan="2" class="text-right font-mono py-1" id="pv-sub-logam-total">Rp. {{ number_format($subtotalLogam, 2, ',', '.') }}</td>
                    </tr>
                </table>

                {{-- 3. Saldo Bank & Summary --}}
                <table class="w-full text-xs mt-1 mb-3">
                    <tr>
                        <td class="w-6">3.</td>
                        <td class="w-64">Saldo Rekening Bank</td>
                        <td class="text-right font-bold w-36">Sub Jumlah (3)</td>
                        <td class="text-right font-mono font-bold" id="pv-saldo-bank">Rp. {{ number_format($saldoBank, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold border-t border-black border-b border-double">
                        <td>B.</td>
                        <td colspan="2" class="text-right py-1">Jumlah (1+2+3)</td>
                        <td class="text-right font-mono py-1" id="pv-total-kas-b">Rp. {{ number_format($totalKasB, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold">
                        <td colspan="3" class="pt-2">Perbedaan (A-B)</td>
                        <td class="text-right font-mono pt-2" id="pv-perbedaan">Rp. {{ number_format($perbedaan, 2, ',', '.') }}</td>
                    </tr>
                </table>

                <div class="mt-2 mb-6">
                    <div class="text-xs mb-1">Penjelasan Perbedaan:</div>
                    <div class="border border-black p-2 min-h-[30px] font-bold font-sans text-xs" id="pv-penjelasan">
                        {{ $penjelasanPerbedaan }}
                    </div>
                </div>

                {{-- Tanda Tangan --}}
                <table class="w-full text-xs mt-6">
                    <tr>
                        <td class="w-1/2 align-top">
                            Yang diperiksa,<br>
                            <strong>Bendahara</strong>
                            <div class="h-16"></div>
                            <strong>{{ $profil?->nama_bendahara ?? '....................................' }}</strong><br>
                            NIP. {{ $profil?->nip_bendahara ?? '....................................' }}
                        </td>
                        <td class="w-1/2 align-top">
                            Tanggal, <span id="pv-ttd-tgl">{{ $tanggalPenutupan }}</span><br>
                            Yang Memeriksa,<br>
                            <strong>Kepala Sekolah</strong><br>
                            <strong>{{ $profil?->nama ?? 'Sekolah' }}</strong>
                            <div class="h-16"></div>
                            <strong>{{ $profil?->nama_kepsek ?? '....................................' }}</strong><br>
                            NIP. {{ $profil?->nip_kepsek ?? '....................................' }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Script Realtime Calculator & PDF Link Sync --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saldoBkuA = {{ (float) $saldoBkuA }};
            const kertasInputs = document.querySelectorAll('.input-kertas');
            const logamInputs = document.querySelectorAll('.input-logam');
            const bankInput = document.getElementById('input_saldo_bank');
            const penjelasanInput = document.getElementById('input_penjelasan');
            const tglIniInput = document.getElementById('tanggal_penutupan');
            const tglLaluInput = document.getElementById('tanggal_penutupan_lalu');
            const btnPdf = document.getElementById('btn-unduh-pdf');

            function formatRupiah(num) {
                return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function formatNumber(num) {
                return num.toLocaleString('id-ID');
            }
            function parseNominal(str) {
                if (!str) return 0;
                let clean = String(str).replace(/\./g, '').replace(/,/g, '.');
                let val = parseFloat(clean);
                return isNaN(val) ? 0 : val;
            }

            function hitungSemua() {
                let totalKertas = 0;
                kertasInputs.forEach(input => {
                    let nominal = parseFloat(input.dataset.nominal) || 0;
                    let qty = parseInt(input.value) || 0;
                    let sub = nominal * qty;
                    totalKertas += sub;
                    let key = input.dataset.key;
                    document.getElementById('sub-kertas-' + key).innerText = 'Rp ' + formatNumber(sub);
                    document.getElementById('pv-qty-kertas-' + key).innerText = formatNumber(qty);
                    document.getElementById('pv-sub-kertas-' + key).innerText = 'Rp. ' + formatRupiah(sub);
                });
                document.getElementById('total-kertas-display').innerText = 'Rp ' + formatRupiah(totalKertas);
                document.getElementById('pv-sub-kertas-total').innerText = 'Rp. ' + formatRupiah(totalKertas);

                let totalLogam = 0;
                logamInputs.forEach(input => {
                    let nominal = parseFloat(input.dataset.nominal) || 0;
                    let qty = parseInt(input.value) || 0;
                    let sub = nominal * qty;
                    totalLogam += sub;
                    let key = input.dataset.key;
                    document.getElementById('sub-logam-' + key).innerText = 'Rp ' + formatNumber(sub);
                    document.getElementById('pv-qty-logam-' + key).innerText = formatNumber(qty);
                    document.getElementById('pv-sub-logam-' + key).innerText = 'Rp. ' + formatRupiah(sub);
                });
                document.getElementById('total-logam-display').innerText = 'Rp ' + formatRupiah(totalLogam);
                document.getElementById('pv-sub-logam-total').innerText = 'Rp. ' + formatRupiah(totalLogam);

                let totalFisikKas = totalKertas + totalLogam;
                document.getElementById('pv-saldo-tunai').innerText = 'Rp. ' + formatRupiah(totalFisikKas);

                let saldoBank = parseNominal(bankInput.value);
                document.getElementById('pv-saldo-bank').innerText = 'Rp. ' + formatRupiah(saldoBank);

                let totalKasB = totalFisikKas + saldoBank;
                document.getElementById('display-total-b').innerText = 'Rp ' + formatRupiah(totalKasB);
                document.getElementById('pv-total-kas-b').innerText = 'Rp. ' + formatRupiah(totalKasB);

                let perbedaan = saldoBkuA - totalKasB;
                let dispPerbedaan = document.getElementById('display-perbedaan');
                let pvPerbedaan = document.getElementById('pv-perbedaan');
                dispPerbedaan.innerText = 'Rp ' + formatRupiah(perbedaan);
                pvPerbedaan.innerText = 'Rp. ' + formatRupiah(perbedaan);

                if (Math.abs(perbedaan) < 0.01) {
                    dispPerbedaan.className = 'font-mono text-emerald-700';
                    if (!penjelasanInput.value || penjelasanInput.value.startsWith('Selisih')) {
                        penjelasanInput.value = 'NIHIL';
                    }
                } else {
                    dispPerbedaan.className = 'font-mono text-red-600';
                    if (!penjelasanInput.value || penjelasanInput.value === 'NIHIL') {
                        penjelasanInput.value = 'Selisih Rp ' + formatNumber(Math.abs(perbedaan));
                    }
                }
                document.getElementById('pv-penjelasan').innerText = penjelasanInput.value || 'NIHIL';

                // Sync URL PDF download
                updatePdfUrl();
            }

            function updatePdfUrl() {
                let params = new URLSearchParams(window.location.search);
                params.set('cetak', 'pdf');
                params.set('bulan', document.getElementById('bulan').value);
                params.set('tahun', document.getElementById('tahun').value);
                let sd = document.getElementById('sumber_dana_id').value;
                if (sd) params.set('sumber_dana_id', sd); else params.delete('sumber_dana_id');

                if (tglIniInput.value) params.set('tanggal_penutupan', tglIniInput.value);
                if (tglLaluInput.value) params.set('tanggal_penutupan_lalu', tglLaluInput.value);
                if (bankInput.value) params.set('saldo_bank', parseNominal(bankInput.value));
                if (penjelasanInput.value) params.set('penjelasan_perbedaan', penjelasanInput.value);

                kertasInputs.forEach(input => {
                    params.set('kertas_' + input.dataset.key, input.value || '0');
                });
                logamInputs.forEach(input => {
                    params.set('logam_' + input.dataset.key, input.value || '0');
                });

                btnPdf.href = "{{ route('laporan.k7b') }}?" + params.toString();
            }

            kertasInputs.forEach(i => i.addEventListener('input', hitungSemua));
            logamInputs.forEach(i => i.addEventListener('input', hitungSemua));
            bankInput.addEventListener('input', hitungSemua);
            penjelasanInput.addEventListener('input', function() {
                document.getElementById('pv-penjelasan').innerText = this.value || 'NIHIL';
                updatePdfUrl();
            });

            tglIniInput.addEventListener('change', function() {
                document.getElementById('form-filter').submit();
            });
            tglLaluInput.addEventListener('change', function() {
                document.getElementById('form-filter').submit();
            });

            hitungSemua();
        });
    </script>
</x-app-layout>

