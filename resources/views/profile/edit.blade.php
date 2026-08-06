<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Akun &amp; Login</div>
    </x-slot>

    @if(session('status') === 'profile-updated')
        <div class="alert-success mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ __('Akun berhasil diperbarui.') }}
        </div>
    @endif

    @if(session('status') === 'password-updated')
        <div class="alert-success mb-6">
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ __('Password berhasil diperbarui.') }}
        </div>
    @endif

    <div class="w-full space-y-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ __('Informasi Akun') }}</span>
            </div>
            <div class="card-body">
                <p class="text-xs text-slate-500 mb-4">Perbarui nama dan email yang dipakai untuk login.</p>
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ __('Ganti Password') }}</span>
            </div>
            <div class="card-body">
                <p class="text-xs text-slate-500 mb-4">Pastikan akun menggunakan password yang panjang dan acak agar tetap aman.</p>
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title text-red-600">{{ __('Hapus Akun') }}</span>
            </div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
