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
        <div class="stat-card indigo">
            <div class="stat-icon bg-indigo-50">
                <svg aria-hidden="true" class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            </div>
            <div class="stat-label">Jumlah Backup</div>
            <div class="stat-value text-indigo-700">{{ $backups->count() }}</div>
        </div>

        <div class="stat-card blue">
            <div class="stat-icon bg-blue-50">
                <svg aria-hidden="true" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </div>
            <div class="stat-label">Total Ukuran</div>
            <div class="stat-value text-blue-700">{{ number_format($totalSize / 1048576, 2) }} <span class="text-sm text-slate-400 font-normal">MB</span></div>
        </div>

        <div class="stat-card {{ $latest ? 'green' : 'gray' }}">
            <div class="stat-icon bg-emerald-50">
                <svg aria-hidden="true" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="stat-label">Backup Terakhir</div>
            <div class="mt-2">
                @if($latest)
                    <span class="badge badge-green">{{ \Carbon\Carbon::createFromTimestamp($latest['mtime'], config('app.timezone'))->format('d M Y H:i') }}</span>
                @else
                    <span class="badge badge-gray">Belum ada</span>
                @endif
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
            <form method="POST" action="{{ route('pengaturan.backup.now') }}" id="form-backup-now">
                @csrf
                <button type="submit" class="btn btn-primary" id="btn-backup-now">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Backup Sekarang
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-2">
            <span class="card-title">Daftar Backup</span>
            @if($backups->isNotEmpty())
                <span class="badge badge-blue text-xs">{{ $backups->count() }} file</span>
            @endif
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
                            <td class="font-medium text-slate-800 flex items-center gap-2">
                                <svg aria-hidden="true" class="w-4 h-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                {{ $backup['name'] }}
                            </td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($backup['mtime'], config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge badge-gray text-xs">{{ number_format($backup['size'] / 1048576, 2) }} MB</span>
                            </td>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-backup-now');
        var btn = document.getElementById('btn-backup-now');

        if (! form || ! btn) {
            return;
        }

        form.addEventListener('submit', function () {
            if (btn.disabled) {
                return;
            }

            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-wait');
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
                '</svg> Memproses...';
        });
    });
</script>
