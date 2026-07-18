<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Exports\ArrearsExport;
use App\Exports\MonthlyRecapExport;
use App\Exports\OfficerDepositReportExport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan bulanan CEO (TASK-12) — 3 laporan export Excel.
 * PDF ditunda (keputusan: Excel-only dulu).
 */
class ReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected string $view = 'filament.pages.report-page';

    // Periode terpilih (format Y-m); default bulan & tahun ini.
    public ?string $period = null;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Billing');
    }

    public static function canAccess(): bool
    {
        // Hanya CEO & admin; petugas forbidden.
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->period ?: now()->format('Y-m')).'-01')->startOfMonth();
    }

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return Dashboard::periodOptions();
    }

    public function download(string $type): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $period = $this->periodStart();
        $suffix = $period->format('Y-m');

        return match ($type) {
            'recap' => (new MonthlyRecapExport($period))->download("rekap-{$suffix}.xlsx"),
            'deposits' => (new OfficerDepositReportExport($period))->download("setoran-{$suffix}.xlsx"),
            'arrears' => (new ArrearsExport($period))->download("tunggakan-{$suffix}.xlsx"),
        };
    }
}
