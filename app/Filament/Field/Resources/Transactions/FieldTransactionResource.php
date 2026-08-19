<?php

namespace App\Filament\Field\Resources\Transactions;

use App\Enums\Role;
use App\Filament\Field\Pages\Transactions;
use App\Filament\Field\Resources\Transactions\Pages\CreateFieldTransaction;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Hanya halaman create — daftar & filter transaksi ada di halaman
 * App\Filament\Field\Pages\Transactions (daftar tagihan per periode).
 */
class FieldTransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $slug = 'transaksi';

    public static function getModelLabel(): string
    {
        return __('Transaction');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::FieldOfficer) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateFieldTransaction::route('/create'),
        ];
    }

    /**
     * Resource ini tanpa halaman index — breadcrumb & redirect "index"
     * diarahkan ke halaman daftar tagihan per periode.
     */
    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return Transactions::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }
}
