<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Tentang Aplikasi</div>
    </x-slot>

    @if(session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Versi Aplikasi</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">v{{ $version }}</div>
        </div>
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Status Pembaruan</div>
            <div class="mt-2">
                @if($release === null)
                    <span class="badge badge-gray">Tidak dapat memeriksa</span>
                @elseif($updateAvailable)
                    <span class="badge badge-yellow">Versi baru tersedia</span>
                @else
                    <span class="badge badge-green">Sudah versi terbaru</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="text-sm text-slate-500 font-medium">Pembaruan Terbaru</div>
            <div class="text-lg font-bold text-slate-800 mt-1">
                {{ $release !== null && $release['tag_name'] !== '' ? $release['tag_name'] : '—' }}
            </div>
        </div>
    </div>

    @if($updateAvailable && $release !== null)
        <div class="alert-warning mb-6">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1">
                <strong>Pembaruan {{ $release['tag_name'] }} tersedia.</strong> Untuk antisipasi kegagalan, ikuti langkah berikut sebelum menginstal:
                <ol class="list-decimal ml-5 mt-2 text-sm space-y-1">
                    <li><strong>Backup data dulu</strong> — buka halaman <a href="{{ route('pengaturan.backup.index') }}" class="underline hover:text-amber-900">Backup &amp; Pemulihan</a>, klik <em>Backup Sekarang</em>, lalu unduh file <code class="text-xs">.zip</code>-nya.</li>
                    <li><strong>Unduh installer baru</strong> — <a href="{{ $release['html_url'] }}" target="_blank" rel="noopener" class="underline hover:text-amber-900">buka halaman rilis di GitHub</a>.</li>
                    <li><strong>Instal versi baru</strong> — data tersimpan aman (database terpisah dari aplikasi), tetapi tetap backup agar siap memulihkan bila terjadi masalah.</li>
                </ol>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-end mb-6">
        <a href="{{ route('tentang.check') }}" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5m11 11v-5h-5M4.58 16A8 8 0 1116 19.42M4.58 8A8 8 0 1116 4.58"/></svg>
            Periksa Pembaruan Sekarang
        </a>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Petunjuk Penggunaan Singkat</span>
        </div>
        <div class="card-body space-y-3 text-sm text-slate-600 leading-relaxed">
            <ol class="list-decimal ml-5 space-y-1">
                <li><strong>Pengaturan → Profil Sekolah</strong> — isi identitas sekolah (dipakai di kop laporan).</li>
                <li><strong>Master data</strong> — Tahun Anggaran, Sumber Dana, Jenis Belanja, Master Program, Kode Rekening.</li>
                <li><strong>Data RKAS</strong> — isi rencana kegiatan per item per bulan, atau impor dari file Excel.</li>
                <li><strong>Buku Kas Umum</strong> — catat setiap transaksi kas masuk/keluar.</li>
                <li><strong>Laporan</strong> — BKU, Rekap Rekening, Rekap Kuartal, Rekap SIPLAH (preview, PDF, export Excel).</li>
                <li><strong>Backup rutin</strong> — lakukan di Pengaturan → Backup &amp; Pemulihan, terutama sebelum memperbarui aplikasi.</li>
            </ol>
            <p>Untuk panduan lengkap (mode web/desktop, env, CLI, build), baca <code class="text-xs">README.md</code> pada repositori.</p>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Peringatan</span>
        </div>
        <div class="card-body space-y-3 text-sm text-slate-600 leading-relaxed">
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-xs text-amber-700 leading-relaxed">
                    Aplikasi ini bersifat sebagai <strong>alat bantu monitoring</strong> anggaran sekolah. Input dan pelaporan
                    resmi tetap dilakukan melalui <strong>ARKAS</strong> (Aplikasi Rencana Kegiatan dan Anggaran Sekolah).
                </p>
            </div>
            <ul class="list-disc ml-5 space-y-1">
                <li>Satu instalasi diperuntukkan untuk satu sekolah.</li>
                <li>Backup rutin wajib dilakukan; pemulihan otomatis tersedia pada penyiapan pertama.</li>
                <li>Jangan menghapus file database di folder data aplikasi tanpa backup.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Pengembang &amp; Sumber</span>
        </div>
        <div class="card-body space-y-2 text-sm text-slate-600 leading-relaxed">
            <p>Dikembangkan oleh
                <a href="https://irfandev97.my.id/" target="_blank" rel="noopener" class="text-blue-600 hover:underline font-semibold">IrfanDev97</a>.
            </p>
            <p>
                Sumber terbuka:
                <a href="https://github.com/Mukhamadirfan1997/SMARTRKAS" target="_blank" rel="noopener" class="text-blue-600 hover:underline">github.com/Mukhamadirfan1997/SMARTRKAS</a>
            </p>
            <p class="text-xs text-slate-400">&copy; {{ date('Y') }} SmartRKAS v{{ $version }}. Menggunakan Laravel &amp; Tauri.</p>
        </div>
    </div>
</x-app-layout>
