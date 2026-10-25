<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LaporanPresensiSiswaExport
{
    private const HURUF = [
        'hadir' => 'H',
        'izin'  => 'I',
        'sakit' => 'S',
        'alpa'  => 'A',
    ];

    /**
     * @param Collection $rekap       hasil LaporanPresensiSiswa::getRekap()
     * @param array      $tanggalList hasil LaporanPresensiSiswa::getDateRange()
     * @param array      $meta        periode, cabang, program (string), namaCabang (id => nama)
     */
    public function build(Collection $rekap, array $tanggalList, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $jumlahTanggal = count($tanggalList);
        $kolomAwalTanggal = 3;
        $kolomAwalTotal = $kolomAwalTanggal + $jumlahTanggal;
        $kolomTerakhir = $kolomAwalTotal + 3;
        $huruf = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $lastCol = $huruf($kolomTerakhir);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($i = $kolomAwalTanggal; $i <= $kolomTerakhir; $i++) {
            $sheet->getColumnDimension($huruf($i))->setWidth($i < $kolomAwalTotal ? 6 : 5);
        }

        $row = 1;

        // Judul laporan
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Laporan Presensi Siswa');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        // Informasi filter
        foreach (['Periode' => 'periode', 'Cabang' => 'cabang', 'Program' => 'program'] as $label => $key) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("C{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("C{$row}", ': ' . ($meta[$key] ?? '-'));
            $row++;
        }
        $row++;

        $namaCabang = $meta['namaCabang'] ?? [];
        $perCabang = $rekap->groupBy(fn ($item) => $item->siswa->cabang_id);
        $banyakCabang = $perCabang->count() > 1;

        foreach ($perCabang as $cabangId => $barisCabang) {
            // Judul cabang, hanya jika data lebih dari satu cabang
            if ($banyakCabang) {
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->setCellValue("A{$row}", ($namaCabang[$cabangId] ?? 'Tanpa Cabang') . ' Branch');
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row += 2;
            }

            // Header tabel
            $headerRow = $row;
            $sheet->setCellValue("A{$row}", 'No');
            $sheet->setCellValue("B{$row}", 'Nama Siswa');
            foreach ($tanggalList as $i => $tanggal) {
                $sheet->setCellValue($huruf($kolomAwalTanggal + $i) . $row, \Carbon\Carbon::parse($tanggal)->format('d/m'));
            }
            foreach (['H', 'I', 'S', 'A'] as $i => $label) {
                $sheet->setCellValue($huruf($kolomAwalTotal + $i) . $row, $label);
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $row++;

            // Baris siswa
            $no = 1;
            foreach ($barisCabang as $item) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValue("B{$row}", $item->siswa->nama);

                foreach ($tanggalList as $i => $tanggal) {
                    $status = $item->harian[$tanggal] ?? null;
                    $sheet->setCellValue(
                        $huruf($kolomAwalTanggal + $i) . $row,
                        self::HURUF[$status] ?? '-'
                    );
                }

                $sheet->setCellValue($huruf($kolomAwalTotal) . $row, $item->hadir);
                $sheet->setCellValue($huruf($kolomAwalTotal + 1) . $row, $item->izin);
                $sheet->setCellValue($huruf($kolomAwalTotal + 2) . $row, $item->sakit);
                $sheet->setCellValue($huruf($kolomAwalTotal + 3) . $row, $item->alpa);
                $row++;
            }

            $tableEndRow = $row - 1;

            // Rata tengah semua kolom kecuali nama, lalu border
            $sheet->getStyle("A{$headerRow}:A{$tableEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$headerRow}:{$lastCol}{$tableEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$tableEndRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $row += 2; // jarak antar cabang
        }

        return $spreadsheet;
    }
}