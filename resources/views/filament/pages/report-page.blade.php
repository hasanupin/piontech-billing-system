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

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ([
            'recap' => ['title' => __('Monthly Payment Recap'), 'description' => __('Billed, collected (cash/transfer), success rate, trend vs last month')],
            'deposits' => ['title' => __('Officer Deposits'), 'description' => __('Per officer: cash collected, deposit history, remaining balance')],
            'arrears' => ['title' => __('Arrears / ISOLIR List'), 'description' => __('Unpaid & suspended customers with WhatsApp and cluster for follow-up')],
        ] as $type => $report)
            <x-filament::section>
                <x-slot name="heading">{{ $report['title'] }}</x-slot>

                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $report['description'] }}</p>

                <x-slot name="footerActions">
                    <x-filament::button wire:click="download('{{ $type }}')" icon="heroicon-o-arrow-down-tray">
                        {{ __('Download Excel') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
