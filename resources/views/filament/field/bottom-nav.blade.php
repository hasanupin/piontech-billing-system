@php
    $dashboardUrl = App\Filament\Field\Pages\Dashboard::getUrl();
    $billingUrl = App\Filament\Field\Resources\Customers\FieldCustomerResource::getUrl('index');
    $settingsUrl = App\Filament\Field\Pages\Settings::getUrl();

    $current = url()->current();
    $billingActive = str_contains($current, '/pelanggan') || str_contains($current, '/transaksi');
    $settingsActive = $current === $settingsUrl;
    $dashboardActive = ! $billingActive && ! $settingsActive;
@endphp

{{-- Bottom nav 3 menu — pengganti sidebar/topbar navigation Filament. --}}
<nav class="fi-field-bottomnav">
    <a href="{{ $dashboardUrl }}" @class(['fi-field-navitem', 'fi-active' => $dashboardActive])>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        <span>{{ __('Dashboard') }}</span>
    </a>
    <a href="{{ $billingUrl }}" @class(['fi-field-navitem', 'fi-active' => $billingActive])>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
        <span>{{ __('Billing') }}</span>
    </a>
    <a href="{{ $settingsUrl }}" @class(['fi-field-navitem', 'fi-active' => $settingsActive])>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2v.1a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-3-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.1-3l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.2V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 3 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1Z"/></svg>
        <span>{{ __('Settings') }}</span>
    </a>
</nav>
