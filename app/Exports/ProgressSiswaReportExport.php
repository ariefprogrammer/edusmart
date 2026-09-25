<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProgressSiswaReportExport
{
    public function build(Collection $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(40);

        $row = 1;

        $byCabang = $rows->groupBy(fn ($item) => $item->cabang?->nama_cabang ?? 'Tanpa Cabang');

        foreach ($byCabang as $cabangName => $cabangRows) {
            // Judul cabang
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", $cabangName . ' Branch');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row += 2;

            $bySiswa = $cabangRows->groupBy(fn ($item) => $item->siswa?->nama ?? 'Tanpa Nama');

            foreach ($bySiswa as $siswaName => $siswaRows) {
                // Baris "Nama : Siswa X"
                $sheet->setCellValue("A{$row}", 'Nama');
                $sheet->mergeCells("B{$row}:D{$row}");
                $sheet->setCellValue("B{$row}", ': ' . $siswaName);
                $row++;

                // Header tabel
                $headerRow = $row;
                $sheet->setCellValue("A{$headerRow}", 'No');
                $sheet->setCellValue("B{$headerRow}", 'Program');
                $sheet->setCellValue("C{$headerRow}", 'Tanggal');
                $sheet->setCellValue("D{$headerRow}", 'Catatan');
                $sheet->getStyle("A{$headerRow}:D{$headerRow}")->getFont()->setBold(true);
                $row++;

                $byProgram = $siswaRows
                    ->filter(fn ($item) => $item->kelas !== null)
                    ->groupBy(fn ($item) => $item->kelas->nama_kelas);

                $no = 1;

                foreach ($byProgram as $programName => $programRows) {
                    $startRow = $row;

                    foreach ($programRows as $item) {
                        $sheet->setCellValue("C{$row}", \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y'));
                        $sheet->setCellValue("D{$row}", $item->catatan ?: '-');
                        $row++;
                    }

                    $endRow = $row - 1;

                    $sheet->setCellValue("A{$startRow}", $no);
                    $sheet->setCellValue("B{$startRow}", $programName);

                    if ($endRow > $startRow) {
                        $sheet->mergeCells("A{$startRow}:A{$endRow}");
                        $sheet->mergeCells("B{$startRow}:B{$endRow}");
                    }

                    $sheet->getStyle("A{$startRow}:A{$endRow}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("B{$startRow}:B{$endRow}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $no++;
                }

                $tableEndRow = $row - 1;

                $sheet->getStyle("A{$headerRow}:D{$tableEndRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $row += 2; // jarak antar blok siswa
            }
        }

        return $spreadsheet;
    }
}