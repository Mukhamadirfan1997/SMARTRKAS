<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan BKU - {{ $bulan ? \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') : '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: white; }

        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 10px; }
        .header .nama-sekolah { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header .alamat { font-size: 10px; color: #444; margin-top: 2px; }
        .header .judul { font-size: 12px; font-weight: bold; margin-top: 6px; text-transform: uppercase; letter-spacing: 2px; }
        .header .sub-judul { font-size: 10px; color: #555; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        thead tr { background-color: #1e3a5f; color: white; }
        th { padding: 5px 4px; text-align: left; font-size: 9px; white-space: nowrap; border: 1px solid #1e3a5f; }
        td { padding: 4px; border: 1px solid #ddd; vertical-align: top; }
        tbody tr:nth-child(even) { background-color: #f8faff; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', monospace; }
        .text-green { color: #15803d; font-weight: 600; }
        .text-red { color: #dc2626; font-weight: 600; }
        .text-blue { color: #1d4ed8; font-weight: 600; }
        tfoot tr { background-color: #e8f0fe; font-weight: bold; }
        tfoot td { border: 1px solid #94a3b8; padding: 5px 4px; }

        .summary-text { margin-top: 10px; font-size: 10px; }
        .summary-text span { margin-right: 20px; }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            @page { margin: 2.5cm 3cm; size: A4 landscape; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="nama-sekolah">{{ $profil?->nama ?? 'Nama Sekolah' }}</div>
        <div class="alamat">{{ $profil?->alamat ?? '' }}</div>
        <div class="judul">Buku Kas Umum (BKU)</div>
        <div class="sub-judul">
            Bulan: {{ $bulan ? \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') . ' ' . ($tahunAnggaranAktif?->tahun ?? date('Y')) : '-' }}
            &nbsp;|&nbsp;
            Tahun Anggaran: {{ $tahunAnggaranAktif?->tahun ?? '-' }}
            &nbsp;|&nbsp;
            NPSN: {{ $profil?->npsn ?? '-' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:10%">No Bukti</th>
                <th style="width:10%">Kode Kegiatan</th>
                <th style="width:10%">Kode Rekening</th>
                <th style="width:12%">Jenis Belanja</th>
                <th style="width:18%">Uraian</th>
                <th style="width:10%">Toko/Penerima</th>
                <th style="width:6%">Pengadaan</th>
                <th style="width:7%" class="text-right">Penerimaan</th>
                <th style="width:7%" class="text-right">Pengeluaran</th>
                <th style="width:7%" class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @php $saldo = $saldoAwal; @endphp
            @forelse($transaksis as $no => $t)
                @php
                    $jenisBelanja = $t->rkasItem?->kodeRekening?->jenisBelanja?->nama ?? '';
                @endphp
                <tr>
                    <td class="text-center">{{ $no + 1 }}</td>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($t->tanggal)->format('d/m/Y') }}</td>
                    <td class="font-mono" style="font-size:8px">{{ $t->no_bukti }}</td>
                    <td style="font-size:8px">{{ $t->rkasItem?->program?->kode ?? '-' }}</td>
                    <td class="font-mono" style="font-size:8px">{{ $t->rkasItem?->kodeRekening?->kode ?? '-' }}</td>
                    <td style="font-size:8px">{{ $jenisBelanja ?: '-' }}</td>
                    <td style="font-size:8px">{{ $t->uraian ?? $t->rkasItem?->uraian ?? '-' }}</td>
                    <td style="font-size:8px">{{ $t->toko_penerima ?? '-' }}</td>
                    <td style="font-size:8px;text-align:center">
                        @if($t->metode_pengadaan === 'siplah')
                            SIPLAH
                        @elseif($t->metode_pengadaan === 'non_siplah')
                            Non-SIPLAH
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if(strtolower($t->jenis) == 'penerimaan')
                            <span class="text-green">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</span>
                        @else &mdash;
                        @endif
                    </td>
                    <td class="text-right">
                        @if(strtolower($t->jenis) == 'pengeluaran')
                            <span class="text-red">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</span>
                        @else &mdash;
                        @endif
                    </td>
                    <td class="text-right">
                        <span class="{{ $t->saldo_berjalan >= 0 ? 'text-blue' : 'text-red' }}">
                            Rp {{ number_format($t->saldo_berjalan, 0, ',', '.') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding:20px; color:#888">
                        Tidak ada transaksi pada bulan ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" class="text-right"><strong>TOTAL</strong></td>
                <td class="text-right text-green">Rp {{ number_format($totalPenerimaan, 0, ',', '.') }}</td>
                <td class="text-right text-red">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                <td class="text-right text-blue">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-text">
        <strong>Saldo Awal:</strong> Rp {{ number_format($saldoAwal, 0, ',', '.') }}
        &nbsp;|&nbsp;
        <strong>Total Penerimaan:</strong> <span style="color:#15803d">Rp {{ number_format($totalPenerimaan, 0, ',', '.') }}</span>
        &nbsp;|&nbsp;
        <strong>Total Pengeluaran:</strong> <span style="color:#dc2626">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</span>
        &nbsp;|&nbsp;
        <strong>Saldo Akhir:</strong> <span style="color:#1d4ed8">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</span>
    </div>

    <table style="width:100%;border-collapse:collapse;border:none !important;margin-top:30px;">
        <tr>
            <td style="border:none !important;width:45%;text-align:center;vertical-align:top;padding:0 10px;">
                <div style="font-size:11px;height:75px;line-height:1.4;">Mengetahui,<br>Kepala Sekolah</div>
                <div style="font-weight:bold;font-size:11px;margin-top:0;">{{ $profil?->nama_kepsek ?? '.....................' }}</div>
                <div style="font-size:10px;color:#555;margin-top:4px;">NIP. {{ $profil?->nip_kepsek ?? '.....................' }}</div>
            </td>
            <td style="border:none !important;width:10%;"></td>
            <td style="border:none !important;width:45%;text-align:center;vertical-align:top;padding:0 10px;">
                <div style="font-size:11px;height:75px;line-height:1.4;">{{ $profil?->kecamatan ?? '' }}, {{ $tanggalCetak }}<br><strong>Bendahara</strong></div>
                <div style="font-weight:bold;font-size:11px;margin-top:0;">{{ $profil?->nama_bendahara ?? '.....................' }}</div>
                <div style="font-size:10px;color:#555;margin-top:4px;">NIP. {{ $profil?->nip_bendahara ?? '.....................' }}</div>
            </td>
        </tr>
    </table>

    @unless(request('cetak') == 'pdf')
    <div class="no-print" style="position: fixed; top: 16px; right: 16px; display: flex; gap: 8px;">
        <button onclick="window.print()"
            style="background:#1e3a5f;color:white;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
            Cetak
        </button>
        <button onclick="window.close()"
            style="background:#6b7280;color:white;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
            Tutup
        </button>
    </div>
    @endunless
</body>
</html>
