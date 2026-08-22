<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Pemetaan Kode Rekening &rarr; Kategori Juknis</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="alert-info flex items-start gap-2 mb-6">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Centang kategori Juknis yang relevan untuk tiap kode rekening (satu rekening boleh beberapa kategori). Menyimpan hanya memperbarui kode rekening yang tampil di tabel; mengosongkan semua centang pada satu baris berarti melepas pemetaan baris tersebut.</span>
    </div>

    <div class="card mb-6">
        <div class="card-body py-4">
            <form method="GET" action="{{ route('pengaturan.kategori-juknis.pemetaan') }}" class="flex flex-wrap items-center gap-3">
                <input type="text" name="q" value="{{ request('q') }}" class="form-input max-w-xs flex-1" placeholder="Cari kode / nama rekening...">
                <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
                @if(request('q'))
                    <a href="{{ route('pengaturan.kategori-juknis.pemetaan') }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
                <a href="{{ route('pengaturan.kategori-juknis.index') }}" class="btn btn-secondary btn-sm ml-auto">
                    Kembali ke Daftar Kategori
                </a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Tabel Pemetaan</span>
        </div>

        <form method="POST" action="{{ route('pengaturan.kategori-juknis.simpan-pemetaan') }}">
            @csrf

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Rekening</th>
                            <th>Jenis Belanja</th>
                            @foreach($kategoriJuknis as $kategori)
                                <th class="text-center whitespace-nowrap" title="{{ ($kategori->arah === 'minimal' ? 'Minimal' : 'Maksimal') }} {{ number_format((float) $kategori->batas_persen, 2, ',', '.') }}%">
                                    {{ $kategori->nama }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekenings as $rekening)
                            @php
                                $mapped = $rekening->kategoriJuknis->pluck('id');
                            @endphp
                            <tr>
                                <td class="font-mono text-xs whitespace-nowrap">{{ $rekening->kode }}</td>
                                <td class="font-medium text-slate-800">{{ $rekening->nama }}</td>
                                <td>{{ $rekening->jenisBelanja?->nama ?? '-' }}</td>
                                @foreach($kategoriJuknis as $kategori)
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="map[{{ $rekening->id }}][{{ $kategori->id }}]"
                                               value="{{ $kategori->id }}"
                                               class="w-4 h-4 accent-indigo-600"
                                               @checked($mapped->contains($kategori->id))>
                                    </td>
                                @endforeach
                            </tr>
                            <input type="hidden" name="rows[]" value="{{ $rekening->id }}">
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $kategoriJuknis->count() }}" class="text-center text-slate-500 py-8">Kode rekening tidak ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 flex items-center justify-between gap-3">
                <button type="submit" class="btn-primary" @disabled($rekenings->isEmpty())>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Pemetaan
                </button>
                <span class="text-sm text-slate-500">{{ $rekenings->total() }} kode rekening{{ request('q') ? ' (hasil pencarian)' : '' }}</span>
            </div>
        </form>

        <div class="p-4 border-t border-slate-200">
            {{ $rekenings->links() }}
        </div>
    </div>
</x-app-layout>
