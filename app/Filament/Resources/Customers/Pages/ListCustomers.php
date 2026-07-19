<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\Role;
use App\Filament\Resources\Customers\CustomerResource;
use App\Imports\CustomerImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as SchemaActions;
use Illuminate\Support\Facades\Storage;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

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
                    $import = new CustomerImport();
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
            CreateAction::make(),
        ];
    }
}
