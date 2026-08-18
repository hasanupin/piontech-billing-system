<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retensi audit log (6 bulan, AuditLog::RETENTION_MONTHS). --model dibatasi
// supaya model:prune tidak menyapu model lain. Butuh cron schedule:run.
Schedule::command('model:prune', ['--model' => [AuditLog::class]])->daily();
