<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="page-title">Master Kode Rekening</div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Master Kode Rekening</span>
            <div class="flex items-center gap-2">
                <form method="GET" class="flex gap-2">
                    <input type="text" name="search" class="form-input text-sm" placeholder="Cari nama/kode..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('master-kode-rekening.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                    @endif
                </form>
                <a href="{{ route('master-kode-rekening.download-template') }}" class="btn btn-info btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0L8 8m4 4V3"/></svg>
                    Template
                </a>
                <form action="{{ route('master-kode-rekening.import') }}" method="POST" enctype="multipart/form-data" class="flex gap-2">
                    @csrf
                    <input type="file" name="file" class="form-input text-sm max-w-xs" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" class="btn btn-success btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Import
                    </button>
                </form>
                <button type="button" class="btn btn-danger btn-sm" onclick="hapusSemuaMasterKodeRekening()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Semua
                </button>
                <a href="{{ route('master-kode-rekening.create') }}" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Master Kode Rekening
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jenis Belanja</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($masterKodeRekenings as $kodeRekening)
                        <tr>
                            <td class="font-mono font-medium text-slate-700">{{ $kodeRekening->kode }}</td>
                            <td class="font-medium text-slate-800">{{ $kodeRekening->nama }}</td>
                            <td class="text-slate-600">{{ $kodeRekening->jenisBelanja?->nama ?? '-' }}</td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('master-kode-rekening.edit', $kodeRekening) }}" class="btn btn-secondary btn-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Edit
                                    </a>
                                    <form action="{{ route('master-kode-rekening.destroy', $kodeRekening) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200">
            {{ $masterKodeRekenings->links() }}
        </div>
    </div>

    <form id="form-hapus-semua-master-kode-rekening" method="POST" action="{{ route('master-kode-rekening.hapus-semua') }}" class="hidden">
        @csrf
        <input type="hidden" name="search" value="{{ request('search') }}">
    </form>

    <script>
        function hapusSemuaMasterKodeRekening() {
            if (!confirm('Hapus SEMUA Master Kode Rekening pada filter aktif?\nData RKAS yang memakai kode ini akan kehilangan kode rekening (set null).')) return;
            document.getElementById('form-hapus-semua-master-kode-rekening').submit();
        }
    </script>
</x-app-layout>
