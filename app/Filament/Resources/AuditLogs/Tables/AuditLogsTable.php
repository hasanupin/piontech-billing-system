<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge(),
                TextColumn::make('subject_type')
                    ->label(__('Object'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __(class_basename($state)) : '—'),
                TextColumn::make('subject_label')
                    ->label(__('Detail'))
                    ->searchable()
                    ->wrap(),
                // state(), BUKAN formatStateUsing(): kolom JSON punya state array dan
                // Filament memformat tiap elemennya, jadi ringkasannya keluar berulang.
                TextColumn::make('changed_values')
                    ->label(__('Changes'))
                    ->state(fn (AuditLog $record): string => $record->changesSummary())
                    ->tooltip(fn (AuditLog $record): ?string => $record->changesSummary() ?: null)
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('url')
                    ->label(__('URL'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label(__('IP Address'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('event')
                    ->label(__('Event'))
                    ->options(AuditEvent::class)
                    ->multiple(),
                SelectFilter::make('subject_type')
                    ->label(__('Object'))
                    ->options(fn (): array => AuditLog::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->map(fn (string $class): string => __(class_basename($class)))
                        ->all()),
                Filter::make('created_at')
                    ->label(__('Date'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                // Resource ini tanpa halaman View — detail lengkap lewat modal.
                ViewAction::make()
                    ->schema([
                        TextEntry::make('created_at')->label(__('Time'))->dateTime('d M Y H:i:s'),
                        TextEntry::make('user.name')->label(__('User')),
                        TextEntry::make('event')->label(__('Event'))->badge(),
                        TextEntry::make('subject_label')->label(__('Detail'))->placeholder('—'),
                        TextEntry::make('url')->label(__('URL'))->placeholder('—'),
                        TextEntry::make('ip_address')->label(__('IP Address'))->placeholder('—'),
                        TextEntry::make('changed_values')
                            ->label(__('Changes'))
                            ->columnSpanFull()
                            ->placeholder('—')
                            ->state(fn (AuditLog $record): string => $record->changesSummary()),
                    ]),
            ]);
        // Sengaja tanpa toolbarActions/delete: log tidak boleh diubah dari UI.
    }
}
