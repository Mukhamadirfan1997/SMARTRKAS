<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Import RKAS</div>
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

    @if(!$tahunAnggaranAktif)
        <div class="alert-warning mb-6">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Tahun anggaran belum diaktifkan. Import tidak dapat dilakukan. Silakan aktifkan di menu <a href="{{ route('tahun-anggaran.index') }}" class="underline font-semibold hover:text-amber-900">Tahun Anggaran</a> terlebih dahulu.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @if($tahunAnggaranAktif)
        <div class="card">
            <div class="card-header">
                    <span class="card-title">Upload File Excel RKAS</span>
                <div class="flex gap-2">
                    <a href="{{ route('import-rkas.download-template') }}" class="btn btn-outline btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Template
                    </a>
                    <a href="{{ route('rkas.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('import-rkas.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <p class="text-xs text-slate-500 mb-4">Anda bisa mengunggah hingga 12 bulan sekaligus. Kosongkan bulan yang tidak ingin diisi.</p>

                    <div class="space-y-3 max-h-96 overflow-y-auto pr-2 mb-6">
                        @for($i = 1; $i <= 12; $i++)
                        <div class="flex items-center justify-between border border-slate-200 rounded-xl p-3 bg-slate-50">
                            <span class="text-sm font-semibold text-slate-700 w-24">
                                {{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}
                            </span>
                            <input type="file" name="files[{{ $i }}]" accept=".xlsx,.xls" class="flex-1 ml-4 text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        @endfor
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Sumber Dana</label>
                        <select name="sumber_dana_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Pilih Sumber Dana --</option>
                            @foreach(\App\Models\SumberDana::orderBy('kode')->get() as $sd)
                                <option value="{{ $sd->id }}">{{ $sd->kode }} - {{ $sd->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Mulai Proses Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <span class="card-title">Riwayat Import Terakhir</span>
            </div>
            <div id="import-processing-bar" class="hidden items-center gap-3 px-4 py-3 bg-amber-50 border-b border-amber-200 text-sm text-amber-700">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Import sedang diproses...</span>
            </div>
            <div class="card-body flex flex-col" id="riwayat-container">
                @if($logs->count() > 0)
                    <div class="space-y-4 flex-1 overflow-y-auto max-h-96">
                        @foreach($logs as $log)
                            <div class="p-4 rounded-xl border {{ $log->status == 'error' ? 'bg-red-50 border-red-200' : ($log->status == 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200') }}">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-semibold text-sm {{ $log->status == 'error' ? 'text-red-700' : ($log->status == 'success' ? 'text-emerald-700' : 'text-amber-700') }}">
                                        {{ \Carbon\Carbon::create()->month($log->bulan ?? 1)->translatedFormat('F') }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $log->uploader?->name ?? '-' }} &middot; {{ $log->created_at->translatedFormat('d M Y H:i') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="badge {{ $log->status == 'failed' ? 'badge-red' : ($log->status == 'success' ? 'badge-green' : 'badge-yellow') }}">
                                        {{ strtoupper($log->status) }}
                                    </span>
                                    @if(($log->baris_berhasil ?? 0) > 0 || ($log->total_baris ?? 0) > 0)
                                        <span class="text-xs text-slate-600 bg-white px-3 py-1 rounded-lg border border-slate-200">
                                            {{ $log->baris_berhasil }}/{{ $log->total_baris }} Baris
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($log->error_detail))
                                    <div class="mt-3 text-xs text-red-600 bg-white p-3 rounded-lg border border-red-100 max-h-24 overflow-y-auto">
                                        @foreach($log->error_detail as $err)
                                            <div>{{ $err }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center flex-1 text-slate-400 py-12">
                        <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span class="text-sm">Belum ada riwayat import.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        var pollTimeout = null;

        function renderLog(log) {
            var statusClass = log.status === 'failed' ? 'bg-red-50 border-red-200' : (log.status === 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200');
            var badgeClass = log.status === 'failed' ? 'badge-red' : (log.status === 'success' ? 'badge-green' : 'badge-yellow');
            var month = new Date(0, (log.bulan || 1) - 1).toLocaleString('id', { month: 'long' });
            var date = log.created_at ? new Date(log.created_at).toLocaleString('id', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
            var errors = log.error_detail ? log.error_detail.map(function(e) { return '<div>' + e + '</div>'; }).join('') : '';
            var progressBar = '';
            if (log.status === 'processing' && log.total_baris > 0) {
                var pct = Math.round((log.baris_berhasil || 0) / log.total_baris * 100);
                progressBar = '<div class="mt-2 w-full bg-slate-200 rounded-full h-1.5"><div class="bg-amber-500 h-1.5 rounded-full transition-all" style="width:' + pct + '%"></div></div>';
            }
            return '<div class="p-4 rounded-xl border ' + statusClass + '">' +
                '<div class="flex justify-between items-center mb-2">' +
                    '<span class="font-semibold text-sm ' + (log.status === 'failed' ? 'text-red-700' : (log.status === 'success' ? 'text-emerald-700' : 'text-amber-700')) + '">' + month + '</span>' +
                    '<span class="text-xs font-mono text-slate-500">' + date + '</span>' +
                '</div>' +
                '<div class="flex items-center gap-2">' +
                    '<span class="badge ' + badgeClass + '">' + log.status.toUpperCase() + '</span>' +
                    ((log.baris_berhasil || 0) > 0 || (log.total_baris || 0) > 0 ? '<span class="text-xs text-slate-600 bg-white px-3 py-1 rounded-lg border border-slate-200">' + (log.baris_berhasil || 0) + '/' + (log.total_baris || 0) + ' Baris</span>' : '') +
                '</div>' +
                progressBar +
                (errors ? '<div class="mt-3 text-xs text-red-600 bg-white p-3 rounded-lg border border-red-100 max-h-24 overflow-y-auto">' + errors + '</div>' : '') +
            '</div>';
        }

        function polling() {
            fetch('{{ route('import-rkas.status') }}')
                .then(function(r) { return r.json(); })
                .then(function(logs) {
                    var hasProcessing = false;
                    var processingBar = document.getElementById('import-processing-bar');
                    var container = document.getElementById('riwayat-container');
                    if (container && logs.length > 0) {
                        container.innerHTML = logs.map(function(log) {
                            if (log.status === 'processing') hasProcessing = true;
                            return renderLog(log);
                        }).join('');
                    }
                    if (processingBar) {
                        processingBar.style.display = hasProcessing ? 'flex' : 'none';
                    }
                    if (hasProcessing) {
                        pollTimeout = setTimeout(polling, 5000);
                    }
                })
                .catch(function() {
                    pollTimeout = setTimeout(polling, 5000);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var processingBar = document.getElementById('import-processing-bar');
            var container = document.getElementById('riwayat-container');
            if (container && container.querySelector('.badge-yellow')) {
                if (processingBar) processingBar.style.display = 'flex';
                pollTimeout = setTimeout(polling, 5000);
            }
        });
    </script>
</x-app-layout>
