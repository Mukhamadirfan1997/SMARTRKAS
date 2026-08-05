<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Realisasi Per Rekening - {{ $bulan ? \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') : '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: white; }

        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 15px; }
        .header .nama-sekolah { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header .alamat { font-size: 10px; color: #444; margin-top: 2px; }
        .header .judul { font-size: 12px; font-weight: bold; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .header .sub-judul { font-size: 10px; color: #555; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead tr { background-color: #1e3a5f; color: white; }
        th { padding: 6px 4px; text-align: left; border: 1px solid #1e3a5f; }
        td { padding: 5px 4px; border: 1px solid #ddd; vertical-align: middle; }
        .grup-header { background-color: #e8f0fe; font-weight: bold; color: #1e3a5f; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

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
        <div class="judul">Rekap Realisasi Anggaran Per Kode Rekening</div>
        <div class="sub-judul">
            Bulan: {{ $bulan ? \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') . ' ' . ($tahunAnggaranAktif?->tahun ?? date('Y')) : '-' }}
            &nbsp;|&nbsp;
            Tahun Anggaran: {{ $tahunAnggaranAktif?->tahun ?? '-' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%" class="text-center">No</th>
                <th style="width: 15%">Kode Rekening</th>
                <th style="width: 35%">Uraian Anggaran</th>
                <th style="width: 15%" class="text-right">Anggaran (Rencana)</th>
                <th style="width: 15%" class="text-right">Realisasi (GSP)</th>
                <th style="width: 15%" class="text-right">Sisa Anggaran</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotalRencana = 0;
                $grandTotalRealisasi = 0;
                $grandTotalSisa = 0;
                $i = 1;
            @endphp
            @forelse($grouped as $jenisBelanja => $items)
                <tr class="grup-header">
                    <td colspan="6">{{ $jenisBelanja }}</td>
                </tr>

                @php
                    $subRencana = $items->sum('rencana_bulan');
                    $subRealisasi = $items->sum('realisasi_bulan');
                    $subSisa = $items->sum('sisa_bulan');

                    $grandTotalRencana += $subRencana;
                    $grandTotalRealisasi += $subRealisasi;
                    $grandTotalSisa += $subSisa;
                @endphp

                @foreach($items as $item)
                    <tr>
                        <td class="text-center">{{ $i++ }}</td>
                        <td style="font-family: monospace">{{ $item->kodeRekening?->kode ?? '-' }}</td>
                        <td>{{ $item->uraian }}</td>
                        <td class="text-right">Rp {{ number_format($item->rencana_bulan, 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($item->realisasi_bulan, 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($item->sisa_bulan, 0, ',', '.') }}</td>
                    </tr>
                @endforeach

                <tr style="background-color: #f8fafc; font-weight: bold; font-size: 9px;">
                    <td colspan="3" class="text-right">SUBTOTAL {{ strtoupper($jenisBelanja) }}</td>
                    <td class="text-right">Rp {{ number_format($subRencana, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($subRealisasi, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($subSisa, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">Belum ada data anggaran untuk bulan {{ $bulan ? \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') . ' ' . ($tahunAnggaranAktif?->tahun ?? date('Y')) : '-' }}.</td>
                </tr>
            @endforelse

            @if($grouped->count() > 0)
                <tr style="background-color: #1e3a5f; color: white; font-weight: bold;">
                    <td colspan="3" class="text-right">TOTAL KESELURUHAN (BULAN INI)</td>
                    <td class="text-right">Rp {{ number_format($grandTotalRencana, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($grandTotalRealisasi, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($grandTotalSisa, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

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
        <button onclick="window.print()" style="background:#1e3a5f;color:white;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
            Cetak
        </button>
        <button onclick="window.close()" style="background:#6b7280;color:white;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
            Tutup
        </button>
    </div>
    @endunless
</body>
</html>
