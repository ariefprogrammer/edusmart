<?php

namespace App\Exports;

use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export data siswa mengikuti template "Formulir Pendaftaran Siswa Baru".
 * - 1 siswa   : layout formulir (label | ":" | isi), sheet "Formulir"
 * - banyak    : satu sheet "Data Siswa", field menjadi judul kolom, satu baris per siswa
 */
class SiswaFormulirExport
{
    /** Siswa harus sudah memuat relasi `keluarga` (gunakan with('keluarga')). */
    public static function download(Collection $siswaList): StreamedResponse
    {
        $siswaList->loadMissing('keluarga');

        $spreadsheet = (new self())->build($siswaList);

        $nama = $siswaList->count() === 1
            ? 'formulir-siswa-' . Str::slug($siswaList->first()->nama) . '.xlsx'
            : 'data-siswa-' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nama);
    }

    public function build(Collection $siswaList): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($siswaList->count() === 1) {
            $this->buildFormulir($sheet, $siswaList->first());
        } else {
            $this->buildTabel($sheet, $siswaList);
        }

        return $spreadsheet;
    }

    /** Urutan dan isi field sesuai template. */
    private function fields(): array
    {
        return [
            'Nama' => fn (Siswa $s) => $s->nama,
            'Jenis Kelamin' => fn (Siswa $s) => match ($s->jenis_kelamin) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => '',
            },
            'NISN' => fn (Siswa $s) => $s->nisn,
            'Kelas' => fn (Siswa $s) => $s->kelas,
            'Asal Sekolah' => fn (Siswa $s) => $s->asal_sekolah,
            'Tempat/Tanggal Lahir' => fn (Siswa $s) => $this->ttl($s->tempat_lahir, $s->tanggal_lahir),
            'Agama' => fn (Siswa $s) => $s->agama,
            'Kewarganegaraan' => fn (Siswa $s) => $s->kewarganegaraan,
            'Alamat' => fn (Siswa $s) => $this->alamat($s),
            'Kelurahan' => fn (Siswa $s) => $s->kelurahan,
            'Alat Transportasi' => fn (Siswa $s) => $s->alat_transportasi,
            'Sumber Informasi' => fn (Siswa $s) => $s->sumber_informasi,
            'Anak Ke' => fn (Siswa $s) => $s->anak_ke,
            'Media Sosial Siswa (jika ada)' => fn (Siswa $s) => $s->media_sosial,
            'No Tlp/Seluler' => fn (Siswa $s) => $s->telepon,

            'Nama Ayah' => fn (Siswa $s) => $s->keluarga?->nama_ayah,
            'NIK Ayah' => fn (Siswa $s) => $s->keluarga?->nik_ayah,
            'Tempat/Tgl Lahir Ayah' => fn (Siswa $s) => $this->ttl($s->keluarga?->tempat_lahir_ayah, $s->keluarga?->tanggal_lahir_ayah),
            'Pendidikan Terakhir Ayah' => fn (Siswa $s) => $s->keluarga?->pendidikan_ayah,
            'Pekerjaan Ayah' => fn (Siswa $s) => $s->keluarga?->pekerjaan_ayah,
            'Penghasilan Ayah' => fn (Siswa $s) => $s->keluarga?->penghasilan_ayah,
            'Media Sosial Ayah' => fn (Siswa $s) => $s->keluarga?->media_sosial_ayah,

            'Nama Ibu' => fn (Siswa $s) => $s->keluarga?->nama_ibu,
            'NIK Ibu' => fn (Siswa $s) => $s->keluarga?->nik_ibu,
            'Tempat/Tgl Lahir Ibu' => fn (Siswa $s) => $this->ttl($s->keluarga?->tempat_lahir_ibu, $s->keluarga?->tanggal_lahir_ibu),
            'Pendidikan Terakhir Ibu' => fn (Siswa $s) => $s->keluarga?->pendidikan_ibu,
            'Pekerjaan Ibu' => fn (Siswa $s) => $s->keluarga?->pekerjaan_ibu,
            'Penghasilan Ibu' => fn (Siswa $s) => $s->keluarga?->penghasilan_ibu,
            'Media Sosial Ibu' => fn (Siswa $s) => $s->keluarga?->media_sosial_ibu,

            'Nama Wali' => fn (Siswa $s) => $s->keluarga?->nama_wali,
            'NIK Wali' => fn (Siswa $s) => $s->keluarga?->nik_wali,
            'Tahun Lahir Wali' => fn (Siswa $s) => $s->keluarga?->tahun_lahir_wali,
            'Alamat Wali' => fn (Siswa $s) => $s->keluarga?->alamat_wali,
        ];
    }

    // ---------------------------------------------------------------- 1 siswa

    private function buildFormulir($sheet, Siswa $siswa): void
    {
        $sheet->setTitle('Formulir');
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(60);

        $row = 1;

        foreach ($this->fields() as $label => $ambil) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", ':');
            $sheet->setCellValueExplicit("C{$row}", $this->teks($ambil($siswa)), DataType::TYPE_STRING);
            $row++;
        }

        $akhir = $row - 1;
        $sheet->getStyle("A1:C{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("C1:C{$akhir}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
    }

    // ------------------------------------------------------------ banyak siswa

    private function buildTabel($sheet, Collection $siswaList): void
    {
        $sheet->setTitle('Data Siswa');
        $fields = $this->fields();

        $kolom = 1;
        foreach (array_keys($fields) as $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($kolom) . '1', $label);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($kolom))->setAutoSize(true);
            $kolom++;
        }

        $row = 2;
        foreach ($siswaList as $siswa) {
            $kolom = 1;
            foreach ($fields as $ambil) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($kolom) . $row,
                    $this->teks($ambil($siswa)),
                    DataType::TYPE_STRING
                );
                $kolom++;
            }
            $row++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($fields));
        $akhir = $row - 1;

        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}{$akhir}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('B2'); // judul kolom dan kolom Nama tetap terlihat
    }

    // ----------------------------------------------------------------- helper

    /** Semua isian ditulis sebagai teks agar NISN/NIK tidak berubah jadi notasi ilmiah. */
    private function teks(mixed $nilai): string
    {
        return trim((string) ($nilai ?? ''));
    }

    /** "Bandung, 05/05/2010"; bila salah satunya kosong, tampilkan yang ada saja. */
    private function ttl(?string $tempat, mixed $tanggal): string
    {
        $tgl = filled($tanggal) ? Carbon::parse($tanggal)->format('d/m/Y') : null;

        return collect([$tempat, $tgl])->filter(fn ($v) => filled($v))->implode(', ');
    }

    /** "Jl. Mawar No. 1, RT 01/RW 02, Kode Pos 40123" (bagian yang kosong dilewati). */
    private function alamat(Siswa $siswa): string
    {
        $rtRw = collect([
            filled($siswa->rt) ? 'RT ' . $siswa->rt : null,
            filled($siswa->rw) ? 'RW ' . $siswa->rw : null,
        ])->filter()->implode('/');

        return collect([
            $siswa->alamat,
            $rtRw,
            filled($siswa->kode_pos) ? 'Kode Pos ' . $siswa->kode_pos : null,
        ])->filter(fn ($v) => filled($v))->implode(', ');
    }
}