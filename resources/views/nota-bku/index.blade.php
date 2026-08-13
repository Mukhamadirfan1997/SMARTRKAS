<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Riwayat Nota Belanja</div>
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

    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Daftar Nota Belanja</span>
                <p class="text-xs text-slate-500 mt-0.5">1 Nota = 1 Kegiatan, boleh banyak item belanja</p>
            </div>
            <a href="{{ route('nota-bku.create') }}" class="btn-primary">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Nota
            </a>
        </div>

        @if($notas->isEmpty())
            <div class="text-center py-12 text-slate-400">
                <svg aria-hidden="true" class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div class="text-sm font-medium">Belum ada nota belanja</div>
                <p class="text-xs mt-1">Klik "Tambah Nota" untuk membuat nota multi-item (pilih kegiatan, lalu pilih item belanjanya).</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Nota</th>
                            <th>Tanggal</th>
                            <th>Kegiatan</th>
                            <th>Sumber Dana</th>
                            <th class="text-right">Jumlah Item</th>
                            <th class="text-right">Total (Rp)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notas as $nota)
                            <tr>
                                <td class="font-mono text-xs">{{ $nota->no_nota }}</td>
                                <td>{{ \Carbon\Carbon::parse($nota->tanggal)->translatedFormat('d M Y') }}</td>
                                <td>{{ optional($nota->kegiatan)->kode }} {{ optional($nota->kegiatan)->nama }}</td>
                                <td>{{ optional($nota->sumberDana)->kode }} - {{ optional($nota->sumberDana)->nama }}</td>
                                <td class="text-right">{{ $nota->items_count }}</td>
                                <td class="text-right font-medium">Rp {{ number_format((float) $nota->total_item, 0, ',', '.') }}</td>
                                <td class="text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('nota-bku.show', $nota) }}" class="btn btn-info btn-sm" title="Detail Nota">Detail</a>
                                        <a href="{{ route('nota-bku.cetak', $nota) }}" target="_blank" class="btn btn-success btn-sm" title="Cetak PDF">PDF</a>
                                        <form method="POST" action="{{ route('nota-bku.destroy', $nota) }}" onsubmit="return confirm('Hapus nota {{ $nota->no_nota }} beserta SEMUA transaksi hasilnya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">
                {{ $notas->links() }}
            </div>
        @endif
    </div>
</x-app-layout>