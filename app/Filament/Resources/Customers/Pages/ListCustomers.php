<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\Role;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Imports\CustomerImport;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as SchemaActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    /**
     * Record yang baru dibuat dipin ke baris pertama (sort tabel jadi
     * tie-breaker). ?created= hanya ada di GET awal, jadi pin otomatis lepas
     * begitu user menyentuh tabel.
     */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->when(request()->query('created'), fn (Builder $query, string $id) => $query->orderByRaw('id = ? desc', [$id]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Import Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false)
                ->schema([
                    // Tombol template DI DALAM modal — user unduh, isi, upload balik.
                    SchemaActions::make([
                        Action::make('downloadTemplate')
                            ->label(__('Download Template'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->action(fn () => response()->streamDownload(
                                fn () => print (CustomerImport::templateContent()),
                                'template_pelanggan.xlsx',
                                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                            )),
                    ]),
                    FileUpload::make('file')
                        ->label(__('Excel File'))
                        ->helperText(__('Columns: NAMA, WA, PAKET, HARGA, CLUSTER, ALAMAT, TGL TAGIH, KET., MAPS. Cluster is optional — assign later via the app.'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $import = new CustomerImport;
                    $import->import(Storage::disk('public')->path($data['file']));

                    $failed = count($import->failures);
                    $body = __(':success succeeded, :failed failed', [
                        'success' => $import->imported,
                        'failed' => $failed,
                    ]);

                    if ($failed > 0) {
                        // Log gagal per baris ditampilkan langsung di notifikasi.
                        $body .= "\n".collect($import->failures)
                            ->map(fn (string $msg, int $line): string => __('Row :line: :message', ['line' => $line, 'message' => $msg]))
                            ->implode("\n");
                    }

                    Notification::make()
                        ->title(__('Import Finished'))
                        ->body($body)
                        ->{$failed > 0 ? 'warning' : 'success'}()
                        ->persistent()
                        ->send();
                }),
            ExportTableAction::make(
                'pelanggan',
                [
                    __('Name'),
                    __('Cluster'),
                    __('Package'),
                    __('Billing Amount'),
                    __('Address'),
                    __('Officer'),
                    __('Billing Day'),
                    __('WhatsApp'),
                    __('Status'),
                    __('Referral'),
                    __('Maps'),
                ],
                fn (Customer $record): array => [
                    $record->name,
                    $record->cluster?->name ?? '',
                    $record->package?->name ?? '',
                    (float) $record->billing_amount,
                    $record->address ?? '',
                    $record->cluster?->officer?->name ?? '',
                    $record->billing_day,
                    $record->whatsapp_number ?? '',
                    $record->status->getLabel(),
                    $record->referral?->display_name ?? '',
                    $record->maps_url ?? '',
                ],
            ),
            CreateAction::make(),
        ];
    }
}
