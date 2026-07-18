<?php

namespace App\Providers;

use App\Models\User;
use App\Services\BillingService;
use App\Services\ScopeService;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ScopeService::class);
        $this->app->singleton(BillingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admin (CEO) punya akses penuh ke semua fitur tanpa terkecuali.
        // Return null agar role lain jatuh ke policy masing-masing.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Default pagination semua table: 20 per halaman, dengan opsi "Semua".
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([20, 50, 100, 'all'])
                ->defaultPaginationPageOption(20);
        });
    }
}
