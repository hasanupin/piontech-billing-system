<x-filament::dropdown.list>
    <div class="fi-theme-switcher">
        @foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $locale)
            <a
                href="{{ route('locale.switch', $locale) }}"
                @class([
                    'fi-theme-switcher-btn',
                    'fi-active' => app()->getLocale() === $locale,
                ])
                style="font-size: 0.875rem; font-weight: 600; line-height: 1.25rem;"
            >{{ strtoupper($locale) }}</a>
        @endforeach
    </div>
</x-filament::dropdown.list>
