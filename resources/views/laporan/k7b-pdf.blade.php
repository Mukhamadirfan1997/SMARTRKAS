<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir BOS-K7b - {{ $profil?->nama ?? 'Sekolah' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11px; color: #000; background: #fff; line-height: 1.35; padding: 1.5cm 2cm; }
        .top-badge { float: right; border: 1.5px solid #000; padding: 3px 10px; font-weight: bold; font-size: 11px; margin-bottom: 8px; }
        .clear { clear: both; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin-bottom: 16px; letter-spacing: 0.5px; }
        
        .table-info { width: 100%; margin-bottom: 12px; font-size: 11px; border-collapse: collapse; }
        .table-info td { padding: 1.5px 0; vertical-align: top; }
        .table-info .lbl { width: 42%; }
        .table-info .sep { width: 2%; text-align: center; }
        .table-info .val { width: 56%; }

        .table-rincian { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 6px; }
        .table-rincian td { padding: 1.5px 4px; vertical-align: middle; }
        .table-rincian .col-no { width: 4%; }
        .table-rincian .col-jenis { width: 32%; }
        .table-rincian .col-nominal { width: 16%; }
        .table-rincian .col-qty { width: 12%; text-align: right; }
        .table-rincian .col-unit { width: 12%; }
        .table-rincian .col-total { width: 24%; text-align: right; }

        .subtotal-row { border-top: 1px solid #000; border-bottom: 1.5px double #000; font-weight: bold; }
        .subtotal-row td { padding: 3px 4px; }

        .table-summary { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; }
        .table-summary td { padding: 2px 4px; }

        .box-penjelasan { border: 1.5px solid #000; padding: 4px 8px; min-height: 28px; width: 100%; font-weight: bold; }

        .signature-table { width: 100%; margin-top: 20px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 50%; vertical-align: top; font-size: 11px; }
        .ttd-space { height: 50px; }
        
        @page { size: A4 portrait; margin: 0; }
    </style>
</head>
<body>
    <div class="top-badge">FORMULIR BOS-K7b</div>
    <div class="clear"></div>

    <div class="title">REGISTER PENUTUPAN KAS</div>

    <table class="table-info">
        <tr>
            <td class="lbl">Tanggal Penutupan Kas Bulan ini</td>
            <td class="sep">:</td>
            <td class="val">{{ $tanggalPenutupan }}</td>
        </tr>
        <tr>
            <td class="lbl">Nama Penutup KAS (Pemegang KAS)</td>
            <td class="sep">:</td>
            <td class="val">{{ $profil?->nama_bendahara ?? 'Bendahara' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Penutupan KAS Bulan Lalu</td>
            <td class="sep">:</td>
            <td class="val">{{ $tanggalPenutupanLalu }}</td>
        </tr>
        <tr>
            <td class="lbl">Jumlah Total Penerimaan BKU (D)</td>
            <td class="sep">:</td>
            <td class="val">Rp. {{ number_format($totalPenerimaanD, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Jumlah Total Pengeluaran BKU (K)</td>
            <td class="sep">:</td>
            <td class="val">Rp. {{ number_format($totalPengeluaranK, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td class="lbl">Saldo Buku Kas Umum (A=D-K)</td>
            <td class="sep">:</td>
            <td class="val">Rp. {{ number_format($saldoBkuA, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td class="lbl">Saldo Kas Tunai</td>
            <td class="sep">:</td>
            <td class="val">Rp. {{ number_format($subtotalFisikKas, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- 1. Uang Kertas --}}
    <table class="table-rincian">
        @php $firstKertas = true; @endphp
        @foreach($rincianKertas as $k)
            <tr>
                <td class="col-no">{{ $firstKertas ? '1.' : '' }}</td>
                <td class="col-jenis">Lembaran uang kertas</td>
                <td class="col-nominal">Rp {{ $k['label'] }}</td>
                <td class="col-qty">{{ number_format($k['lembar'], 0, ',', '.') }}</td>
                <td class="col-unit">Lembar</td>
                <td class="col-total">Rp. {{ number_format($k['total'], 2, ',', '.') }}</td>
            </tr>
            @php $firstKertas = false; @endphp
        @endforeach
        <tr class="subtotal-row">
            <td colspan="4" style="text-align: right;">Sub Jumlah Lembar uang kertas (1)</td>
            <td colspan="2" style="text-align: right;">Rp. {{ number_format($subtotalKertas, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- 2. Uang Logam --}}
    <table class="table-rincian" style="margin-top: 4px;">
        @php $firstLogam = true; @endphp
        @foreach($rincianLogam as $l)
            <tr>
                <td class="col-no">{{ $firstLogam ? '2.' : '' }}</td>
                <td class="col-jenis">Keping uang logam</td>
                <td class="col-nominal">Rp {{ $l['label'] }}</td>
                <td class="col-qty">{{ number_format($l['keping'], 0, ',', '.') }}</td>
                <td class="col-unit">Keping</td>
                <td class="col-total">Rp. {{ number_format($l['total'], 2, ',', '.') }}</td>
            </tr>
            @php $firstLogam = false; @endphp
        @endforeach
        <tr class="subtotal-row">
            <td colspan="4" style="text-align: right;">Sub Jumlah Keping uang logam (2)</td>
            <td colspan="2" style="text-align: right;">Rp. {{ number_format($subtotalLogam, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- 3. Saldo Bank & Summary --}}
    <table class="table-summary">
        <tr>
            <td style="width: 4%;">3.</td>
            <td style="width: 44%;">Saldo Rekening Bank</td>
            <td style="width: 24%; text-align: right; font-weight: bold;">Sub Jumlah (3)</td>
            <td style="width: 28%; text-align: right; font-weight: bold;">Rp. {{ number_format($saldoBank, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold; border-top: 1px solid #000; border-bottom: 1.5px double #000;">
            <td>B.</td>
            <td colspan="2" style="text-align: right;">Jumlah (1+2+3)</td>
            <td style="text-align: right;">Rp. {{ number_format($totalKasB, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td colspan="3" style="padding-top: 6px;">Perbedaan (A-B)</td>
            <td style="text-align: right; padding-top: 6px;">Rp. {{ number_format($perbedaan, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div style="margin-top: 6px;">
        <div style="margin-bottom: 2px; font-weight: normal;">Penjelasan Perbedaan:</div>
        <div class="box-penjelasan">
            {{ $penjelasanPerbedaan }}
        </div>
    </div>

    {{-- Tanda Tangan --}}
    <table class="signature-table">
        <tr>
            <td style="text-align: center;">
                Pasuruan, {{ $tanggalPenutupan }}<br>
                Yang diperiksa,<br>
                <strong>Bendahara</strong><br>
                <strong>{{ $profil?->nama ?? 'Sekolah' }}</strong>
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_bendahara ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_bendahara ?? '....................................' }}
            </td>
            <td style="text-align: center;">
                Tanggal, {{ $tanggalPenutupan }}<br>
                Yang Memeriksa,<br>
                <strong>Kepala Sekolah</strong><br>
                <strong>{{ $profil?->nama ?? 'Sekolah' }}</strong>
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_kepsek ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_kepsek ?? '....................................' }}
            </td>
        </tr>
    </table>
</body>
</html>

