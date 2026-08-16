<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Import Revisi Anggaran (Pergeseran / PAK)</div>
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

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Petunjuk</span>
        </div>
        <div class="card-body text-sm text-slate-600 space-y-2">
            <p>Upload hasil pergeseran / Perubahan Anggaran (PAK) yang dikerjakan di <strong>ARKAS</strong>. Format file sama dengan template import RKAS (No Urut, Kode Rekening, Kode Program, Uraian, Volume, Satuan, Tarif, Jumlah) — satu file per bulan, boleh hanya bulan yang berubah.</p>
            <ul class="list-disc list-inside space-y-1">
                <li>Item yang <strong>tidak ada</strong> di file revisi dibiarkan apa adanya (tidak dihapus).</li>
                <li>Item yang sudah ber-realisasi tidak boleh menjadi sumber (turun).</li>
                <li>Revisi bersifat <strong>all-or-nothing</strong>: bila ada satu saja item yang melanggar, seluruh revisi ditolak.</li>
                <li>Pergeseran harus net-zero per (sumber dana + jenis belanja); PAK net-zero per sumber dana.</li>
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @if($tahunAnggaranAktif)
        <div class="card">
            <div class="card-header">
                <span class="card-title">Upload File Revisi</span>
                <a href="{{ route('import-rkas.index') }}" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali ke Import RKAS
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('import-revisi.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <p class="text-xs text-slate-500 mb-4">Unggah hingga 12 bulan sekaligus. Kosongkan bulan yang tidak berubah.</p>

                    <div class="space-y-3 max-h-60 overflow-y-auto pr-2 mb-6">
                        @for($i = 1; $i <= 12; $i++)
                        <div class="flex items-center justify-between border border-slate-200 rounded-xl p-3 bg-slate-50">
                            <span class="text-sm font-semibold text-slate-700 w-24">
                                {{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}
                            </span>
                            <input type="file" name="files[{{ $i }}]" accept=".xlsx,.xls,.csv" class="flex-1 ml-4 text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        @endfor
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Revisi</label>
                            <select name="jenis" required class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Pilih Jenis --</option>
                                <option value="pergeseran" @selected(old('jenis') === 'pergeseran')>Pergeseran</option>
                                <option value="pak" @selected(old('jenis') === 'pak')>Perubahan Anggaran (PAK)</option>
                            </select>
                            @error('jenis') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Revisi</label>
                            <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('tanggal') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Sumber Dana</label>
                            <select name="sumber_dana_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Pilih Sumber Dana --</option>
                                @foreach(\App\Models\SumberDana::orderBy('kode')->get() as $sd)
                                    <option value="{{ $sd->id }}" @selected(old('sumber_dana_id') == $sd->id)>{{ $sd->kode }} - {{ $sd->nama }}</option>
                                @endforeach
                            </select>
                            @error('sumber_dana_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Keterangan <span class="text-slate-400 font-normal">(opsional)</span></label>
                            <textarea name="keterangan" rows="2" maxlength="500" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('keterangan') }}</textarea>
                            @error('keterangan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Terapkan Revisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <span class="card-title">Riwayat Revisi</span>
            </div>
            <div class="card-body">
                @if($riwayat->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No. Revisi</th>
                                    <th>Jenis</th>
                                    <th>Tanggal</th>
                                    <th>Sumber Dana</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riwayat as $rev)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $rev->no_revisi }}</td>
                                        <td>
                                            <span class="badge {{ $rev->jenis === 'pak' ? 'badge-purple' : 'badge-blue' }}">{{ strtoupper($rev->jenis) }}</span>
                                        </td>
                                        <td>{{ $rev->tanggal->translatedFormat('d M Y') }}</td>
                                        <td>{{ $rev->sumberDana?->kode ?? '-' }}</td>
                                        <td class="text-right">Rp {{ number_format($rev->sesudah_total, 0, ',', '.') }}</td>
                                        <td>
                                            <a href="{{ route('import-revisi.show', $rev) }}" class="btn btn-info btn-sm">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $riwayat->links() }}
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center text-slate-400 py-12">
                        <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-sm">Belum ada riwayat revisi.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
