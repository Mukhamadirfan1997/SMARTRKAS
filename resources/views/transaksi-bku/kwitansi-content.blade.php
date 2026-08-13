@php
    $profil         = $profil ?? null;
    $namaSekolah    = $profil ? ($profil->nama ?? '')                : '...........................';
    $npsn           = $profil ? ($profil->npsn ?? '')                : '...........................';
    $kab            = $profil ? ($profil->kabupaten ?? '')           : '';
    $kec            = $profil ? ($profil->kecamatan ?? '')           : '';
    $namaKepsek     = $profil ? ($profil->nama_kepsek ?? '')         : '...........................';
    $nipKepsek      = $profil ? ($profil->nip_kepsek ?? '')          : '...........................';
    $namaBendahara  = $profil ? ($profil->nama_bendahara ?? '')      : '...........................';
    $nipBendahara   = $profil ? ($profil->nip_bendahara ?? '')       : '...........................';

    $tanggal  = \Carbon\Carbon::parse($transaksiBku->tanggal);
    $tglFormat = $tanggal->translatedFormat('d F Y');

    /* ===== TERBILANG ===== */
    if (!function_exists('terbilang_num')) {
    function terbilang_num($n) {
        $n = (int) abs($n);
        $satuan = ['','satu','dua','tiga','empat','lima','enam',
                   'tujuh','delapan','sembilan','sepuluh','sebelas'];
        if ($n < 12)                 return $satuan[$n];
        if ($n < 20)                 return $satuan[$n-10].' belas';
        if ($n < 100)                return $satuan[(int)($n/10)].' puluh '.terbilang_num($n%10);
        if ($n < 200)                return 'seratus '.terbilang_num($n-100);
        if ($n < 1000)               return $satuan[(int)($n/100)].' ratus '.terbilang_num($n%100);
        if ($n < 2000)               return 'seribu '.terbilang_num($n-1000);
        if ($n < 1000000)            return terbilang_num((int)($n/1000)).' ribu '.terbilang_num($n%1000);
        if ($n < 1000000000)         return terbilang_num((int)($n/1000000)).' juta '.terbilang_num($n%1000000);
        return terbilang_num((int)($n/1000000000)).' miliar '.terbilang_num($n%1000000000);
    }
    }
    $terbilang = ucfirst(trim(terbilang_num($transaksiBku->jumlah))).' Rupiah';

    /* ===== PROGRAM HIERARKI ===== */
    $namaKegiatan   = '-';
    $namaProgram    = '-';
    $namaSubProgram = '-';
    $kodeRekening   = '-';
    $namaRekening   = '-';

    $prog     = $transaksiBku->rkasItem ? $transaksiBku->rkasItem->program : null;
    $rekening = $transaksiBku->rkasItem ? $transaksiBku->rkasItem->kodeRekening : null;

    if ($prog === null && $transaksiBku->notaBku) {
        $prog     = $transaksiBku->notaBku->kegiatan;
        $rekening = $transaksiBku->notaBku->kodeRekening;
    }

    if ($rekening) {
        $kodeRekening = $rekening->kode ?? '-';
        $namaRekening = $rekening->nama ?? '-';
    }

    if ($prog) {
        $kodeKegiatan = rtrim($prog->kode ?? '', '.');
        $segments = explode('.', $kodeKegiatan);
        $kodeSubProgram = isset($segments[0]) && isset($segments[1]) ? $segments[0] . '.' . $segments[1] . '.' : '-';
        $kodeProgram    = isset($segments[0]) ? $segments[0] . '.' : '-';
        $namaKegiatan   = $prog->kode . ' ' . $prog->nama;
        $namaSubProgram = ($kodeSubProgram !== '-' ? $kodeSubProgram . ' ' : '') . ($prog->sub_program ?? '-');
        $namaProgram    = ($kodeProgram !== '-' ? $kodeProgram . ' ' : '') . ($prog->program ?? '-');
    }

    /* Terima Dari: sesuai contoh PDF — selalu Kepala Sekolah institusi tersebut */
    $terimaDari = 'Kepala ' . $namaSekolah;
    $suffixKec  = trim((string) $kec);
    if ($suffixKec !== '' && !str_ends_with(mb_strtoupper(trim($namaSekolah)), mb_strtoupper($suffixKec))) {
        $terimaDari .= ' ' . $suffixKec;
    }
@endphp

<div class="wrap">

    {{-- JUDUL --}}
    <div class="judul">BUKTI PEMBAYARAN</div>

    {{-- FIELD UTAMA --}}
    <table class="field-table">
        <tr>
            <td class="lbl">No</td>
            <td class="sep">:</td>
            <td class="val"><strong>{{ $transaksiBku->no_bukti }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">Kegiatan</td>
            <td class="sep">:</td>
            <td class="val">{{ $namaKegiatan }}</td>
        </tr>
        <tr>
            <td class="lbl">Program</td>
            <td class="sep">:</td>
            <td class="val">{{ $namaProgram }}</td>
        </tr>
        <tr>
            <td class="lbl">Sub Program</td>
            <td class="sep">:</td>
            <td class="val">{{ $namaSubProgram }}</td>
        </tr>
        <tr>
            <td class="lbl">Kode Rekening</td>
            <td class="sep">:</td>
            <td class="val">
                @if($kodeRekening !== '-' && $kodeRekening !== '')
                    {{ $kodeRekening }} - {{ $namaRekening }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Terima Dari</td>
            <td class="sep">:</td>
            <td class="val">{{ $terimaDari }}</td>
        </tr>
        @if($transaksiBku->metode_pengadaan === 'siplah' && !empty($transaksiBku->no_invoice_siplah))
        <tr>
            <td class="lbl">No. Invoice SIPLah</td>
            <td class="sep">:</td>
            <td class="val">{{ $transaksiBku->no_invoice_siplah }}</td>
        </tr>
        @endif
        <tr>
            <td class="lbl">Sebesar</td>
            <td class="sep">:</td>
            <td class="val">
                <span class="sebesar-rp">Rp &nbsp;{{ number_format($transaksiBku->jumlah, 0, ',', '.') }},00</span>
            </td>
        </tr>
        <tr>
            <td class="lbl"></td>
            <td class="sep"></td>
            <td class="val">
                <span class="terbilang">({{ $terbilang }})</span>
            </td>
        </tr>
    </table>

    {{-- UNTUK --}}
    @php
        $untukUtama = $transaksiBku->rkasItem
            ? (string) $transaksiBku->rkasItem->uraian
            : (string) ($transaksiBku->uraian ?? '');
        $untukSub = (string) ($transaksiBku->uraian ?? '');
        if ($untukSub !== '') {
            $untukSub = preg_replace('/\s+/', ' ', trim(mb_strtolower($untukSub))) === preg_replace('/\s+/', ' ', trim(mb_strtolower($untukUtama)))
                ? ''
                : $untukSub;
        }
    @endphp
    <table class="field-table" style="margin-top:3px;">
        <tr>
            <td class="lbl">Untuk</td>
            <td class="sep">:</td>
            <td class="val">
                <div class="untuk-box">
                    @if($untukUtama !== '')
                        <div>{{ $untukUtama }}</div>
                    @endif
                    @if($untukSub !== '')
                        <div class="untuk-sub">{{ $untukSub }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- TANDA TANGAN --}}
    <table class="ttd-table">
        <tr>
            <td>
                <div style="font-size:10px; visibility:hidden; margin-bottom:2px;">-</div>
                <div class="ttd-jabatan">Kepala {{ $namaSekolah }}</div>
                <div class="ttd-nama">{{ $namaKepsek }}</div>
                <div class="ttd-nip">NIP. {{ $nipKepsek }}</div>
            </td>
            <td>
                <div style="font-size:10px; margin-bottom:2px;">Lunas Bayar, -</div>
                <div class="ttd-jabatan">Bendahara</div>
                <div class="ttd-nama">{{ $namaBendahara }}</div>
                <div class="ttd-nip">NIP. {{ $nipBendahara }}</div>
            </td>
            <td>
                <div style="font-size:10px; margin-bottom:2px;">{{ $kab }}, {{ $tglFormat }}</div>
                <div class="ttd-jabatan">Yang menerima</div>
                <div class="ttd-nama">{{ strtoupper($transaksiBku->toko_penerima ?? '..........................') }}</div>
                <div class="ttd-nip">-</div>
            </td>
        </tr>
    </table>

</div>
