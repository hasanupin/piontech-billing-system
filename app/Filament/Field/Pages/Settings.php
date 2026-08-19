<?php

namespace App\Filament\Field\Pages;

use App\Enums\Role;
use App\Models\Cluster;
use App\Models\OfficerDeposit;
use App\Services\ScopeService;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pengaturan petugas: profil, cluster yang dipegang (hanya lihat — sengaja
 * lewat Page + ScopeService, bukan ClusterResource, karena ClusterPolicy
 * menolak petugas), dan riwayat setoran read-only (pencatatan setoran tetap
 * wewenang admin di desktop).
 */
class Settings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'pengaturan';

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::FieldOfficer) ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.field.settings-info')
                ->viewData(fn (): array => ['clusters' => $this->clusters()]),
            EmbeddedTable::make(),
            View::make('filament.field.logout'),
        ]);
    }

    /**
     * @return Collection<int, Cluster>
     */
    public function clusters(): Collection
    {
        return app(ScopeService::class)
            ->scopeClustersForUser(Cluster::query(), auth()->user())
            ->withCount('customers')
            ->orderBy('name')
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('My Deposits'))
            ->query(fn (): Builder => app(ScopeService::class)
                ->scopeDepositsForUser(OfficerDeposit::query(), auth()->user()))
            ->columns([
                TextColumn::make('period')
                    ->label(__('Period'))
                    ->date('F Y'),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('IDR'),
                TextColumn::make('deposited_at')
                    ->label(__('Deposited At'))
                    ->dateTime('d M Y H:i'),
            ])
            ->defaultSort('deposited_at', 'desc');
    }
}
