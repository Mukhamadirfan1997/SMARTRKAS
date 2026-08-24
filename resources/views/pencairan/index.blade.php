<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Data Pencairan</div>
        <p class="text-sm text-slate-500 mt-0.5">Penerimaan SP2D / Transaksi Koran dari rekening bank sekolah (di luar Buku Kas Umum)</p>
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="stat-card blue">
            <div class="stat-icon bg-blue-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <div>
                <p class="stat-label">Total Pencairan {{ $tahunAnggaran?->tahun ?? '-' }}</p>
                <p class="stat-value">Rp {{ number_format($totalTahun, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="stat-card indigo">
            <div class="stat-icon bg-indigo-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="stat-label">Jumlah Transaksi</p>
                <p class="stat-value">{{ $pencairans->total() }}</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon bg-emerald-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="stat-label">Sumber Dana Aktif</p>
                <p class="stat-value">{{ $sumberDanas->count() }}</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('pencairan.index') }}" class="card mb-6">
        <div class="card-body flex flex-wrap items-end gap-3">
            <div>
                <label class="form-label" for="filter_tahun">Tahun Anggaran</label>
                <select name="tahun" id="filter_tahun" class="form-input py-1.5 text-sm" onchange="this.form.submit()">
                    @forelse($tahunAnggarans as $ta)
                        <option value="{{ $ta->id }}" {{ (request()->input('tahun') ?: $tahunAnggaran?->id) === $ta->id ? 'selected' : '' }}>
                            {{ $ta->tahun }}{{ $ta->status ? ' (aktif)' : '' }}
                        </option>
                    @empty
                        <option value="">-</option>
                    @endforelse
                </select>
            </div>
            <div>
                <label class="form-label" for="filter_sumber_dana_id">Sumber Dana</label>
                <select name="sumber_dana_id" id="filter_sumber_dana_id" class="form-input py-1.5 text-sm" onchange="this.form.submit()">
                    <option value="">Semua Sumber Dana</option>
                    @foreach($sumberDanas as $sd)
                        <option value="{{ $sd->id }}" {{ request()->input('sumber_dana_id') === $sd->id ? 'selected' : '' }}>{{ $sd->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="card lg:col-span-1 self-start">
            <div class="card-header">
                <span class="card-title">Catat Pencairan</span>
            </div>
            <form method="POST" action="{{ route('pencairan.store', array_filter(['tahun' => request()->input('tahun'), 'sumber_dana_id' => request()->input('sumber_dana_id')])) }}">
                @csrf
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label" for="tanggal">Tanggal Pencairan</label>
                        <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" required class="form-input @error('tanggal') border-red-500 @enderror">
                        @error('tanggal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="sumber_dana_id">Sumber Dana</label>
                        <select name="sumber_dana_id" id="sumber_dana_id" required class="form-input @error('sumber_dana_id') border-red-500 @enderror">
                            <option value="">-- Pilih Sumber Dana --</option>
                            @foreach($sumberDanas as $sd)
                                <option value="{{ $sd->id }}" {{ old('sumber_dana_id') === $sd->id ? 'selected' : '' }}>{{ $sd->kode }} - {{ $sd->nama }}</option>
                            @endforeach
                        </select>
                        @error('sumber_dana_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="nominal">Nominal (Rp)</label>
                        <input type="text" name="nominal" id="nominal" inputmode="decimal" value="{{ old('nominal') }}" placeholder="cth. 90.160.000" required class="form-input @error('nominal') border-red-500 @enderror">
                        <p class="text-xs text-slate-400 mt-1">Format Indonesia: titik ribuan tanpa desimal.</p>
                        @error('nominal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="keterangan">Keterangan</label>
                        <input type="text" name="keterangan" id="keterangan" value="{{ old('keterangan') }}" placeholder="cth. SP2D Tahap 1 BOSP Reguler" maxlength="255" class="form-input @error('keterangan') border-red-500 @enderror">
                        @error('keterangan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center">
                        Simpan Pencairan
                    </button>
                </div>
            </form>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header">
                <span class="card-title">Riwayat Pencairan</span>
            </div>

            @if($pencairans->isEmpty())
                <div class="text-center py-12 text-slate-400">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <p class="mt-3 text-sm font-medium text-slate-500">Belum ada data pencairan{{ $tahunAnggaran ? ' tahun '.$tahunAnggaran->tahun : '' }}.</p>
                    <p class="text-xs mt-1">Catat penerimaan SP2D dari rekening koran melalui formulir di samping.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Sumber Dana</th>
                                <th>Nominal</th>
                                <th>Keterangan</th>
                                <th>Dicatat Oleh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pencairans as $pencairan)
                                <tr>
                                    <td>{{ $pencairan->tanggal->translatedFormat('d F Y') }} <span class="text-xs text-slate-400">(bln {{ $pencairan->bulan }})</span></td>
                                    <td>{{ $pencairan->sumberDana?->nama ?? '-' }}</td>
                                    <td class="font-medium text-slate-800 whitespace-nowrap">Rp {{ number_format((float) $pencairan->nominal, 0, ',', '.') }}</td>
                                    <td>{{ $pencairan->keterangan ?? '-' }}</td>
                                    <td class="text-sm text-slate-500">{{ $pencairan->createdBy?->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('pencairan.edit', $pencairan) }}" class="btn btn-secondary btn-sm">Edit</a>
                                            <form action="{{ route('pencairan.destroy', $pencairan) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus pencairan ini?')">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-200">
                    {{ $pencairans->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
