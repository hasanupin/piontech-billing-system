@php
    $user = auth()->user();
@endphp

<div class="fi-field-settings">
    {{-- Profil --}}
    <div class="fi-field-card fi-field-profile">
        <div class="fi-field-avatar">{{ Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($word) => Illuminate\Support\Str::substr($word, 0, 1))->take(2)->implode('') }}</div>
        <div>
            <div class="fi-field-profile-name">{{ $user->name }}</div>
            <div class="fi-field-profile-email">{{ $user->email }}</div>
            <span class="fi-field-chip">{{ $user->role->getLabel() }}</span>
        </div>
    </div>

    {{-- Cluster yang dipegang — hanya lihat. --}}
    <div class="fi-field-section-label">
        <span>{{ __('Clusters') }}</span>
        <span class="fi-field-readonly">{{ __('View only') }}</span>
    </div>

    @forelse ($clusters as $cluster)
        <div class="fi-field-card">
            <div class="fi-field-cluster-head">
                <span class="fi-field-profile-name">{{ $cluster->name }}</span>
                <span @class(['fi-field-chip', 'fi-field-chip-success' => $cluster->is_active])>
                    {{ $cluster->is_active ? __('Active') : __('Inactive') }}
                </span>
            </div>
            @if ($cluster->description)
                <div class="fi-field-profile-email">{{ $cluster->description }}</div>
            @endif
            <div class="fi-field-stat-foot">{{ trans_choice(':count customer|:count customers', $cluster->customers_count, ['count' => $cluster->customers_count]) }}</div>
        </div>
    @empty
        <div class="fi-field-card fi-field-profile-email">{{ __('No clusters assigned yet.') }}</div>
    @endforelse

    {{-- Panduan pemakaian; halamannya menyesuaikan peran lewat route 'guide'. --}}
    <a class="fi-field-card fi-field-guide" href="{{ route('guide') }}" target="_blank" rel="noopener">
        <span class="fi-field-cluster-head">
            <span class="fi-field-profile-name">{{ __('User Guide') }}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
        </span>
        <span class="fi-field-profile-email">{{ __('How to collect, record payments, and register customers') }}</span>
    </a>
</div>
