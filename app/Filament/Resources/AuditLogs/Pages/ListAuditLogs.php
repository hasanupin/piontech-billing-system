<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Actions\ExportTableAction;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(
                'log_aktivitas',
                [
                    __('Time'),
                    __('User'),
                    __('Event'),
                    __('Object'),
                    __('Detail'),
                    __('Changes'),
                    __('URL'),
                    __('IP Address'),
                ],
                fn (AuditLog $record): array => [
                    $record->created_at?->format('Y-m-d H:i:s') ?? '',
                    $record->user?->name ?? '',
                    $record->event->getLabel(),
                    $record->subject_type ? __(class_basename($record->subject_type)) : '',
                    $record->subject_label ?? '',
                    $record->changesSummary(),
                    $record->url ?? '',
                    $record->ip_address ?? '',
                ],
            ),
        ];
    }
}
