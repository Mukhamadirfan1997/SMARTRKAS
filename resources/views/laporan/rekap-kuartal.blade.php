<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Tribulan {{ $qLabel }}</title>
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
        .font-mono { font-family: 'Courier New', monospace; }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            @page { margin: 1cm; size: A4 landscape; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="nama-sekolah">{{ $profil?->nama ?? 'Nama Sekolah' }}</div>
        <div class="alamat">{{ $profil?->alamat ?? '' }}</div>
        <div class="judul">Rekap Realisasi Anggaran Per Kode Rekening</div>
        <div class="sub-judul">
            {{ $periodeLabel }} &nbsp;|&nbsp;
            Tahun Anggaran: {{ $tahunAnggaranAktif?->tahun ?? '-' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%" class="text-center">No</th>
                <th style="width: 14%">Kode Rekening</th>
                <th style="width: 22%">Uraian Anggaran</th>
                @foreach($bulanNames as $name)
                    <th style="width: 14%" class="text-right">{{ $name }}</th>
                @endforeach
                <th style="width: 14%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotalPerBulan = array_fill_keys($bulanMonths, 0);
                $grandTotalAll = 0;
                $i = 1;
            @endphp

            @forelse($grouped as $jenisBelanja => $items)
                <tr class="grup-header">
                    <td colspan="{{ 3 + count($bulanNames) + 1 }}">{{ $jenisBelanja }}</td>
                </tr>

                @php
                    $subTotalPerBulan = array_fill_keys($bulanMonths, 0);
                    $subTotalAll = 0;
                @endphp

                @foreach($items->sortBy('kodeRekening.kode') as $item)
                    <tr>
                        <td class="text-center">{{ $i++ }}</td>
                        <td class="font-mono">{{ $item->kodeRekening?->kode ?? '-' }}</td>
                        <td>{{ $item->uraian }}</td>
                        @foreach($bulanMonths as $bulan)
                            @php $r = $item->realisasi_per_bulan[$bulan] ?? 0; @endphp
                            <td class="text-right">{{ $r > 0 ? 'Rp ' . number_format($r, 0, ',', '.') : '&mdash;' }}</td>
                        @endforeach
                        <td class="text-right"><strong>Rp {{ number_format($item->total_realisasi, 0, ',', '.') }}</strong></td>
                    </tr>
                @endforeach

                @php
                    foreach ($bulanMonths as $bulan) {
                        $subTotalPerBulan[$bulan] += $items->sum(fn($it) => $it->realisasi_per_bulan[$bulan] ?? 0);
                    }
                    $subTotalAll += $items->sum('total_realisasi');
                @endphp

                <tr style="background-color: #f1f5f9; font-weight: bold; font-size: 9px;">
                    <td colspan="3" class="text-right">SUBTOTAL {{ strtoupper($jenisBelanja) }}</td>
                    @foreach($bulanMonths as $bulan)
                        <td class="text-right">Rp {{ number_format($subTotalPerBulan[$bulan], 0, ',', '.') }}</td>
                    @endforeach
                    <td class="text-right">Rp {{ number_format($subTotalAll, 0, ',', '.') }}</td>
                </tr>

                @php
                    foreach ($bulanMonths as $bulan) {
                        $grandTotalPerBulan[$bulan] += $subTotalPerBulan[$bulan];
                    }
                    $grandTotalAll += $subTotalAll;
                @endphp
            @empty
                <tr>
                    <td colspan="{{ 3 + count($bulanNames) + 1 }}" class="text-center" style="padding: 20px;">Belum ada data anggaran untuk Tribulan {{ $qLabel ?? '' }} ({{ $periodeLabel ?? '' }}) {{ $tahunAnggaranAktif?->tahun ?? '' }}.</td>
                </tr>
            @endforelse

            @if($grouped->count() > 0)
                <tr style="background-color: #1e3a5f; color: white; font-weight: bold;">
                    <td colspan="3" class="text-right">TOTAL KESELURUHAN</td>
                    @foreach($bulanMonths as $bulan)
                        <td class="text-right">Rp {{ number_format($grandTotalPerBulan[$bulan], 0, ',', '.') }}</td>
                    @endforeach
                    <td class="text-right">Rp {{ number_format($grandTotalAll, 0, ',', '.') }}</td>
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
