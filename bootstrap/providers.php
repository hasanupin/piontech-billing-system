<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\FieldPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FieldPanelProvider::class,
];
