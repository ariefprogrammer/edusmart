<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LaporanPresensiSiswaExport
{
    /**
     * @param Collection $rekap       hasil LaporanPresensiSiswa::getRekap()
     * @param array      $tanggalList hasil LaporanPresensiSiswa::getDateRange()
     * @param array      $meta        periode, cabang, program (string), namaCabang (id => nama)
     */
    public function build(Collection $rekap, array $tanggalList, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Lebar kolom tanggal cukup untuk kode terpanjang seperti "HD/A"
        $lebarTanggal = 8;
        $lebarTotal = 5;

        $jumlahTanggal = count($tanggalList);
        $kolomAwalTanggal = 3;
        $kolomAwalTotal = $kolomAwalTanggal + $jumlahTanggal;
        $kolomTerakhir = $kolomAwalTotal + 3;
        $huruf = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $lastCol = $huruf($kolomTerakhir);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($i = $kolomAwalTanggal; $i <= $kolomTerakhir; $i++) {
            $sheet->getColumnDimension($huruf($i))->setWidth($i < $kolomAwalTotal ? $lebarTanggal : $lebarTotal);
        }

        // Lebar seluruh tabel (satuan karakter), dipakai untuk memperkirakan tinggi baris keterangan
        $lebarTabel = 6 + 30 + ($jumlahTanggal * $lebarTanggal) + (4 * $lebarTotal);

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

        // Keterangan kode kehadiran
        $keterangan = [
            'Keterangan: H = Hadir, I = Izin, S = Sakit, A = Alpa.',
            'Lebih dari satu sesi dalam sehari: D = 2 sesi, T = 3 sesi, angka = 4 sesi atau lebih '
                . '(contoh: HD = hadir 2 sesi, H4 = hadir 4 sesi). Status berbeda dipisah "/" '
                . '(contoh: H/A = 1 hadir dan 1 alpa).',
            'Kolom H, I, S, A di bagian kanan tabel menghitung jumlah sesi, bukan jumlah hari.',
        ];

        foreach ($keterangan as $teks) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", $teks);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $sheet->getStyle("A{$row}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);

            // Excel tidak menyesuaikan tinggi sel yang digabung, jadi dihitung dari panjang teks
            $jumlahBaris = max(1, (int) ceil(mb_strlen($teks) / max($lebarTabel, 40)));
            $sheet->getRowDimension($row)->setRowHeight(15 * $jumlahBaris);

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
                    // Kode lengkap per hari: H, HD, HT, H/A, AD, dan seterusnya
                    $sheet->setCellValue(
                        $huruf($kolomAwalTanggal + $i) . $row,
                        $item->kode[$tanggal]['kode'] ?? '-'
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