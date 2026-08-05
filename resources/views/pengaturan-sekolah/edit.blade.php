<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Pengaturan Sekolah</div>
    </x-slot>

    @if(session('success'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <span class="card-title">Form Pengaturan Sekolah</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengaturan-sekolah.update') }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="form-label" for="npsn">NPSN</label>
                        <input type="text" name="npsn" id="npsn" value="{{ old('npsn', $pengaturanSekolah->npsn) }}" class="form-input" maxlength="20">
                        @error('npsn')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="nama">Nama Sekolah <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="nama" value="{{ old('nama', $pengaturanSekolah->nama) }}" class="form-input" maxlength="255" required>
                        @error('nama')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="alamat">Alamat</label>
                        <input type="text" name="alamat" id="alamat" value="{{ old('alamat', $pengaturanSekolah->alamat) }}" class="form-input" maxlength="255">
                        @error('alamat')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="kecamatan">Kecamatan</label>
                        <input type="text" name="kecamatan" id="kecamatan" value="{{ old('kecamatan', $pengaturanSekolah->kecamatan) }}" class="form-input" maxlength="100">
                        @error('kecamatan')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="kabupaten">Kabupaten</label>
                        <input type="text" name="kabupaten" id="kabupaten" value="{{ old('kabupaten', $pengaturanSekolah->kabupaten) }}" class="form-input" maxlength="100">
                        @error('kabupaten')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="provinsi">Provinsi</label>
                        <input type="text" name="provinsi" id="provinsi" value="{{ old('provinsi', $pengaturanSekolah->provinsi) }}" class="form-input" maxlength="100">
                        @error('provinsi')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="telepon">Telepon</label>
                        <input type="text" name="telepon" id="telepon" value="{{ old('telepon', $pengaturanSekolah->telepon) }}" class="form-input" maxlength="30">
                        @error('telepon')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $pengaturanSekolah->email) }}" class="form-input" maxlength="100">
                        @error('email')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="nama_kepsek">Nama Kepala Sekolah</label>
                        <input type="text" name="nama_kepsek" id="nama_kepsek" value="{{ old('nama_kepsek', $pengaturanSekolah->nama_kepsek) }}" class="form-input" maxlength="150">
                        @error('nama_kepsek')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="nip_kepsek">NIP Kepala Sekolah</label>
                        <input type="text" name="nip_kepsek" id="nip_kepsek" value="{{ old('nip_kepsek', $pengaturanSekolah->nip_kepsek) }}" class="form-input" maxlength="30">
                        @error('nip_kepsek')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="nama_bendahara">Nama Bendahara</label>
                        <input type="text" name="nama_bendahara" id="nama_bendahara" value="{{ old('nama_bendahara', $pengaturanSekolah->nama_bendahara) }}" class="form-input" maxlength="150">
                        @error('nama_bendahara')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="nip_bendahara">NIP Bendahara</label>
                        <input type="text" name="nip_bendahara" id="nip_bendahara" value="{{ old('nip_bendahara', $pengaturanSekolah->nip_bendahara) }}" class="form-input" maxlength="30">
                        @error('nip_bendahara')
                            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
