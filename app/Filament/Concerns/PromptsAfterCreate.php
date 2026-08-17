<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;

/**
 * Mengganti tombol "Buat & buat lainnya" dengan popup setelah record tersimpan:
 * pilih kembali ke daftar (record baru dipin ke baris pertama lewat ?created=)
 * atau langsung isi form kosong lagi.
 */
trait PromptsAfterCreate
{
    /**
     * Tujuan tombol "Kembali", dihitung saat afterCreate() selagi $this->record
     * masih hidup lalu ikut tersimpan di state Livewire — tombol modal ditekan
     * di request berikutnya.
     */
    public ?string $createdRedirectUrl = null;

    /** Pilihan lanjut/berhenti dipindah ke popup setelah simpan. */
    public function canCreateAnother(): bool
    {
        return false;
    }

    /** Hook untuk mematikan popup di alur tertentu. */
    protected function shouldPromptAfterCreate(): bool
    {
        return true;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldPromptAfterCreate()) {
            return;
        }

        $this->createdRedirectUrl = $this->getCreatedRedirectUrl();

        $this->mountAction('createdPrompt');

        // Menahan redirect bawaan CreateRecord. Halt default tidak me-rollback,
        // jadi record tetap ter-commit; notifikasi hijau bawaan digantikan modal.
        throw new Halt;
    }

    protected function getCreatedRedirectUrl(): string
    {
        return static::getResource()::getUrl('index', ['created' => $this->getRecord()->getKey()]);
    }

    public function createdPromptAction(): Action
    {
        return Action::make('createdPrompt')
            ->modalHeading(__('Data Saved'))
            ->modalDescription(__('What would you like to do next?'))
            ->modalSubmitActionLabel(__('Back to List'))
            ->modalCancelAction(false)
            // Modal wajib dijawab: kalau ditutup, user balik ke form yang masih
            // terisi dan bisa submit ulang tanpa sadar.
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('another', ['another' => true])
                    ->label(__('Create Another'))
                    ->color('gray'),
            ])
            // Form baru lewat halaman create yang fresh — tidak perlu meniru
            // urutan reset schema internal Filament.
            ->action(fn (array $arguments) => $this->redirect(
                ($arguments['another'] ?? false)
                    ? static::getResource()::getUrl('create')
                    : $this->createdRedirectUrl,
            ));
    }
}
