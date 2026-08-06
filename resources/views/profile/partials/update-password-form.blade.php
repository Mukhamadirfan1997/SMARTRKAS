<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-4">
        <label for="update_password_current_password" class="form-label">{{ __('Password Saat Ini') }}</label>
        <input id="update_password_current_password" name="current_password" type="password" class="form-input" autocomplete="current-password">
        @error('current_password', 'updatePassword')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-4">
        <label for="update_password_password" class="form-label">{{ __('Password Baru') }}</label>
        <input id="update_password_password" name="password" type="password" class="form-input" autocomplete="new-password">
        @error('password', 'updatePassword')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-4">
        <label for="update_password_password_confirmation" class="form-label">{{ __('Konfirmasi Password Baru') }}</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password">
        @error('password_confirmation', 'updatePassword')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <button type="submit" class="btn-primary">{{ __('Simpan') }}</button>
    </div>
</form>
