@php
    $m = $this->metrics();
@endphp

{{-- Wrapper wajib: yang menerapkan columnSpan ke grid dashboard. --}}
<x-filament-widgets::widget>
<div class="fi-noc-strip">
    <div class="fi-noc-strip-brand">
        <span class="fi-noc-strip-title">{{ __('Network Status') }}</span>
        <span class="fi-noc-strip-period">{{ $this->periodLabel() }}</span>
    </div>

    <div class="fi-noc-strip-chips">
        <span class="noc-chip noc-chip-success">
            <span class="noc-dot noc-dot-success"></span>
            {{ __('Active') }}
            <b>{{ number_format($m['active'], 0, ',', '.') }}</b>
        </span>

        <span class="noc-chip noc-chip-warning">
            <span class="noc-dot noc-dot-warning"></span>
            {{ __('Suspended') }}
            <b>{{ number_format($m['suspended'], 0, ',', '.') }}</b>
        </span>

        <span class="noc-chip noc-chip-primary">
            <span class="noc-dot noc-dot-primary"></span>
            {{ __('Due Today') }}
            <b>{{ number_format($m['due_today'], 0, ',', '.') }}</b>
        </span>

        <span @class([
            'noc-chip',
            'noc-chip-danger' => $m['held_by_officers'] > 0,
            'noc-chip-muted' => $m['held_by_officers'] <= 0,
        ])>
            <span @class([
                'noc-dot',
                'noc-dot-danger' => $m['held_by_officers'] > 0,
                'noc-dot-muted' => $m['held_by_officers'] <= 0,
            ])></span>
            {{ __('Held By Officers') }}
            <b>{{ \App\Filament\Widgets\BillingStatsOverview::rupiah($m['held_by_officers']) }}</b>
        </span>
    </div>

    <div class="fi-noc-strip-meter">
        <div class="fi-noc-strip-meter-head">
            <span>{{ __('Collection Rate') }}</span>
            <b>{{ $m['collection_rate'] }}%</b>
        </div>
        <div class="noc-meter" role="progressbar" aria-valuenow="{{ $m['collection_rate'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="noc-meter-fill" style="width: {{ min(100, $m['collection_rate']) }}%"></div>
        </div>
        <div class="fi-noc-strip-meter-foot">
            {{ __(':paid of :billed paid', ['paid' => $m['paid'], 'billed' => $m['billed']]) }}
        </div>
    </div>
</div>
</x-filament-widgets::widget>
