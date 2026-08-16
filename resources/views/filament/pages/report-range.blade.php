<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Date Range') }}</x-slot>

        <div class="flex flex-wrap items-end gap-4">
            <div class="grid gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300" for="from">{{ __('From') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="from" type="date" wire:model.live="from" max="{{ $this->until }}" />
                </x-filament::input.wrapper>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300" for="until">{{ __('Until') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="until" type="date" wire:model.live="until" min="{{ $this->from }}" />
                </x-filament::input.wrapper>
            </div>
        </div>

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $this->description() }}</p>

        @if ($this->hasExport())
            {{-- Slot komponen section bernama "footer" — "footerActions" dibuang diam-diam. --}}
            <x-slot name="footer">
                <x-filament::button wire:click="download" icon="heroicon-o-arrow-down-tray">
                    {{ __('Download Excel') }}
                </x-filament::button>
            </x-slot>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
