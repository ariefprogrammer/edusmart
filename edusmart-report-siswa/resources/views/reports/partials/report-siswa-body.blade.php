@php
    /** @var array $data  isi snapshot (ReportSiswa::$data) — TIDAK ada query di sini */
    $tgl = fn ($v, $fmt = 'd F Y') => $v ? \Carbon\Carbon::parse($v)->locale('id')->translatedFormat($fmt) : '-';
    $angka = function ($v, $desimal = 1) {
        if ($v === null) {
            return '-';
        }
        $s = number_format((float) $v, $desimal, ',', '.');

        return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
    };
    $bagian = $data['bagian'] ?? [];
    $siswa = $data['siswa'] ?? [];
    $cabang = $data['cabang'] ?? [];
@endphp

<div class="rs-kop">
    <div class="rs-cabang">{{ $cabang['nama'] ?? '-' }}</div>
    @if (! empty($cabang['alamat']))
        <div class="rs-alamat">{{ $cabang['alamat'] }}</div>
    @endif
</div>

<div class="rs-judul">LAPORAN PERKEMBANGAN SISWA</div>

<table class="rs-identitas">
    <tr>
        <td class="rs-label">Nama Siswa</td>
        <td>: {{ $siswa['nama'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="rs-label">Tanggal Lahir</td>
        <td>: {{ $tgl($siswa['tanggal_lahir'] ?? null) }}</td>
    </tr>
    <tr>
        <td class="rs-label">Wali Murid</td>
        <td>: {{ $siswa['wali'] ?? '-' }}</td>
    </tr>
    @if (! empty($data['periode']['bulan']))
        <tr>
            <td class="rs-label">Periode Bulan</td>
            <td>: {{ collect($data['periode']['bulan'])->pluck('nama')->implode(', ') }}</td>
        </tr>
    @endif
    @if (! empty($data['periode']['semester']))
        <tr>
            <td class="rs-label">Periode Semester</td>
            <td>: {{ collect($data['periode']['semester'])->pluck('nama')->implode(', ') }}</td>
        </tr>
    @endif
</table>

{{-- ============================================================ PRESENSI --}}
@if (in_array('presensi', $bagian, true))
    <div class="rs-section">
        <h2>Rekap Presensi</h2>

        @forelse ($data['presensi']['per_kelas'] ?? [] as $blok)
            <h3>Program: {{ $blok['kelas']['nama'] }}</h3>
            <table class="rs-tabel">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="rs-c">Hadir</th>
                        <th class="rs-c">Izin</th>
                        <th class="rs-c">Sakit</th>
                        <th class="rs-c">Alpa</th>
                        <th class="rs-c">Total</th>
                        <th class="rs-c">% Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($blok['rows'] as $row)
                        <tr>
                            <td>{{ $row['periode']['nama'] }}</td>
                            <td class="rs-c">{{ $row['hadir'] }}</td>
                            <td class="rs-c">{{ $row['izin'] }}</td>
                            <td class="rs-c">{{ $row['sakit'] }}</td>
                            <td class="rs-c">{{ $row['alpa'] }}</td>
                            <td class="rs-c">{{ $row['total'] }}</td>
                            <td class="rs-c">{{ $row['persen'] === null ? '-' : $angka($row['persen']).'%' }}</td>
                        </tr>
                    @endforeach
                    @if (count($blok['rows']) > 1)
                        <tr class="rs-total">
                            <td>Total</td>
                            <td class="rs-c">{{ $blok['total']['hadir'] }}</td>
                            <td class="rs-c">{{ $blok['total']['izin'] }}</td>
                            <td class="rs-c">{{ $blok['total']['sakit'] }}</td>
                            <td class="rs-c">{{ $blok['total']['alpa'] }}</td>
                            <td class="rs-c">{{ $blok['total']['total'] }}</td>
                            <td class="rs-c">{{ $blok['total']['persen'] === null ? '-' : $angka($blok['total']['persen']).'%' }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @empty
            <p class="rs-kosong">Tidak ada data presensi pada periode yang dipilih.</p>
        @endforelse

        @if (! empty($data['presensi']['per_kelas']))
            <div class="rs-catatan-kecil">% Kehadiran = Hadir ÷ jumlah pertemuan yang tercatat.</div>
        @endif
    </div>
@endif

{{-- ============================================================ PROGRESS --}}
@if (in_array('progress', $bagian, true))
    <div class="rs-section">
        <h2>Rekap Progress</h2>

        @foreach ($data['progress'] ?? [] as $blok)
            <h3>
                {{ $blok['periode']['nama'] }}
                <span class="rs-periode-range">({{ $tgl($blok['periode']['mulai'], 'd M Y') }} – {{ $tgl($blok['periode']['selesai'], 'd M Y') }})</span>
            </h3>

            @if (empty($blok['items']))
                <p class="rs-kosong">Tidak ada catatan progress pada periode ini.</p>
            @else
                <table class="rs-tabel">
                    <thead>
                        <tr>
                            <th style="width: 15%">Tanggal</th>
                            <th style="width: 20%">Program</th>
                            <th style="width: 17%">Guru</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blok['items'] as $item)
                            <tr>
                                <td>{{ $tgl($item['tanggal'], 'd M Y') }}</td>
                                <td>{{ $item['kelas'] }}</td>
                                <td>{{ $item['guru'] ?? '-' }}</td>
                                <td>{!! nl2br(e($item['catatan'])) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    </div>
@endif

{{-- ============================================================== NILAI --}}
@if (in_array('nilai', $bagian, true))
    <div class="rs-section">
        <h2>Rekap Nilai</h2>

        @forelse ($data['nilai']['per_kelas'] ?? [] as $blok)
            <h3>Program: {{ $blok['kelas']['nama'] }}</h3>
            <table class="rs-tabel">
                <thead>
                    <tr>
                        <th>Kategori Nilai</th>
                        @foreach ($blok['periode'] as $p)
                            <th class="rs-c">{{ $p['nama'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($blok['kategori'] as $kat)
                        <tr>
                            <td>{{ $kat['nama'] }}</td>
                            @foreach ($blok['periode'] as $p)
                                <td class="rs-c">{{ $angka($kat['nilai'][$p['id']] ?? null, 2) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="rs-total">
                        <td>Rata-rata</td>
                        @foreach ($blok['periode'] as $p)
                            <td class="rs-c">{{ $angka($blok['rata_rata'][$p['id']] ?? null, 2) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @empty
            <p class="rs-kosong">Tidak ada data nilai pada periode yang dipilih.</p>
        @endforelse
    </div>
@endif

@if (filled($report->catatan))
    <div class="rs-section">
        <h2>Catatan</h2>
        <p>{!! nl2br(e($report->catatan)) !!}</p>
    </div>
@endif

<div class="rs-footer">
    Dibuat pada {{ $tgl($data['dibuat']['pada'] ?? null, 'd F Y H:i') }}
    @if (! empty($data['dibuat']['oleh']['nama']))
        oleh {{ $data['dibuat']['oleh']['nama'] }}
    @endif
    . Data pada dokumen ini adalah snapshot pada saat report dibuat.
</div>
