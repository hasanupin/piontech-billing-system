<x-filament-panels::page>
    <div class="max-w-xs">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="period">
                @foreach ($this->periodOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
