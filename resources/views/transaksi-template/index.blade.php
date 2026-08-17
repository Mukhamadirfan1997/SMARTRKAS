<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Template Transaksi</div>
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
                <span class="card-title">Daftar Template Transaksi</span>
                <p class="text-xs text-slate-500 mt-0.5">Template disimpan dari baris transaksi BKU untuk dipakai ulang</p>
            </div>
            <a href="{{ route('transaksi-bku.index') }}" class="btn btn-secondary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali ke BKU
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center">No</th>
                        <th>Nama Template</th>
                        <th>Kegiatan</th>
                        <th>Kode Rekening</th>
                        <th>Uraian Item</th>
                        <th>Toko/Penerima</th>
                        <th>Metode</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $no => $tpl)
                        <tr>
                            <td class="text-center text-slate-500">{{ $no + 1 }}</td>
                            <td class="font-medium text-slate-800">{{ $tpl->nama_template }}</td>
                            <td class="text-xs text-slate-600">
                                {{ $tpl->kegiatan?->kode }} - {{ $tpl->kegiatan?->nama }}
                            </td>
                            <td class="text-xs font-mono text-slate-600">
                                {{ $tpl->kodeRekening?->kode }} - {{ $tpl->kodeRekening?->nama }}
                            </td>
                            <td class="text-xs text-slate-600 max-w-[200px]">
                                <div class="truncate" title="{{ $tpl->uraian_item_snapshot }}">{{ $tpl->uraian_item_snapshot }}</div>
                            </td>
                            <td class="text-xs text-slate-600">{{ $tpl->toko_penerima ?? '-' }}</td>
                            <td class="text-center">
                                @if($tpl->metode_pengadaan === 'siplah')
                                    <span class="badge badge-blue">SIPLAH</span>
                                @elseif($tpl->metode_pengadaan === 'non_siplah')
                                    <span class="badge badge-gray">Non-SIPLAH</span>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="text-xs text-slate-500">{{ $tpl->createdByUser?->name ?? '-' }}</td>
                            <td class="text-center whitespace-nowrap">
                                <a href="{{ route('transaksi-bku.create') }}?template_id={{ $tpl->id }}" class="btn btn-success btn-sm" title="Pakai template ini">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Pakai
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" title="Hapus template" onclick="hapusTemplate('{{ $tpl->id }}', '{{ addslashes($tpl->nama_template) }}')">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <svg aria-hidden="true" class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                                <p class="font-medium">Belum ada template</p>
                                <p class="text-xs mt-1">Buka halaman BKU, lalu klik ikon "Simpan Template" pada baris transaksi pengeluaran</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form id="form-hapus-template" method="POST" action="{{ route('transaksi-template.destroy', '__ID__') }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <script>
        function hapusTemplate(id, nama) {
            if (!confirm('Hapus template "' + nama + '"?')) return;
            const form = document.getElementById('form-hapus-template');
            form.action = "{{ route('transaksi-template.destroy', '__ID__') }}".replace('__ID__', id);
            form.submit();
        }
    </script>
</x-app-layout>
