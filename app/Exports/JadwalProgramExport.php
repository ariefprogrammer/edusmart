<?php

namespace App\Exports;

use App\Models\Jadwal;
use App\Models\Kelas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JadwalProgramExport
{
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    private const KOLOM_HARI = ['B', 'C', 'D', 'E', 'F', 'G', 'H'];

    private const WARNA_JUDUL = '1F4E79';
    private const WARNA_BANNER = '2F5597';
    private const WARNA_HEADER = '264478';
    private const WARNA_JAM = 'B8CCE4';
    private const WARNA_JUMLAH = 'E2EFDA';
    private const TEKS_JUMLAH = '375623';
    private const WARNA_GARIS = 'BFBFBF';

    public function build(Kelas $kelas): Spreadsheet
    {
        $kelas->loadMissing('cabang');

        $jadwal = Jadwal::query()
            ->with('guru')
            ->where('kelas_id', $kelas->getKey())
            ->where('is_active', true)
            ->get();

        $peserta = $this->pesertaPerJadwal($jadwal->pluck('id')->all());

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        $sheet->getColumnDimension('A')->setWidth(18);
        foreach (self::KOLOM_HARI as $kolom) {
            $sheet->getColumnDimension($kolom)->setWidth(22);
        }

        // Judul dan informasi cabang / program
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Jadwal Pembelajaran Siswa — ' . ($kelas->cabang?->nama_cabang ?? '-'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::WARNA_JUDUL);

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValueExplicit('A2', 'Program: ' . $kelas->nama_kelas, DataType::TYPE_STRING);
        $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setRGB(self::WARNA_JUDUL);

        $row = 4;

        // Satu tabel per guru, berurutan ke bawah
        $perGuru = $jadwal
            ->groupBy('guru_id')
            ->sortBy(fn (Collection $grup) => mb_strtolower($grup->first()->guru?->nama ?? ''));

        foreach ($perGuru as $jadwalGuru) {
            $row = $this->tabelGuru($sheet, $row, $jadwalGuru->first()->guru?->nama ?? '-', $jadwalGuru, $peserta);
            $row++; // jarak antar guru
        }

        return $spreadsheet;
    }

    /** Menulis satu tabel guru mulai dari $row. Mengembalikan nomor baris kosong berikutnya. */
    private function tabelGuru(Worksheet $sheet, int $row, string $namaGuru, Collection $jadwalGuru, Collection $peserta): int
    {
        $awalTabel = $row;

        // Banner nama guru
        $sheet->mergeCells("B{$row}:H{$row}");
        $sheet->setCellValueExplicit("B{$row}", 'Guru: ' . $namaGuru, DataType::TYPE_STRING);
        $this->isi($sheet, "A{$row}:H{$row}", self::WARNA_BANNER, 'FFFFFF', true);
        $row++;

        // Header: JAM dan nama hari
        $sheet->setCellValue("A{$row}", 'JAM');
        foreach (self::HARI as $i => $hari) {
            $sheet->setCellValue(self::KOLOM_HARI[$i] . $row, strtoupper($hari));
        }
        $this->isi($sheet, "A{$row}:H{$row}", self::WARNA_HEADER, 'FFFFFF', true);
        $row++;

        // Jam pelajaran guru ini, berurutan dari yang paling pagi
        $perJam = $jadwalGuru
            ->groupBy(fn (Jadwal $j) => $j->jam_mulai . '|' . $j->jam_selesai)
            ->sortKeys();

        foreach ($perJam as $jadwalJam) {
            $contoh = $jadwalJam->first();
            $perHari = $jadwalJam->groupBy('hari');

            // Nama siswa per hari; null = tidak ada jadwal di hari itu pada jam ini
            $namaPerHari = [];

            foreach (self::HARI as $hari) {
                $namaPerHari[$hari] = $perHari->has($hari)
                    ? $perHari->get($hari)
                        ->flatMap(fn (Jadwal $j) => $peserta->get($j->id, []))
                        ->unique()
                        ->sortBy(fn ($nama) => mb_strtolower($nama))
                        ->values()
                        ->all()
                    : null;
            }

            $tinggi = max(1, collect($namaPerHari)->map(fn ($nama) => is_array($nama) ? count($nama) : 0)->max());
            $awalJam = $row;
            $akhirJam = $row + $tinggi - 1;

            // Kolom JAM: digabung vertikal sepanjang daftar siswa
            $sheet->setCellValue(
                "A{$awalJam}",
                Carbon::parse($contoh->jam_mulai)->format('H.i') . ' – ' . Carbon::parse($contoh->jam_selesai)->format('H.i'),
            );

            if ($tinggi > 1) {
                $sheet->mergeCells("A{$awalJam}:A{$akhirJam}");
            }

            $this->isi($sheet, "A{$awalJam}:A{$akhirJam}", self::WARNA_JAM, '000000', true);

            // Siswa per hari
            foreach (self::HARI as $i => $hari) {
                foreach (($namaPerHari[$hari] ?? []) as $urut => $nama) {
                    $sheet->setCellValueExplicit(self::KOLOM_HARI[$i] . ($awalJam + $urut), $nama, DataType::TYPE_STRING);
                }
            }

            $sheet->getStyle("B{$awalJam}:H{$akhirJam}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $row = $akhirJam + 1;

            // Baris jumlah siswa
            $sheet->setCellValue("A{$row}", 'Jumlah Siswa');
            foreach (self::HARI as $i => $hari) {
                if ($namaPerHari[$hari] !== null) {
                    $sheet->setCellValue(self::KOLOM_HARI[$i] . $row, count($namaPerHari[$hari]));
                }
            }

            $this->isi($sheet, "A{$row}:H{$row}", self::WARNA_JUMLAH, self::TEKS_JUMLAH, true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $row++;
        }

        // Garis tipis di seluruh tabel guru
        $sheet->getStyle("A{$awalTabel}:H" . ($row - 1))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB(self::WARNA_GARIS);

        return $row;
    }

    /** Siswa aktif per jadwal: [jadwal_id => [nama, ...]], berurutan nama. */
    private function pesertaPerJadwal(array $jadwalIds): Collection
    {
        if ($jadwalIds === []) {
            return collect();
        }

        return DB::table('jadwal_siswa as js')
            ->join('kelas_siswa as ks', 'ks.id', '=', 'js.kelas_siswa_id')
            ->join('siswa as s', 's.id', '=', 'js.siswa_id')
            ->whereIn('js.jadwal_id', $jadwalIds)
            ->where('ks.is_active', true)
            ->whereNull('s.deleted_at')
            ->orderBy('s.nama')
            ->get(['js.jadwal_id', 's.nama'])
            ->groupBy('jadwal_id')
            ->map(fn (Collection $baris) => $baris->pluck('nama')->all());
    }

    private function isi(Worksheet $sheet, string $range, string $latar, string $teks, bool $tebal): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => $tebal, 'color' => ['rgb' => $teks]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $latar]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
}