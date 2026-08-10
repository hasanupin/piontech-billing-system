<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Actions\ExportTableAction;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(
                'transaksi',
                [
                    __('Customer'),
                    __('Cluster'),
                    __('Period'),
                    __('Billed Amount'),
                    __('Paid Amount'),
                    __('Status'),
                    __('Payment Method'),
                    __('Officer'),
                    __('Paid At'),
                ],
                fn (Transaction $record): array => [
                    $record->customer?->name ?? '',
                    $record->customer?->cluster?->name ?? '',
                    $record->period->translatedFormat('F Y'),
                    (float) $record->billed_amount,
                    (float) $record->paid_amount,
                    $record->status->getLabel(),
                    $record->payment_method?->getLabel() ?? '',
                    $record->officer?->name ?? '',
                    $record->paid_at?->format('d/m/Y H:i') ?? '',
                ],
            ),
            CreateAction::make(),
        ];
    }
}
