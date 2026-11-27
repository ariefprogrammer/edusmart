<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export halaman "Laporan Siswa Aktif": keterangan filter, ringkasan, pergerakan bulan ini,
 * dan detail siswa (satu baris per program).
 */
class LaporanSiswaAktifExport
{
    private const LAST_COL = 'H';

    private const GERAK = [
        'baru' => 'Baru bergabung',
        'kembali' => 'Kembali aktif',
        'berhenti' => 'Berhenti bulan ini',
        'masuk_keluar' => 'Bergabung & berhenti bulan ini',
    ];

    /**
     * @param array      $laporan    hasil $this->laporan (awal, akhir, bulan_berjalan, ringkasan, siswa)
     * @param array      $program    hasil $this->ringkasanProgram (jumlah, siswa, rasio)
     * @param Collection $siswaList  hasil $this->siswaTampil (sudah terfilter status dan pencarian)
     * @param array      $meta       bulan, cabang, program, status, pencarian (string)
     */
    public function build(array $laporan, array $program, Collection $siswaList, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Siswa Aktif');
        $last = self::LAST_COL;

        foreach (['A' => 6, 'B' => 30, 'C' => 22, 'D' => 14, 'E' => 30, 'F' => 30, 'G' => 16, 'H' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $r = $laporan['ringkasan'];
        $row = 1;

        // Judul
        $sheet->mergeCells("A{$row}:{$last}{$row}");
        $sheet->setCellValue("A{$row}", 'Laporan Siswa Aktif');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        // Keterangan filter
        $posisi = $this->tanggal($laporan['akhir'])
            . ($laporan['bulan_berjalan'] ? ' (bulan berjalan, sampai hari ini)' : ' (akhir bulan)');

        foreach ([
            'Bulan' => $meta['bulan'] ?? '-',
            'Cabang' => $meta['cabang'] ?? '-',
            'Program' => $meta['program'] ?? '-',
            'Posisi per' => $posisi,
            'Status' => $meta['status'] ?? '-',
            'Pencarian' => $meta['pencarian'] ?? '-',
        ] as $label => $nilai) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("C{$row}:{$last}{$row}");
            $sheet->setCellValueExplicit("C{$row}", ': ' . $nilai, DataType::TYPE_STRING);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Berdasarkan keikutsertaan program');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
        $row += 2;

        // Ringkasan
        $this->judulBagian($sheet, $row, 'Ringkasan');
        $this->barisNilai($sheet, $row, 'Siswa aktif', $r['aktif']);
        $this->barisNilai($sheet, $row, 'Siswa tidak aktif', $r['tidak_aktif']);
        $this->barisNilai(
            $sheet,
            $row,
            'Jumlah program',
            $program['jumlah'],
            number_format($program['rasio'], 1, ',', '.') . ' program per siswa aktif · ' . $program['siswa'] . ' siswa'
        );
        $this->barisNilai($sheet, $row, 'Total terdaftar', $r['total']);
        $row++;

        // Pergerakan bulan ini
        $this->judulBagian(
            $sheet,
            $row,
            'Pergerakan bulan ini (dihitung dari posisi ' . $this->tanggal($laporan['awal']) . ' sampai ' . $this->tanggal($laporan['akhir']) . ')'
        );
        $this->barisNilai($sheet, $row, 'Aktif ' . $this->tanggal($laporan['awal'], 'd M Y'), $r['aktif_awal'], 'Posisi akhir bulan lalu');
        $this->barisNilai($sheet, $row, '+ Baru bergabung', $r['baru'], 'Pertama kali aktif bulan ini');
        $this->barisNilai($sheet, $row, '+ Kembali aktif', $r['kembali'], 'Aktif lagi setelah sempat nonaktif');
        $this->barisNilai($sheet, $row, '− Berhenti', $r['berhenti'], 'Aktif bulan lalu, kini tidak aktif');
        $this->barisNilai(
            $sheet,
            $row,
            '= Aktif ' . $this->tanggal($laporan['akhir'], 'd M Y'),
            $r['aktif'],
            $laporan['bulan_berjalan'] ? 'Posisi hari ini' : 'Posisi akhir bulan',
            true
        );

        if (($r['masuk_keluar'] ?? 0) > 0) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->setCellValue(
                "A{$row}",
                $r['masuk_keluar'] . ' siswa bergabung lalu berhenti dalam bulan yang sama, sehingga tidak muncul di hitungan di atas dan masuk ke siswa tidak aktif.'
            );
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
        }
        $row++;

        // Detail siswa
        $this->judulBagian($sheet, $row, 'Detail siswa (' . $siswaList->count() . ' siswa)');

        $headerRow = $row;
        foreach (['No', 'Nama Siswa', 'Cabang', 'Status', 'Pergerakan', 'Program', 'Status Program', 'Sejak'] as $i => $label) {
            $sheet->setCellValue(chr(65 + $i) . $row, $label);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $no = 1;

        foreach ($siswaList as $siswa) {
            $programList = collect($siswa['program'] ?? [])->values();
            $startRow = $row;

            if ($programList->isEmpty()) {
                foreach (['F', 'G', 'H'] as $col) {
                    $sheet->setCellValue("{$col}{$row}", '-');
                }
                $row++;
            } else {
                foreach ($programList as $p) {
                    $sheet->setCellValueExplicit("F{$row}", (string) ($p['nama'] ?? '-'), DataType::TYPE_STRING);
                    $sheet->setCellValue("G{$row}", ! empty($p['aktif']) ? 'Aktif' : 'Nonaktif');
                    $sheet->setCellValue("H{$row}", filled($p['sejak'] ?? null) ? $this->tanggal($p['sejak'], 'd M Y') : '-');
                    $row++;
                }
            }

            $endRow = $row - 1;

            $sheet->setCellValue("A{$startRow}", $no++);
            $sheet->setCellValueExplicit("B{$startRow}", (string) $siswa['nama'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$startRow}", (string) ($siswa['cabang'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$startRow}", ($siswa['status'] ?? null) === 'aktif' ? 'Aktif' : 'Tidak aktif');
            $sheet->setCellValue("E{$startRow}", self::GERAK[$siswa['gerak'] ?? ''] ?? '-');

            if ($endRow > $startRow) {
                foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                    $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                }
            }

            $sheet->getStyle("A{$startRow}:E{$endRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $tableEndRow = max($row - 1, $headerRow);

        foreach (['A', 'D', 'E', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$headerRow}:{$col}{$tableEndRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getStyle("A{$headerRow}:{$last}{$tableEndRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $spreadsheet;
    }

    private function judulBagian(Worksheet $sheet, int &$row, string $teks): void
    {
        $last = self::LAST_COL;

        $sheet->mergeCells("A{$row}:{$last}{$row}");
        $sheet->setCellValue("A{$row}", $teks);
        $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $row++;
    }

    /** Label di A:C, angka di D, keterangan opsional di E:H. */
    private function barisNilai(Worksheet $sheet, int &$row, string $label, int|float $nilai, string $ket = '', bool $sorot = false): void
    {
        $last = self::LAST_COL;

        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $sheet->setCellValue("D{$row}", $nilai);
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);

        $sheet->mergeCells("E{$row}:{$last}{$row}");
        $sheet->setCellValueExplicit("E{$row}", $ket, DataType::TYPE_STRING);

        if ($sorot) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        }

        $sheet->getStyle("A{$row}:{$last}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }

    private function tanggal(string $tanggal, string $format = 'd F Y'): string
    {
        return CarbonImmutable::parse($tanggal)->locale('id')->translatedFormat($format);
    }
}