@php
    $customersUrl = App\Filament\Field\Resources\Customers\FieldCustomerResource::getUrl('index');
    $transactionsUrl = App\Filament\Field\Pages\Transactions::getUrl();
    $onTransactions = str_contains(url()->current(), '/transaksi');
@endphp

{{-- Segmented control Billing: Data Pelanggan | Transaksi. --}}
<div class="fi-field-tabs" role="tablist" aria-label="{{ __('Billing') }}">
    <a href="{{ $customersUrl }}" @class(['fi-field-tab', 'fi-active' => ! $onTransactions]) role="tab" aria-selected="{{ $onTransactions ? 'false' : 'true' }}">
        {{ __('Customer Data') }}
    </a>
    <a href="{{ $transactionsUrl }}" @class(['fi-field-tab', 'fi-active' => $onTransactions]) role="tab" aria-selected="{{ $onTransactions ? 'true' : 'false' }}">
        {{ __('Transactions') }}
    </a>
</div>
