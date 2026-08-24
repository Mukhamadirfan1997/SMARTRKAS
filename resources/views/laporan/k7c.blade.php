<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="page-title">
                Formulir BOS-K7c (Berita Acara Pemeriksaan Kas)
                @if($tahunAnggaranAktif)
                    <span class="text-slate-400 font-normal">({{ $tahunAnggaranAktif->tahun }})</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan.k7b', request()->query()) }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Buka Register Kas (K-7b)
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
            <form method="GET" action="{{ route('laporan.k7c') }}" id="form-filter">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="bulan" class="form-label">Bulan Pemeriksaan</label>
                        <select name="bulan" id="bulan" class="form-select" onchange="syncTanggalFilter(); this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tahun" class="form-label">Tahun Anggaran</label>
                        <select name="tahun" id="tahun" class="form-select" onchange="syncTanggalFilter(); this.form.submit()">
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
                        <label for="tanggal_penutupan" class="form-label">Tanggal Berita Acara</label>
                        <input type="date" name="tanggal_penutupan" id="tanggal_penutupan" value="{{ $tanggalPenutupanInput }}" class="form-input">
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Workspace: Form Input Kiri + Preview Kanan --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Sisi Kiri: Form Input Data Pemeriksaan --}}
        <div class="lg:col-span-4 space-y-6">
            <div class="card shadow-sm border-slate-200">
                <div class="card-header bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="card-title text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Parameter Berita Acara
                    </span>
                    <span class="text-xs text-slate-500 font-normal">K-7c</span>
                </div>
                <div class="card-body p-4 space-y-4">
                    <div>
                        <label for="input_sk_kepsek" class="form-label text-xs">No. SK Bupati / Pengangkatan Kepala Sekolah</label>
                        <input type="text" id="input_sk_kepsek" value="{{ $skBupatiKepsek }}" class="form-input text-xs font-mono" placeholder="Contoh: 821.2/421/424.103/2021">
                    </div>

                    <div>
                        <label for="input_sk_bendahara" class="form-label text-xs">No. SK Bupati / Tugas Bendahara BOSP</label>
                        <input type="text" id="input_sk_bendahara" value="{{ $skBupatiBendahara }}" class="form-input text-xs font-mono" placeholder="Contoh: 420/ 220 /HK /424.013/2024">
                    </div>

                    <div class="pt-3 border-t border-slate-200 space-y-3">
                        <div>
                            <label for="input_saldo_kas" class="form-label text-xs">a. Saldo Kas Tunai (Uang Kertas & Logam)</label>
                            <input type="text" id="input_saldo_kas" value="{{ number_format($subtotalFisikKas, 0, ',', '.') }}" class="form-input text-sm font-bold font-mono" placeholder="0">
                        </div>

                        <div>
                            <label for="input_saldo_bank" class="form-label text-xs">b. Saldo Rekening Bank</label>
                            <input type="text" id="input_saldo_bank" value="{{ number_format($saldoBank, 0, ',', '.') }}" class="form-input text-sm font-bold font-mono" placeholder="0">
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Total Kas Dihitung (a + b):</span>
                            <span class="font-bold font-mono text-slate-800" id="display-total-ab">Rp {{ number_format($totalKasB, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Saldo BKU Sistem:</span>
                            <span class="font-bold font-mono text-slate-800">Rp {{ number_format($saldoBkuA, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between font-bold pt-1 border-t border-slate-200">
                            <span class="text-slate-700">Perbedaan:</span>
                            <span class="font-mono {{ abs($perbedaan) < 0.01 ? 'text-emerald-700' : 'text-red-600' }}" id="display-perbedaan">
                                Rp {{ number_format($perbedaan, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sisi Kanan: Pratinjau Dokumen K-7c --}}
        <div class="lg:col-span-8 space-y-4">
            <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-slate-200 shadow-sm no-print">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    Pratinjau Berita Acara
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
            <div class="bg-white p-8 md:p-12 rounded-2xl border border-slate-300 shadow-md font-serif text-black text-xs leading-relaxed max-w-full overflow-x-auto">
                <div class="flex justify-end mb-3">
                    <div class="border border-black px-2 py-0.5 font-bold text-xs">FORMULIR BOS K7c</div>
                </div>

                <div class="text-center font-bold text-sm underline mb-1 tracking-wide">BERITA ACARA PEMERIKSAAN KAS</div>
                <div class="text-center font-bold text-xs uppercase mb-6 tracking-wider">PERIODE : <span id="pv-periode">{{ $tanggalPenutupan }}</span></div>

                <p class="text-justify mb-3 leading-relaxed">
                    Pada hari <span id="pv-hari-narasi">{{ $hariPenutupan }}</span>, tanggal <span id="pv-tgl-narasi">{{ $tanggalPenutupan }}</span> yang bertanda tangan di bawah ini, Saya Kepala Sekolah yang ditunjuk berdasarkan Surat Keputusan Bupati Kab. {{ $profil?->kabupaten ?? 'Pasuruan' }} No. <span id="pv-sk-kepsek" class="font-sans font-medium">{{ $skBupatiKepsek }}</span>
                </p>

                <table class="w-full mb-3 ml-6 text-xs">
                    <tr>
                        <td class="w-24 py-0.5">Nama</td>
                        <td class="w-4 text-center">:</td>
                        <td class="font-sans font-medium">{{ $profil?->nama_kepsek ?? '....................................' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Jabatan</td>
                        <td class="text-center">:</td>
                        <td>Kepala Sekolah</td>
                    </tr>
                </table>

                <p class="text-justify mb-3">Melakukan pemeriksaan KAS kepada :</p>

                <table class="w-full mb-3 ml-6 text-xs">
                    <tr>
                        <td class="w-24 py-0.5">Nama</td>
                        <td class="w-4 text-center">:</td>
                        <td class="font-sans font-medium">{{ $profil?->nama_bendahara ?? '....................................' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Jabatan</td>
                        <td class="text-center">:</td>
                        <td>Bendahara BOS / Pemegang KAS</td>
                    </tr>
                </table>

                <p class="text-justify mb-4 leading-relaxed">
                    Yang berdasarkan Surat Keputusan Bupati Kab. {{ $profil?->kabupaten ?? 'Pasuruan' }} No. <span id="pv-sk-bendahara" class="font-sans font-medium">{{ $skBupatiBendahara }}</span> ditugaskan dengan pengurusan uang BOSP Berdasarkan pemeriksaan kas serta bukti-bukti dalam pengurusan itu, kami menemui kenyataan sebagai berikut :
                </p>

                <p class="mb-2">Jumlah uang yang dihitung dihadapan Bendahara/ Pemegang Kas adalah :</p>

                <table class="w-11/12 ml-4 mb-6 text-xs">
                    <tr>
                        <td class="w-72 py-0.5">a Saldo KAS (Uang kertas dan uang logam)</td>
                        <td class="w-4 text-center">:</td>
                        <td class="font-mono" id="pv-saldo-kas">Rp {{ number_format($subtotalFisikKas, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">b Saldo Bank</td>
                        <td class="text-center">:</td>
                        <td class="font-mono" id="pv-saldo-bank">Rp {{ number_format($saldoBank, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold border-t border-slate-200">
                        <td class="py-0.5">&nbsp;&nbsp;&nbsp;Jumlah</td>
                        <td class="text-center">:</td>
                        <td class="font-mono" id="pv-total-ab">Rp {{ number_format($totalKasB, 2, ',', '.') }}</td>
                    </tr>
                    <tr><td colspan="3" class="h-2"></td></tr>
                    <tr class="font-bold">
                        <td class="py-0.5">Saldo menurut Buku Kas Umum (BKU)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono">Rp {{ number_format($saldoBkuA, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="font-bold">
                        <td class="py-0.5">Perbedaan Antara Saldo KAS dan Kas Umum</td>
                        <td class="text-center">:</td>
                        <td class="font-mono" id="pv-perbedaan">Rp {{ number_format($perbedaan, 0, ',', '.') }}</td>
                    </tr>
                </table>

                {{-- Tanda Tangan --}}
                <table class="w-full text-xs mt-10">
                    <tr>
                        <td class="w-1/2 text-center align-top">
                            Bendahara<br>
                            Pemegang KAS
                            <div class="h-16"></div>
                            <strong>{{ $profil?->nama_bendahara ?? '....................................' }}</strong><br>
                            NIP. {{ $profil?->nip_bendahara ?? '....................................' }}
                        </td>
                        <td class="w-1/2 text-center align-top">
                            Kepala Sekolah<br>
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

    {{-- Script Realtime K7c --}}
    <script>
        // Sinkronkan tanggal berita acara dengan bulan/tahun terpilih sebelum
        // submit filter (tanggal_penutupan_lalu tidak ada di halaman ini).
        function syncTanggalFilter() {
            var bulanSel = document.getElementById('bulan');
            var tahunSel = document.getElementById('tahun');
            if (!bulanSel) return;
            var bulan = parseInt(bulanSel.value, 10);
            var tahun = tahunSel ? parseInt(tahunSel.value, 10) : new Date().getFullYear();
            if (!bulan || !tahun || isNaN(tahun)) return;
            function pad(n) { return n < 10 ? '0' + n : '' + n; }
            var tglIni = document.getElementById('tanggal_penutupan');
            var lastIni = new Date(tahun, bulan, 0);
            if (tglIni) tglIni.value = lastIni.getFullYear() + '-' + pad(lastIni.getMonth() + 1) + '-' + pad(lastIni.getDate());
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saldoBkuA = {{ (float) $saldoBkuA }};
            const skKepsekInput = document.getElementById('input_sk_kepsek');
            const skBendaharaInput = document.getElementById('input_sk_bendahara');
            const kasInput = document.getElementById('input_saldo_kas');
            const bankInput = document.getElementById('input_saldo_bank');
            const tglIniInput = document.getElementById('tanggal_penutupan');
            const btnPdf = document.getElementById('btn-unduh-pdf');

            function formatRupiah(num) {
                return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function parseNominal(str) {
                if (!str) return 0;
                let clean = String(str).replace(/\./g, '').replace(/,/g, '.');
                let val = parseFloat(clean);
                return isNaN(val) ? 0 : val;
            }

            function hitung() {
                let kas = parseNominal(kasInput.value);
                let bank = parseNominal(bankInput.value);
                let total = kas + bank;
                let perbedaan = saldoBkuA - total;

                document.getElementById('pv-sk-kepsek').innerText = skKepsekInput.value || '-';
                document.getElementById('pv-sk-bendahara').innerText = skBendaharaInput.value || '-';
                document.getElementById('pv-saldo-kas').innerText = 'Rp ' + formatRupiah(kas);
                document.getElementById('pv-saldo-bank').innerText = 'Rp ' + formatRupiah(bank);
                document.getElementById('pv-total-ab').innerText = 'Rp ' + formatRupiah(total);
                document.getElementById('display-total-ab').innerText = 'Rp ' + formatRupiah(total);

                let dispPerbedaan = document.getElementById('display-perbedaan');
                let pvPerbedaan = document.getElementById('pv-perbedaan');
                dispPerbedaan.innerText = 'Rp ' + formatRupiah(perbedaan);
                pvPerbedaan.innerText = 'Rp ' + Math.round(perbedaan).toLocaleString('id-ID');

                if (Math.abs(perbedaan) < 0.01) {
                    dispPerbedaan.className = 'font-mono text-emerald-700';
                } else {
                    dispPerbedaan.className = 'font-mono text-red-600';
                }

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
                if (skKepsekInput.value) params.set('sk_bupati_kepsek', skKepsekInput.value);
                if (skBendaharaInput.value) params.set('sk_bupati_bendahara', skBendaharaInput.value);
                if (bankInput.value) params.set('saldo_bank', parseNominal(bankInput.value));

                // Halaman ini hanya punya satu input "Saldo Kas Tunai"; kirim
                // nilai hasil edit manual ke PDF via kas_fisik (override server).
                if (kasInput.value.trim() !== '') {
                    params.set('kas_fisik', parseNominal(kasInput.value));
                } else {
                    params.delete('kas_fisik');
                }

                btnPdf.href = "{{ route('laporan.k7c') }}?" + params.toString();
            }

            skKepsekInput.addEventListener('input', hitung);
            skBendaharaInput.addEventListener('input', hitung);
            kasInput.addEventListener('input', hitung);
            bankInput.addEventListener('input', hitung);
            tglIniInput.addEventListener('change', function() {
                document.getElementById('form-filter').submit();
            });

            hitung();
        });
    </script>
</x-app-layout>

