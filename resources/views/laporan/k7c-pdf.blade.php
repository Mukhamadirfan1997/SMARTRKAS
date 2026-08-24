<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir BOS-K7c - {{ $profil?->nama ?? 'Sekolah' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11px; color: #000; background: #fff; line-height: 1.45; padding: 1.5cm 2cm; }
        .top-badge { float: right; border: 1.5px solid #000; padding: 3px 10px; font-weight: bold; font-size: 11px; margin-bottom: 12px; }
        .clear { clear: both; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin-bottom: 4px; letter-spacing: 0.5px; }
        .periode { text-align: center; font-size: 11px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
        
        .p-narasi { text-align: justify; text-indent: 0; margin-bottom: 12px; font-size: 11px; line-height: 1.5; }
        
        .table-person { width: 100%; margin: 8px 0 12px 30px; border-collapse: collapse; font-size: 11px; }
        .table-person td { padding: 1.5px 0; vertical-align: top; }
        .table-person .lbl { width: 15%; }
        .table-person .sep { width: 3%; text-align: center; }
        .table-person .val { width: 82%; }

        .table-hasil { width: 95%; margin: 10px auto 16px 20px; border-collapse: collapse; font-size: 11px; }
        .table-hasil td { padding: 2px 4px; vertical-align: top; }
        .table-hasil .col-label { width: 55%; }
        .table-hasil .col-sep { width: 3%; text-align: center; }
        .table-hasil .col-val { width: 42%; }

        .signature-table { width: 100%; margin-top: 30px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 50%; vertical-align: top; font-size: 11px; text-align: center; }
        .ttd-space { height: 60px; }
        
        @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body>
    <div class="top-badge">FORMULIR BOS K7c</div>
    <div class="clear"></div>

    <div class="title">BERITA ACARA PEMERIKSAAN KAS</div>
    <div class="periode">PERIODE : {{ $tanggalPenutupan }}</div>

    <div class="p-narasi">
        Pada hari <strong>{{ $hariPenutupan }}</strong> tanggal {{ $tanggalPenutupan }} yang bertanda tangan di bawah ini, Saya Kepala Sekolah yang ditunjuk berdasarkan Surat Keputusan Bupati Kab. {{ $profil?->kabupaten ?? 'Pasuruan' }} No. {{ $skBupatiKepsek }}
    </div>

    <table class="table-person">
        <tr>
            <td class="lbl">Nama</td>
            <td class="sep">:</td>
            <td class="val">{{ $profil?->nama_kepsek ?? '....................................' }}</td>
        </tr>
        <tr>
            <td class="lbl">Jabatan</td>
            <td class="sep">:</td>
            <td class="val">Kepala Sekolah</td>
        </tr>
    </table>

    <div class="p-narasi">
        Melakukan pemeriksaan KAS kepada :
    </div>

    <table class="table-person">
        <tr>
            <td class="lbl">Nama</td>
            <td class="sep">:</td>
            <td class="val">{{ $profil?->nama_bendahara ?? '....................................' }}</td>
        </tr>
        <tr>
            <td class="lbl">Jabatan</td>
            <td class="sep">:</td>
            <td class="val">Bendahara BOS / Pemegang KAS</td>
        </tr>
    </table>

    <div class="p-narasi">
        Yang berdasarkan Surat Keputusan Bupati Kab. {{ $profil?->kabupaten ?? 'Pasuruan' }} No. {{ $skBupatiBendahara }} ditugaskan dengan pengurusan uang BOSP Berdasarkan pemeriksaan kas serta bukti-bukti dalam pengurusan itu, kami menemui kenyataan sebagai berikut :
    </div>

    <div style="margin-top: 10px; margin-bottom: 6px; font-weight: normal;">
        Jumlah uang yang dihitung dihadapan Bendahara/ Pemegang Kas adalah :
    </div>

    <table class="table-hasil">
        <tr>
            <td class="col-label">a Saldo KAS (Uang kertas dan uang logam)</td>
            <td class="col-sep">:</td>
            <td class="col-val">Rp {{ number_format($subtotalFisikKas, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="col-label">b Saldo Bank</td>
            <td class="col-sep">:</td>
            <td class="col-val">Rp {{ number_format($saldoBank, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold; border-top: 1px solid #ddd;">
            <td class="col-label">&nbsp;&nbsp;&nbsp;Jumlah</td>
            <td class="col-sep">:</td>
            <td class="col-val">Rp {{ number_format($totalKasB, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3" style="height: 6px;"></td></tr>
        <tr style="font-weight: bold;">
            <td class="col-label">Saldo menurut Buku Kas Umum (BKU)</td>
            <td class="col-sep">:</td>
            <td class="col-val">Rp {{ number_format($saldoBkuA, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td class="col-label">Perbedaan Antara Saldo KAS dan Kas Umum</td>
            <td class="col-sep">:</td>
            <td class="col-val">RP {{ number_format($perbedaan, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Tanda Tangan --}}
    <table class="signature-table">
        <tr>
            <td>
                Bendahara<br>
                Pemegang KAS
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_bendahara ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_bendahara ?? '....................................' }}
            </td>
            <td>
                Kepala Sekolah<br>
                <strong>{{ $profil?->nama ?? 'Sekolah' }}</strong>
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_kepsek ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_kepsek ?? '....................................' }}
            </td>
        </tr>
    </table>
</body>
</html>

