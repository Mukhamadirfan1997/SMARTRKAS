<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap SIPLAH - {{ $profil?->nama ?? 'Sekolah' }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; font-size:10px; color:#1e293b; background:#fff; }
        .header { text-align:center; margin-bottom:20px; border-bottom:3px solid #1e40af; padding-bottom:15px; }
        .header h1 { font-size:16px; color:#1e40af; margin-bottom:4px; }
        .header h2 { font-size:13px; color:#475569; font-weight:normal; }
        .header p { font-size:10px; color:#64748b; margin-top:4px; }
        table { width:100%; border-collapse:collapse; margin-top:15px; font-size:10px; }
        th, td { border:1px solid #cbd5e1; padding:6px 8px; }
        th { background:#1e40af; color:#fff; font-weight:600; text-align:center; }
        td { vertical-align:middle; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }
        .text-green { color:#15803d; }
        .text-orange { color:#c2410c; }
        .text-blue { color:#1e40af; }
        .text-slate { color:#64748b; }
        .font-bold { font-weight:700; }
        .summary { margin-top:20px; padding:12px; background:#f0f4ff; border-radius:6px; border:1px solid #bfdbfe; }
        .summary p { margin-bottom:4px; font-size:10px; }
        .tanda-tangan { margin-top:50px; }
        .tanda-tangan table { border:none; }
        .tanda-tangan td { border:none !important; width:50%; text-align:center; vertical-align:top; padding:10px 20px; }
        .tanda-tangan .nama { font-size:12px; font-weight:700; color:#1e293b; margin-top:60px; border-top:1px solid #1e293b; padding-top:5px; display:inline-block; min-width:150px; }
        .tanda-tangan .nip { font-size:9px; color:#64748b; margin-top:2px; }
        .footer-note { margin-top:20px; text-align:center; font-size:8px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>REKAP PENGADAAN SIPLAH</h1>
        <h2>{{ $profil?->nama ?? '-' }} &mdash; NPSN: {{ $profil?->npsn ?? '-' }}</h2>
        <p>Periode: {{ $periodeLabel }} {{ $tahunAnggaranAktif?->tahun ?? date('Y') }} &nbsp;|&nbsp; Tanggal Cetak: {{ $tanggalCetak ?? date('d F Y') }}</p>
    </div>

    <div class="summary">
        <p><strong>Total Pengeluaran:</strong> <span class="font-bold">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</span></p>
        <p><strong>SIPLAH:</strong> <span class="text-green font-bold">Rp {{ number_format($totalSiplah, 0, ',', '.') }}</span> ({{ $persenSiplah }}%)</p>
        <p><strong>Non-SIPLAH:</strong> <span class="text-orange font-bold">Rp {{ number_format($totalNonSiplah, 0, ',', '.') }}</span> ({{ $persenNonSiplah }}%)</p>
        @if($totalBelumDiisi > 0)
        <p class="text-slate"><strong>Belum Ditandai:</strong> Rp {{ number_format($totalBelumDiisi, 0, ',', '.') }}</p>
        @endif
    </div>

    @if($breakdown->count() > 0)
    <table>
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:30%">Jenis Belanja</th>
                <th style="width:18%" class="text-right">Total Pengeluaran</th>
                <th style="width:18%" class="text-right">SIPLAH</th>
                <th style="width:18%" class="text-right">Non-SIPLAH</th>
                <th style="width:11%" class="text-center">% SIPLAH</th>
            </tr>
        </thead>
        <tbody>
            @foreach($breakdown as $item)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td>{{ $item->jenis_belanja }}</td>
                <td class="text-right font-bold">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                <td class="text-right text-green">Rp {{ number_format($item->siplah, 0, ',', '.') }}</td>
                <td class="text-right text-orange">Rp {{ number_format($item->non_siplah, 0, ',', '.') }}</td>
                <td class="text-center">{{ $item->persen_siplah }}%</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right font-bold">TOTAL</td>
                <td class="text-right font-bold">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                <td class="text-right text-green font-bold">Rp {{ number_format($totalSiplah, 0, ',', '.') }}</td>
                <td class="text-right text-orange font-bold">Rp {{ number_format($totalNonSiplah, 0, ',', '.') }}</td>
                <td class="text-center font-bold">{{ $persenSiplah }}%</td>
            </tr>
        </tfoot>
    </table>
    @else
    <div style="text-align:center;padding:40px;color:#94a3b8;">
        Belum ada data pengeluaran pada periode ini.
    </div>
    @endif

    <table class="tanda-tangan" style="width:100%;border-collapse:collapse;border:none !important;margin-top:30px;">
        <tr>
            <td style="border:none !important;width:45%;text-align:center;vertical-align:top;padding:0 10px;">
                <div style="font-size:10px;height:75px;line-height:1.4;">Mengetahui,<br>Kepala Sekolah</div>
                <div class="nama">{{ $profil?->nama_kepsek ?? '-' }}</div>
                <div class="nip">NIP. {{ $profil?->nip_kepsek ?? '-' }}</div>
            </td>
            <td style="border:none !important;width:10%;"></td>
            <td style="border:none !important;width:45%;text-align:center;vertical-align:top;padding:0 10px;">
                <div style="font-size:10px;height:75px;line-height:1.4;">{{ $profil?->kecamatan ?? '' }}, {{ $tanggalCetak }}<br><strong>Bendahara</strong></div>
                <div class="nama">{{ $profil?->nama_bendahara ?? '-' }}</div>
                <div class="nip">NIP. {{ $profil?->nip_bendahara ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini dihasilkan secara otomatis oleh Sistem Informasi Realisasi Anggaran RKAS.
    </div>

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
