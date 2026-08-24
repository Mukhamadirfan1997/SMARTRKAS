<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Edit Pencairan</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="card max-w-2xl mx-auto">
        <div class="card-header">
            <span class="card-title">Data Pencairan</span>
            <a href="{{ route('pencairan.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
        </div>
        <form method="POST" action="{{ route('pencairan.update', $pencairan) }}">
            @csrf
            @method('PUT')
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label" for="tanggal">Tanggal Pencairan</label>
                    <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', $pencairan->tanggal->toDateString()) }}" required class="form-input @error('tanggal') border-red-500 @enderror">
                    @error('tanggal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="sumber_dana_id">Sumber Dana</label>
                    <select name="sumber_dana_id" id="sumber_dana_id" required class="form-input @error('sumber_dana_id') border-red-500 @enderror">
                        <option value="">-- Pilih Sumber Dana --</option>
                        @foreach($sumberDanas as $sd)
                            <option value="{{ $sd->id }}" {{ old('sumber_dana_id', $pencairan->sumber_dana_id) === $sd->id ? 'selected' : '' }}>{{ $sd->kode }} - {{ $sd->nama }}</option>
                        @endforeach
                    </select>
                    @error('sumber_dana_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="nominal">Nominal (Rp)</label>
                    <input type="text" name="nominal" id="nominal" inputmode="decimal" value="{{ old('nominal', number_format((float) $pencairan->nominal, 0, ',', '.')) }}" placeholder="cth. 90.160.000" required class="form-input @error('nominal') border-red-500 @enderror">
                    <p class="text-xs text-slate-400 mt-1">Format Indonesia: titik ribuan tanpa desimal.</p>
                    @error('nominal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="keterangan">Keterangan</label>
                    <input type="text" name="keterangan" id="keterangan" value="{{ old('keterangan', $pencairan->keterangan) }}" maxlength="255" class="form-input @error('keterangan') border-red-500 @enderror">
                    @error('keterangan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    <a href="{{ route('pencairan.index') }}" class="btn btn-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
