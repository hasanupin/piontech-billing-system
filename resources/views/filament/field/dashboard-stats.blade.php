@php
    $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

{{-- 3 angka utama petugas: tagih hari ini, cash di tangan, belum bayar. --}}
<div class="fi-field-stats">
    <div class="fi-field-stat">
        <span class="fi-field-stat-label">{{ __('Due Today') }}</span>
        <span class="fi-field-stat-value">{{ $stats['due_today'] }}</span>
        <span class="fi-field-stat-foot">{{ __('Customers') }}</span>
    </div>
    <div class="fi-field-stat fi-field-stat-warning">
        <span class="fi-field-stat-label">{{ __('Cash on Hand') }}</span>
        <span class="fi-field-stat-value fi-money">{{ $rupiah($stats['cash_on_hand']) }}</span>
        <span class="fi-field-stat-foot">{{ __('Cash collected − deposited') }}</span>
    </div>
    <div class="fi-field-stat fi-field-stat-danger">
        <span class="fi-field-stat-label">{{ __('Unpaid') }}</span>
        <span class="fi-field-stat-value">{{ $stats['unpaid'] }}</span>
        <span class="fi-field-stat-foot">{{ __('of :total customers', ['total' => $stats['total']]) }}</span>
    </div>
</div>
