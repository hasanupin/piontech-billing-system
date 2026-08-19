<?php

namespace App\Filament\Field\Resources\Transactions\Pages;

use App\Filament\Field\Pages\Transactions;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;

/**
 * Extends CreateTransaction supaya prefill ?customer_id / ?period milik
 * TransactionForm (instanceof CreateTransaction) tetap jalan di panel field.
 */
class CreateFieldTransaction extends CreateTransaction
{
    protected static string $resource = FieldTransactionResource::class;

    /** Alur mobile selalu kembali ke daftar tagihan, tanpa popup. */
    protected function shouldPromptAfterCreate(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return Transactions::getUrl();
    }
}
