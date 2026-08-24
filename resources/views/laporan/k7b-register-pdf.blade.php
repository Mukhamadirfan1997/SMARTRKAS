<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register Penutupan Kas K7b - {{ $profil?->nama ?? 'Sekolah' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 10px; color: #000; background: #fff; line-height: 1.35; padding: 24px 32px; }
        .kop { display: flex; align-items: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 12px; }
        .kop-text { flex-grow: 1; text-align: center; }
        .kop-text .nama { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .kop-text .detail { font-size: 9.5px; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin-bottom: 4px; letter-spacing: 0.5px; }
        .subtitle { text-align: center; font-size: 10px; margin-bottom: 12px; }

        .table-register { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .table-register th { border: 1px solid #000; background-color: #eee; padding: 4px 3px; text-align: center; vertical-align: middle; }
        .table-register td { border: 1px solid #000; padding: 3.5px 4px; vertical-align: middle; }
        .table-register .num { text-align: right; white-space: nowrap; }
        .table-register .ctr { text-align: center; }
        .table-register tfoot td { font-weight: bold; background-color: #f5f5f5; }

        .signature-table { width: 100%; margin-top: 22px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 50%; vertical-align: top; font-size: 10px; }
        .ttd-space { height: 48px; }

        @page { size: A4 landscape; margin: 0; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="kop-text">
            <div class="nama">{{ $profil?->nama ?? 'SEKOLAH' }}</div>
            @if($profil?->alamat)
                <div class="detail">{{ $profil->alamat }}</div>
            @endif
            @if($profil?->kabupaten || $profil?->kecamatan)
                <div class="detail">
                    @if($profil?->kecamatan)Kec. {{ $profil->kecamatan }}@endif
                    @if($profil?->kabupaten){{ $profil?->kecamatan ? ' — ' : '' }}Kab. {{ $profil->kabupaten }}@endif
                </div>
            @endif
            @if($profil?->npsn)
                <div class="detail">NPSN: {{ $profil->npsn }}</div>
            @endif
        </div>
    </div>

    <div class="title">REGISTER PENUTUPAN KAS</div>
    <div class="subtitle">
        Tahun Anggaran {{ $tahunAnggaran->tahun }}
        &mdash; Periode Bulan {{ \Carbon\Carbon::createFromDate($tahunAnggaran->tahun, max(1, (int) $dari), 1)->translatedFormat('F') }} s.d.
        {{ \Carbon\Carbon::createFromDate($tahunAnggaran->tahun, min(12, (int) $sampai), 1)->translatedFormat('F') }}
        @if($sumberDanaLabel)
            &mdash; Sumber Dana: {{ $sumberDanaLabel }}
        @else
            &mdash; Sumber Dana: Semua
        @endif
    </div>

    <table class="table-register">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3%;">No</th>
                <th rowspan="2" style="width: 9%;">Bulan</th>
                <th rowspan="2" style="width: 8%;">Tgl Penutupan</th>
                <th rowspan="2" style="width: 11%;">Saldo Awal</th>
                <th colspan="2">Mutasi Bulan Berjalan</th>
                <th rowspan="2" style="width: 11%;">Sisa Buku<br>(A = Awal + D - K)</th>
                <th colspan="4">Hasil Opname Fisik</th>
            </tr>
            <tr>
                <th style="width: 10%;">Penerimaan (D)</th>
                <th style="width: 10%;">Pengeluaran (K)</th>
                <th style="width: 9.5%;">Kas Tunai (1+2)</th>
                <th style="width: 9.5%;">Bank (3)</th>
                <th style="width: 9.5%;">Jumlah (B)</th>
                <th style="width: 9.5%;">Perbedaan (A-B)</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($rows as $row)
                <tr>
                    <td class="ctr">{{ $no++ }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="ctr">{{ $row['tanggal'] ?? '-' }}</td>
                    <td class="num">Rp. {{ number_format($row['awal'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['penerimaan'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['pengeluaran'], 2, ',', '.') }}</td>
                    <td class="num">Rp. {{ number_format($row['sisa'], 2, ',', '.') }}</td>
                    @if($row['riil'] === null || $row['riil'] === '')
                        <td class="ctr">-</td>
                        <td class="ctr">-</td>
                        <td class="ctr">-</td>
                        <td class="ctr">-</td>
                    @else
                        <td class="num">{{ number_format((float) $row['fisik'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $row['bank'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $row['riil'], 2, ',', '.') }}</td>
                        <td class="num {{ abs((float) $row['perbedaan']) < 0.01 ? '' : '' }}">{{ number_format((float) $row['perbedaan'], 2, ',', '.') }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="ctr">JUMLAH</td>
                <td class="num">{{ number_format($rows->sum('penerimaan'), 2, ',', '.') }}</td>
                <td class="num">{{ number_format($rows->sum('pengeluaran'), 2, ',', '.') }}</td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                <strong>Bendahara</strong>
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_bendahara ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_bendahara ?? '....................................' }}
            </td>
            <td>
                {{ $profil?->kabupaten ? ($profil->kabupaten . ', ') : '' }}{{ now()->translatedFormat('d F Y') }}<br>
                Yang Memeriksa,<br>
                <strong>Kepala Sekolah</strong><br>
                <div class="ttd-space"></div>
                <strong>{{ $profil?->nama_kepsek ?? '....................................' }}</strong><br>
                NIP. {{ $profil?->nip_kepsek ?? '....................................' }}
            </td>
        </tr>
    </table>
</body>
</html>
