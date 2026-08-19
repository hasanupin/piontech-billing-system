{{-- Logout eksplisit, selain yang ada di user menu topbar. --}}
<form method="POST" action="{{ route('filament.field.auth.logout') }}">
    @csrf
    <button type="submit" class="fi-field-logout">{{ __('Sign out') }}</button>
</form>
