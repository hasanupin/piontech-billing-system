<?php

namespace App\Exports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export laporan xlsx via phpspreadsheet (TASK-12).
 * Subclass hanya mendefinisikan rows(): baris pertama heading, sisanya data.
 */
abstract class BaseExport
{
    public function __construct(protected Carbon $period)
    {
    }

    /** @return array<int, array<int, string|int|float>> */
    abstract public function rows(): array;

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($this->rows(), null, 'A1');

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    protected function rupiah(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
