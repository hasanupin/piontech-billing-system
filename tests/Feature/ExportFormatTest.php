<?php

namespace Tests\Feature;

use App\Exports\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Kontrak penulisan xlsx. Nomor WA adalah string berisi angka; kalau ditulis
 * apa adanya, phpspreadsheet mengubahnya jadi float dan Excel menampilkan
 * 6.28123E+11. Kolomnya wajib bertipe & berformat Text — sementara kolom
 * nominal wajib tetap angka supaya bisa di-SUM.
 */
class ExportFormatTest extends TestCase
{
    /** @param array<int, array<int, string|int|float|null>> $rows */
    private function sheetOf(array $rows): Worksheet
    {
        ob_start();
        Xlsx::stream('test.xlsx', $rows)->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $content);

        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        return $sheet;
    }

    public function testWhatsappColumnIsWrittenAsText(): void
    {
        $sheet = $this->sheetOf([
            [__('Name'), __('WhatsApp'), __('Amount')],
            ['Budi', '628123456789', 150000.0],
        ]);

        $this->assertSame('628123456789', $sheet->getCell('B2')->getValue());
        $this->assertSame(
            NumberFormat::FORMAT_TEXT,
            $sheet->getStyle('B2')->getNumberFormat()->getFormatCode(),
        );
    }

    public function testWhatsappColumnIsDetectedWhereverItSits(): void
    {
        $sheet = $this->sheetOf([
            [__('Name'), __('Cluster'), __('Address'), __('WhatsApp')],
            ['Budi', 'Melati', 'Jl. Mawar', '6281234567890'],
        ]);

        $this->assertSame('6281234567890', $sheet->getCell('D2')->getValue());
        $this->assertSame(
            NumberFormat::FORMAT_TEXT,
            $sheet->getStyle('D2')->getNumberFormat()->getFormatCode(),
        );
    }

    public function testAmountColumnStaysNumeric(): void
    {
        $sheet = $this->sheetOf([
            [__('Name'), __('WhatsApp'), __('Amount')],
            ['Budi', '628123456789', 150000.0],
        ]);

        $this->assertIsFloat($sheet->getCell('C2')->getValue());
        $this->assertNotSame(
            NumberFormat::FORMAT_TEXT,
            $sheet->getStyle('C2')->getNumberFormat()->getFormatCode(),
        );
    }

    public function testZeroAndEmptyValuesAreStillWritten(): void
    {
        $sheet = $this->sheetOf([
            [__('Name'), __('Amount')],
            ['Budi', 0.0],
        ]);

        $this->assertSame(0.0, $sheet->getCell('B2')->getValue());
    }
}
