<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class JadwalSiswaExport
{
    // Urutan hari sama dengan komponen JadwalSiswaDetail
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    private const LAST_COL = 'I';

    /**
     * @param Collection $siswaList Siswa dengan relasi cabang, kelasList.jadwal.guru sudah dimuat
     * @param array      $meta      cabang, pencarian (string)
     */
    public function build(Collection $siswaList, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $last = self::LAST_COL;

        foreach (['A' => 6, 'B' => 28, 'C' => 14, 'D' => 16, 'E' => 12, 'F' => 24, 'G' => 11, 'H' => 12, 'I' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $row = 1;

        // Judul laporan
        $sheet->mergeCells("A{$row}:{$last}{$row}");
        $sheet->setCellValue("A{$row}", 'Jadwal Siswa');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        // Keterangan filter
        foreach (['Cabang' => 'cabang', 'Pencarian' => 'pencarian'] as $label => $key) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("C{$row}:{$last}{$row}");
            $sheet->setCellValue("C{$row}", ': ' . ($meta[$key] ?? '-'));
            $row++;
        }
        $row++;

        // Siswa dipecah per program, lalu dikelompokkan: cabang -> program -> siswa
        $perCabang = $siswaList
            ->flatMap(fn ($siswa) => $siswa->kelasList->map(fn ($kelas) => ['siswa' => $siswa, 'kelas' => $kelas]))
            ->groupBy(fn ($e) => $e['siswa']->cabang_id ?? 0)
            ->sortBy(fn ($g) => $g->first()['siswa']->cabang?->nama_cabang ?? '');

        $banyakCabang = $perCabang->count() > 1;

        foreach ($perCabang as $entriesCabang) {
            if ($banyakCabang) {
                $namaCabang = $entriesCabang->first()['siswa']->cabang?->nama_cabang ?? 'Tanpa Cabang';

                $sheet->mergeCells("A{$row}:{$last}{$row}");
                $sheet->setCellValue("A{$row}", $namaCabang . ' Branch');
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row += 2;
            }

            $perProgram = $entriesCabang
                ->groupBy(fn ($e) => $e['kelas']->id)
                ->sortBy(fn ($g) => $g->first()['kelas']->nama_kelas);

            foreach ($perProgram as $entriesProgram) {
                // Baris "Program : ..."
                $sheet->setCellValue("A{$row}", 'Program');
                $sheet->mergeCells("B{$row}:{$last}{$row}");
                $sheet->setCellValue("B{$row}", ': ' . $entriesProgram->first()['kelas']->nama_kelas);
                $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
                $row++;

                // Header tabel
                $headerRow = $row;
                foreach (['No', 'Nama Siswa', 'Gabung', 'Status Program', 'Hari', 'Guru', 'Jam Mulai', 'Jam Selesai', 'Status Jadwal'] as $i => $label) {
                    $sheet->setCellValue(chr(65 + $i) . $row, $label);
                }
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;

                $no = 1;

                foreach ($entriesProgram->sortBy(fn ($e) => $e['siswa']->nama) as $entry) {
                    $siswa = $entry['siswa'];
                    $kelas = $entry['kelas'];

                    $jadwal = $kelas->jadwal
                        ->sortBy(fn ($item) => array_search($item->hari, self::HARI))
                        ->values();

                    $startRow = $row;

                    if ($jadwal->isEmpty()) {
                        foreach (['E', 'F', 'G', 'H', 'I'] as $col) {
                            $sheet->setCellValue("{$col}{$row}", '-');
                        }
                        $row++;
                    } else {
                        foreach ($jadwal as $item) {
                            $sheet->setCellValue("E{$row}", $item->hari);
                            $sheet->setCellValue("F{$row}", $item->guru?->nama ?? '-');
                            $sheet->setCellValue("G{$row}", \Carbon\Carbon::parse($item->jam_mulai)->format('H:i'));
                            $sheet->setCellValue("H{$row}", \Carbon\Carbon::parse($item->jam_selesai)->format('H:i'));
                            $sheet->setCellValue("I{$row}", $item->is_active ? 'Aktif' : 'Nonaktif');
                            $row++;
                        }
                    }

                    $endRow = $row - 1;

                    $sheet->setCellValue("A{$startRow}", $no++);
                    $sheet->setCellValue("B{$startRow}", $siswa->nama);
                    $sheet->setCellValue(
                        "C{$startRow}",
                        $kelas->pivot->tanggal_gabung
                            ? \Carbon\Carbon::parse($kelas->pivot->tanggal_gabung)->format('d/m/Y')
                            : '-'
                    );
                    $sheet->setCellValue("D{$startRow}", $kelas->pivot->is_active ? 'Aktif' : 'Nonaktif');

                    if ($endRow > $startRow) {
                        foreach (['A', 'B', 'C', 'D'] as $col) {
                            $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                        }
                    }

                    $sheet->getStyle("A{$startRow}:D{$endRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }

                $tableEndRow = $row - 1;

                foreach (['A', 'C', 'D', 'E', 'G', 'H', 'I'] as $col) {
                    $sheet->getStyle("{$col}{$headerRow}:{$col}{$tableEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getStyle("A{$headerRow}:{$last}{$tableEndRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $row += 2; // jarak antar program
            }
        }

        return $spreadsheet;
    }
}