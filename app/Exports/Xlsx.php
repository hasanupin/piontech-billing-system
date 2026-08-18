<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
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
        $sheet = $spreadsheet->getActiveSheet();

        // String tetap ditulis sebagai teks: tanpa ini binder bawaan mengubah
        // "628123456789" jadi float dan Excel menampilkan 6.28123E+11. Angka
        // (nominal sudah di-cast (float)/(int) di pemanggil) tetap numerik
        // supaya bisa di-SUM. setIgnoredErrors: matikan segitiga hijau
        // "number stored as text" di Excel.
        Cell::setValueBinder(
            (new StringValueBinder)->setNumericConversion(false)->setSetIgnoredErrors(true)
        );

        try {
            // strictNullComparison: tanpa ini nilai 0 dan '' dilewati diam-diam
            // (nominal 0 keluar sebagai sel kosong).
            $sheet->fromArray($rows, null, 'A1', true);

            foreach (self::textColumns($rows) as $column) {
                $sheet->getStyle($column.'1:'.$column.count($rows))
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        } finally {
            // Binder itu state global statis — jangan bocor ke request lain.
            Cell::setValueBinder(new DefaultValueBinder);
        }

        return response()->streamDownload(
            fn () => (new XlsxWriter($spreadsheet))->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Kolom yang harus berformat Text, dideteksi dari baris heading — supaya
     * export baru ikut otomatis tanpa mengoper parameter di tiap pemanggil.
     * ponytail: acuannya label heading; kalau nanti ada label WA yang berbeda,
     * ganti jadi parameter eksplisit di stream().
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @return array<int, string>
     */
    private static function textColumns(array $rows): array
    {
        $columns = [];

        foreach (array_values($rows[0] ?? []) as $index => $heading) {
            if ($heading === __('WhatsApp')) {
                $columns[] = Coordinate::stringFromColumnIndex($index + 1);
            }
        }

        return $columns;
    }
}
