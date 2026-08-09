<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bukti Pembayaran {{ $transaksiBku->no_bukti }}</title>
    <style>
        @page {
            margin: 25mm 30mm 20mm 25mm;
            size: 215mm 330mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
        }

        .wrap {
            width: 100%;
            margin-top: 20px;
        }

        .judul {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .field-table {
            width: 100%;
            border-collapse: collapse;
        }
        .field-table tr td {
            padding: 4px 0;
            vertical-align: top;
        }
        .field-table .lbl {
            width: 90px;
            font-weight: normal;
        }
        .field-table .sep {
            width: 12px;
            text-align: center;
        }

        .sebesar-rp {
            font-weight: bold;
            font-size: 12px;
        }
        .terbilang {
            font-style: italic;
            font-size: 10px;
        }

        .untuk-box {
            border: 1px solid #000;
            padding: 3px 6px;
            min-height: 28px;
            margin-top: 1px;
        }
        .untuk-box .untuk-sub {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .ttd-table td {
            text-align: center;
            vertical-align: top;
            padding: 0 4px;
            width: 33.33%;
        }
        .ttd-jabatan {
            font-size: 11px;
            height: 75px;
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

        .divider {
            border: none;
            border-top: 1px solid #aaa;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    @include('transaksi-bku.kwitansi-content', ['transaksiBku' => $transaksiBku, 'profil' => $profil])
</body>
</html>
