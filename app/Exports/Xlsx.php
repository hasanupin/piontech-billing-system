<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penulis xlsx bersama: array baris (baris 0 heading) → streamed download.
 * Dipakai BaseExport (laporan) dan ExportTableAction (export tabel).
 */
class Xlsx
{
    /** @param array<int, array<int, string|int|float|null>> $rows */
    public static function stream(string $filename, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        return response()->streamDownload(
            fn () => (new XlsxWriter($spreadsheet))->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
