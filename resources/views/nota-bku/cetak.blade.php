<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Nota Belanja {{ $notaBku->no_nota }}</title>
    <style>
        @page {
            margin: 20mm 25mm 15mm 25mm;
            size: 215mm 330mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
        }

        .header-school {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header-school .nama {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header-school .alamat {
            font-size: 9px;
            margin-top: 2px;
        }

        .judul {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .field-table tr td {
            padding: 3px 0;
            vertical-align: top;
        }
        .field-table .lbl {
            width: 130px;
        }
        .field-table .sep {
            width: 12px;
            text-align: center;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 5px 6px;
        }
        .items-table th {
            background: #eee;
            font-size: 10px;
            text-align: center;
        }
        .items-table td.num {
            text-align: right;
            white-space: nowrap;
        }
        .items-table td.ctr {
            text-align: center;
        }

        .total-line {
            text-align: right;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .terbilang {
            font-style: italic;
            font-size: 10px;
            margin-bottom: 20px;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        .ttd-table td {
            text-align: center;
            vertical-align: top;
            padding: 0 4px;
            width: 50%;
        }
        .ttd-jabatan {
            font-size: 10px;
            height: 70px;
            line-height: 1.4;
        }
        .ttd-nama {
            font-size: 11px;
            font-weight: bold;
            text-decoration: underline;
        }
        .ttd-nip {
            font-size: 9px;
            margin-top: 4px;
        }

        .footer-note {
            margin-top: 24px;
            border-top: 1px solid #aaa;
            padding-top: 6px;
            font-size: 8px;
            color: #555;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $profil = \App\Models\PengaturanSekolah::get();
        $namaSekolah   = $profil->nama ?? '...........................';
        $npsn          = $profil->npsn ?? '';
        $alamatSekolah = trim(implode(', ', array_filter([
            $profil->alamat ?? '',
            $profil->kecamatan ?? '',
            $profil->kabupaten ?? '',
            $profil->provinsi ?? '',
        ])));
        $namaKepsek    = $profil->nama_kepsek ?? '...........................';
        $nipKepsek     = $profil->nip_kepsek ?? '...........................';
        $namaBendahara = $profil->nama_bendahara ?? '...........................';
        $nipBendahara  = $profil->nip_bendahara ?? '...........................';
        $kab           = $profil->kabupaten ?? '';
        $tanggal       = \Carbon\Carbon::parse($notaBku->tanggal)->translatedFormat('d F Y');
        $namaKegiatan  = ($notaBku->kegiatan->kode ?? '') . ' ' . ($notaBku->kegiatan->nama ?? '-');
        $namaSumber    = ($notaBku->sumberDana->kode ?? '') . ' - ' . ($notaBku->sumberDana->nama ?? '-');

        if (!function_exists('nota_terbilang')) {
        function nota_terbilang($n) {
            $n = (int) abs($n);
            $satuan = ['','satu','dua','tiga','empat','lima','enam',
                       'tujuh','delapan','sembilan','sepuluh','sebelas'];
            if ($n < 12)                 return $satuan[$n];
            if ($n < 20)                 return $satuan[$n-10].' belas';
            if ($n < 100)                return $satuan[(int)($n/10)].' puluh '.nota_terbilang($n%10);
            if ($n < 200)                return 'seratus '.nota_terbilang($n-100);
            if ($n < 1000)               return $satuan[(int)($n/100)].' ratus '.nota_terbilang($n%100);
            if ($n < 2000)               return 'seribu '.nota_terbilang($n-1000);
            if ($n < 1000000)            return nota_terbilang((int)($n/1000)).' ribu '.nota_terbilang($n%1000);
            if ($n < 1000000000)         return nota_terbilang((int)($n/1000000)).' juta '.nota_terbilang($n%1000000);
            return nota_terbilang((int)($n/1000000000)).' miliar '.nota_terbilang($n%1000000000);
        }
        }
        $terbilang = ucfirst(trim(nota_terbilang($total))).' Rupiah';
    @endphp

    <div class="header-school">
        <div class="nama">{{ $namaSekolah }}</div>
        <div class="alamat">{{ $alamatSekolah }}{{ $npsn !== '' ? ' · NPSN: ' . $npsn : '' }}</div>
    </div>

    <div class="judul">Nota Belanja</div>

    <table class="field-table">
        <tr>
            <td class="lbl">No. Nota</td>
            <td class="sep">:</td>
            <td><strong>{{ $notaBku->no_nota }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">Tanggal</td>
            <td class="sep">:</td>
            <td>{{ $tanggal }}</td>
        </tr>
        <tr>
            <td class="lbl">Kegiatan</td>
            <td class="sep">:</td>
            <td>{{ $namaKegiatan }}</td>
        </tr>
        <tr>
            <td class="lbl">Sumber Dana</td>
            <td class="sep">:</td>
            <td>{{ $namaSumber }}</td>
        </tr>
        @if(!empty($notaBku->toko_penerima))
        <tr>
            <td class="lbl">Toko / Penerima</td>
            <td class="sep">:</td>
            <td>{{ $notaBku->toko_penerima }}</td>
        </tr>
        @endif
        @if($notaBku->metode_pengadaan === 'siplah' && !empty($notaBku->no_invoice_siplah))
        <tr>
            <td class="lbl">No. Invoice SIPLah</td>
            <td class="sep">:</td>
            <td>{{ $notaBku->no_invoice_siplah }}</td>
        </tr>
        @endif
        @if(!empty($notaBku->uraian))
        <tr>
            <td class="lbl">Uraian</td>
            <td class="sep">:</td>
            <td>{{ $notaBku->uraian }}</td>
        </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Uraian Item</th>
                <th style="width: 55px;">Jumlah</th>
                <th style="width: 70px;">Satuan</th>
                <th style="width: 90px;">Harga Satuan</th>
                <th style="width: 95px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($notaBku->items as $item)
                <tr>
                    <td class="ctr">{{ $item->urutan }}</td>
                    <td>{{ $item->rkasItem->no_urut ?? '' }}. {{ $item->rkasItem->uraian ?? $item->satuan }}</td>
                    <td class="num">{{ number_format((float) $item->jumlah, 0, ',', '.') }}</td>
                    <td class="ctr">{{ $item->satuan ?? '-' }}</td>
                    <td class="num">{{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-line">Total : Rp {{ number_format($total, 0, ',', '.') }},-</div>
    <div class="terbilang">({{ $terbilang }})</div>

    @if($notaBku->transaksiBkus->isNotEmpty())
        <div style="font-size: 9px; margin-bottom: 4px;">
            <strong>Dibukukan sebagai transaksi BKU:</strong>
            {{ $notaBku->transaksiBkus->pluck('no_bukti')->join(', ') }}
        </div>
    @endif

    <table class="ttd-table">
        <tr>
            <td>
                <div style="font-size:10px; margin-bottom:2px;">Mengetahui,</div>
                <div class="ttd-jabatan">Kepala {{ $namaSekolah }}</div>
                <div class="ttd-nama">{{ strtoupper($namaKepsek) }}</div>
                <div class="ttd-nip">NIP. {{ $nipKepsek }}</div>
            </td>
            <td>
                <div style="font-size:10px; margin-bottom:2px;">{{ $kab }}, {{ $tanggal }}</div>
                <div class="ttd-jabatan">Bendahara</div>
                <div class="ttd-nama">{{ strtoupper($namaBendahara) }}</div>
                <div class="ttd-nip">NIP. {{ $nipBendahara }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">Dokumen ini dibuat otomatis oleh SmartRKAS. Nomor nota dan transaksi terkait bersifat permanen.</div>
</body>
</html>