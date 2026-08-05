<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="page-title">Backup &amp; Pemulihan</div>
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

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Jumlah Backup</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ $backups->count() }}</div>
        </div>
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Total Ukuran</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($totalSize / 1048576, 2) }} MB</div>
        </div>
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Backup Terakhir</div>
            <div class="text-lg font-bold text-slate-800 mt-1">
                {{ $latest ? \Carbon\Carbon::createFromTimestamp($latest['mtime'])->format('d/m/Y H:i') : 'Belum ada' }}
            </div>
        </div>
    </div>

    <div class="alert-info mb-6">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            Backup otomatis dijalankan setiap hari pukul <strong>01:30</strong> dan dibersihkan pukul <strong>01:00</strong>
            (saat aplikasi desktop sedang berjalan / server scheduler aktif). Pemulihan dilakukan manual: unduh file backup lalu
            pulihkan database dari file tersebut.
            <span class="block mt-1">Jika ada <strong>pembaruan aplikasi</strong> (lihat menu Tentang Aplikasi), pastikan backup terakhir masih baru sebelum menginstal.</span>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Buat Backup Sekarang</span>
        </div>
        <div class="card-body flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-slate-600">Buat arsip lengkap aplikasi (database + file) secara manual tanpa menunggu jadwal otomatis.</p>
            <form method="POST" action="{{ route('pengaturan.backup.now') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Backup Sekarang
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Backup</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Tanggal</th>
                        <th>Ukuran</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $backup)
                        <tr>
                            <td class="font-medium text-slate-800">{{ $backup['name'] }}</td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($backup['mtime'])->format('d/m/Y H:i') }}</td>
                            <td>{{ number_format($backup['size'] / 1048576, 2) }} MB</td>
                            <td class="text-right">
                                <a href="{{ route('pengaturan.backup.download', ['file' => $backup['name']]) }}" class="btn btn-secondary btn-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Unduh
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-slate-500 py-8">Belum ada backup. Klik "Backup Sekarang" untuk membuat backup pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
