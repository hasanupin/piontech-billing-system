<?php

namespace App\Exports;

use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export laporan xlsx via phpspreadsheet (TASK-12).
 * Subclass hanya mendefinisikan rows(): baris pertama heading, sisanya data.
 */
abstract class BaseExport
{
    public function __construct(protected Carbon $from, protected Carbon $until)
    {
        // Normalisasi sekali di sini — subclass tinggal whereBetween.
        $this->from = $from->copy()->startOfDay();
        $this->until = $until->copy()->endOfDay();
    }

    /** @return array<int, array<int, string|int|float>> */
    abstract public function rows(): array;

    public function download(string $filename): StreamedResponse
    {
        return Xlsx::stream($filename, $this->rows());
    }

    protected function rupiah(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
