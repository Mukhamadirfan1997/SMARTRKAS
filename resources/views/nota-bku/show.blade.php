<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Detail Nota</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @php
        $prog     = $notaBku->kegiatan;
        $rekening = $notaBku->kodeRekening;

        $namaProgram    = '-';
        $namaSubProgram = '-';
        $kodeRekening   = '-';
        $namaRekening   = '-';

        if ($prog) {
            $kodeKegiatan = rtrim($prog->kode ?? '', '.');
            $segments = explode('.', $kodeKegiatan);
            $kodeSubProgram = isset($segments[0]) && isset($segments[1]) ? $segments[0] . '.' . $segments[1] . '.' : '-';
            $kodeProgram    = isset($segments[0]) ? $segments[0] . '.' : '-';
            $namaSubProgram = ($kodeSubProgram !== '-' ? $kodeSubProgram . ' ' : '') . ($prog->sub_program ?? '-');
            $namaProgram    = ($kodeProgram !== '-' ? $kodeProgram . ' ' : '') . ($prog->program ?? '-');
        }

        if ($rekening) {
            $kodeRekening = $rekening->kode ?? '-';
            $namaRekening = $rekening->nama ?? '-';
        }
    @endphp

    {{-- Aksi --}}
    <div class="flex items-center justify-between gap-2 mb-6 flex-wrap">
        <a href="{{ route('nota-bku.index') }}" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Kembali
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('nota-bku.cetak', $notaBku) }}" target="_blank" class="btn btn-success">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak PDF
            </a>
            <form method="POST" action="{{ route('nota-bku.destroy', $notaBku) }}" onsubmit="return confirm('Hapus nota {{ $notaBku->no_nota }} beserta SEMUA transaksi hasilnya?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus
                </button>
            </form>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="stat-card green">
            <div class="stat-icon bg-emerald-50">
                <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            </div>
            <div>
                <div class="stat-label">Total Belanja</div>
                <div class="stat-value">Rp {{ number_format($total, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon bg-blue-50">
                <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div>
                <div class="stat-label">Jumlah Item</div>
                <div class="stat-value">{{ $notaBku->items->count() }}</div>
            </div>
        </div>
        <div class="stat-card indigo">
            <div class="stat-icon bg-indigo-50">
                <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <div>
                <div class="stat-label">Transaksi BKU</div>
                <div class="stat-value">{{ $notaBku->transaksiBkus->count() }}</div>
            </div>
        </div>
    </div>

    {{-- Informasi Nota --}}
    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Informasi Nota</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <div class="text-xs text-slate-400">No. Nota</div>
                    <div class="font-mono font-semibold text-slate-800">{{ $notaBku->no_nota }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Tanggal</div>
                    <div class="font-medium text-slate-800">{{ \Carbon\Carbon::parse($notaBku->tanggal)->translatedFormat('d F Y') }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Bulan</div>
                    <div class="font-medium text-slate-800">Bulan {{ $notaBku->bulan }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Kegiatan</div>
                    <div class="font-medium text-slate-800">{{ $prog->kode ?? '-' }} {{ $prog->nama ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Program</div>
                    <div class="font-medium text-slate-800">{{ $namaProgram }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Sub Program</div>
                    <div class="font-medium text-slate-800">{{ $namaSubProgram }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Kode Rekening</div>
                    <div class="font-medium text-slate-800">
                        @if($kodeRekening !== '-' && $kodeRekening !== '')
                            {{ $kodeRekening }} - {{ $namaRekening }}
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Sumber Dana</div>
                    <div class="font-medium text-slate-800">{{ $notaBku->sumberDana->kode ?? '-' }} - {{ $notaBku->sumberDana->nama ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Toko / Penerima</div>
                    <div class="font-medium text-slate-800">{{ $notaBku->toko_penerima ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Metode Pengadaan</div>
                    <div class="font-medium text-slate-800">
                        @if($notaBku->metode_pengadaan === 'siplah')
                            <span class="badge badge-blue">SIPLAH</span>
                        @elseif($notaBku->metode_pengadaan === 'non_siplah')
                            <span class="badge badge-gray">Non-SIPLAH</span>
                        @else
                            -
                        @endif
                    </div>
                </div>
                @if($notaBku->metode_pengadaan === 'siplah')
                    <div>
                        <div class="text-xs text-slate-400">No. Invoice SIPLah</div>
                        <div class="font-mono font-medium text-slate-800">{{ $notaBku->no_invoice_siplah ?? '-' }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs text-slate-400">Dibuat Oleh</div>
                    <div class="font-medium text-slate-800">{{ $notaBku->createdBy->name ?? '-' }}</div>
                </div>
                @if(!empty($notaBku->uraian))
                    <div class="md:col-span-2">
                        <div class="text-xs text-slate-400">Uraian / Keterangan</div>
                        <div class="font-medium text-slate-800">{{ $notaBku->uraian }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rincian Item Belanja --}}
    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Rincian Item Belanja</span>
            <span class="text-xs text-slate-500">{{ $notaBku->items->count() }} item</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10 text-center">No</th>
                        <th>Uraian Item</th>
                        <th class="text-right">Jumlah</th>
                        <th>Satuan</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notaBku->items as $item)
                        <tr>
                            <td class="text-center">{{ $item->urutan }}</td>
                            <td>{{ $item->rkasItem->no_urut ?? '' }}. {{ $item->rkasItem->uraian ?? $item->satuan }}</td>
                            <td class="text-right">{{ number_format((float) $item->jumlah, 0, ',', '.') }}</td>
                            <td>{{ $item->satuan ?? '-' }}</td>
                            <td class="text-right">Rp {{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-right font-medium">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right font-bold">Total</td>
                        <td class="text-right font-bold">Rp {{ number_format($total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Transaksi Terkait --}}
    @if($notaBku->transaksiBkus->isNotEmpty())
        <div class="card mb-6">
            <div class="card-header">
                <span class="card-title">Transaksi BKU Terkait</span>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Bukti</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notaBku->transaksiBkus as $transaksi)
                            <tr>
                                <td class="font-mono text-xs">{{ $transaksi->no_bukti }}</td>
                                <td class="text-right">Rp {{ number_format((float) $transaksi->jumlah, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
