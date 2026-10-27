<x-filament-panels::page>
    {{ $this->form }}

    @php
        $laporan = $this->laporan;
        $r = $laporan['ringkasan'];
        $tgl = fn (string $t, string $format = 'd F Y') => \Carbon\Carbon::parse($t)->locale('id')->translatedFormat($format);
        $labelGerak = [
            'baru' => 'Baru bergabung',
            'kembali' => 'Kembali aktif',
            'berhenti' => 'Berhenti bulan ini',
            'masuk_keluar' => 'Bergabung & berhenti bulan ini',
        ];
    @endphp

    <p style="font-size: 14px; opacity: .7; margin: 0;">
        Posisi per <strong>{{ $tgl($laporan['akhir']) }}</strong>
        @if($laporan['bulan_berjalan']) (bulan berjalan, sampai hari ini) @else (akhir bulan) @endif
        · Berdasarkan keikutsertaan program
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <x-filament::section>
            <div style="font-size: 13px; opacity: .7;">Siswa aktif</div>
            <div style="font-size: 32px; font-weight: 700; color: #16a34a;">{{ $r['aktif'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 13px; opacity: .7;">Siswa tidak aktif</div>
            <div style="font-size: 32px; font-weight: 700; color: #dc2626;">{{ $r['tidak_aktif'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size: 13px; opacity: .7;">Total terdaftar</div>
            <div style="font-size: 32px; font-weight: 700;">{{ $r['total'] }}</div>
        </x-filament::section>
    </div>

    @php
        $kartuGerak = [
            [
                'tanda' => '',
                'label' => 'Aktif ' . $tgl($laporan['awal'], 'd M Y'),
                'nilai' => $r['aktif_awal'],
                'warna' => '#6b7280',
                'ket' => 'Posisi akhir bulan lalu',
            ],
            [
                'tanda' => '+',
                'label' => 'Baru bergabung',
                'nilai' => $r['baru'],
                'warna' => '#16a34a',
                'ket' => 'Pertama kali aktif bulan ini',
            ],
            [
                'tanda' => '+',
                'label' => 'Kembali aktif',
                'nilai' => $r['kembali'],
                'warna' => '#2563eb',
                'ket' => 'Aktif lagi setelah sempat nonaktif',
            ],
            [
                'tanda' => '−',
                'label' => 'Berhenti',
                'nilai' => $r['berhenti'],
                'warna' => '#dc2626',
                'ket' => 'Aktif bulan lalu, kini tidak aktif',
            ],
            [
                'tanda' => '=',
                'label' => 'Aktif ' . $tgl($laporan['akhir'], 'd M Y'),
                'nilai' => $r['aktif'],
                'warna' => '#0d9488',
                'ket' => $laporan['bulan_berjalan'] ? 'Posisi hari ini' : 'Posisi akhir bulan',
                'sorot' => true,
            ],
        ];
    @endphp

    <x-filament::section
        heading="Pergerakan bulan ini"
        :description="'Dihitung dari posisi ' . $tgl($laporan['awal']) . ' sampai ' . $tgl($laporan['akhir'])"
    >
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px;">
            @foreach($kartuGerak as $k)
                @php
                    $sorot = ! empty($k['sorot']);
                    $latar = $sorot ? $k['warna'] . '14' : 'rgba(128,128,128,.06)';
                    $garis = $sorot ? $k['warna'] . '66' : 'rgba(128,128,128,.3)';
                @endphp

                <div style="position: relative; padding: 16px; border-radius: 12px; border: 1px solid {{ $garis }}; border-top: 4px solid {{ $k['warna'] }}; background: {{ $latar }};">
                    @if($k['tanda'] !== '')
                        <span style="position: absolute; top: 12px; right: 12px; width: 24px; height: 24px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; line-height: 1; background: {{ $k['warna'] }}22; color: {{ $k['warna'] }};">
                            {{ $k['tanda'] }}
                        </span>
                    @endif

                    <div style="font-size: 13px; font-weight: 500; opacity: .7; padding-right: 32px;">{{ $k['label'] }}</div>
                    <div style="font-size: 32px; font-weight: 700; line-height: 1.2; margin: 6px 0; color: {{ $k['warna'] }};">{{ $k['nilai'] }}</div>
                    <div style="font-size: 12px; opacity: .6;">{{ $k['ket'] }}</div>
                </div>
            @endforeach
        </div>

        @if($r['masuk_keluar'] > 0)
            <div style="margin-top: 16px; padding: 10px 14px; border-radius: 8px; border-left: 4px solid #d97706; background: rgba(217,119,6,.08); font-size: 13px;">
                {{ $r['masuk_keluar'] }} siswa bergabung lalu berhenti dalam bulan yang sama, sehingga tidak muncul di hitungan di atas dan masuk ke siswa tidak aktif.
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Detail siswa">
        <style>
            .tbl-siswa { width: 100%; min-width: 640px; border-collapse: separate; border-spacing: 0; font-size: 14px; }
            .tbl-siswa th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; background: rgba(128,128,128,.1); border-bottom: 1px solid rgba(128,128,128,.3); }
            .tbl-siswa th:first-child { border-top-left-radius: 10px; }
            .tbl-siswa th:last-child { border-top-right-radius: 10px; }
            .tbl-siswa td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid rgba(128,128,128,.15); }
            .tbl-siswa tbody tr:last-child td { border-bottom: none; }
            .tbl-siswa tbody tr:hover td { background: rgba(128,128,128,.06); }
            .tbl-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
            .tbl-dot { width: 7px; height: 7px; border-radius: 9999px; flex-shrink: 0; }
        </style>

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px;">
            <select wire:model.live="filterStatus" style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(128,128,128,.4); background: transparent; color: inherit;">
                <option value="semua">Semua status</option>
                <option value="aktif">Aktif</option>
                <option value="tidak_aktif">Tidak aktif</option>
            </select>

            <input
                type="search"
                wire:model.live.debounce.400ms="cari"
                placeholder="Cari nama siswa..."
                style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(128,128,128,.4); background: transparent; color: inherit; min-width: 240px;"
            >

            <span style="margin-left: auto; font-size: 15px; opacity: .7; margin-top: 5px;">
                Menampilkan {{ $this->siswaTampil->count() }} siswa
            </span>
        </div>

        <div style="overflow-x: auto; border: 1px solid rgba(128,128,128,.25); border-radius: 12px;">
            <table class="tbl-siswa">
                <thead>
                    <tr>
                        <th>Nama siswa</th>
                        <th>Status</th>
                        <th>Pergerakan</th>
                        <th>Program</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->siswaTampil as $siswa)
                        @php
                            $aktif = $siswa['status'] === 'aktif';
                            $warna = $warnaGerak[$siswa['gerak']] ?? null;
                        @endphp

                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="width: 34px; height: 34px; border-radius: 9999px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; background: rgba(13,148,136,.15); color: #0d9488;">
                                        {{ mb_strtoupper(mb_substr($siswa['nama'], 0, 1)) }}
                                    </span>
                                    <span style="font-weight: 600;">{{ $siswa['nama'] }}</span>
                                </div>
                            </td>

                            <td>
                                @if($aktif)
                                    <span class="tbl-badge" style="background: rgba(34,197,94,.15); color: #16a34a;">
                                        <span class="tbl-dot" style="background: #16a34a;"></span>Aktif
                                    </span>
                                @else
                                    <span class="tbl-badge" style="background: rgba(239,68,68,.15); color: #dc2626;">
                                        <span class="tbl-dot" style="background: #dc2626;"></span>Tidak aktif
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($warna)
                                    <span class="tbl-badge" style="background: {{ $warna }}1f; color: {{ $warna }};">
                                        {{ $labelGerak[$siswa['gerak']] }}
                                    </span>
                                @else
                                    <span style="opacity: .5;">-</span>
                                @endif
                            </td>

                            <td>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    @foreach($siswa['program'] as $program)
                                        <div style="display: flex; align-items: flex-start; gap: 8px; {{ $program['aktif'] ? '' : 'opacity: .6;' }}">
                                            <span class="tbl-dot" style="margin-top: 6px; background: {{ $program['aktif'] ? '#16a34a' : '#9ca3af' }};"></span>
                                            <div>
                                                <div style="font-weight: 500; line-height: 1.3;">{{ $program['nama'] }}</div>
                                                <div style="font-size: 12px; opacity: .7;">
                                                    {{ $program['aktif'] ? 'aktif sejak' : 'nonaktif sejak' }} {{ $tgl($program['sejak'], 'd M Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 32px; text-align: center; opacity: .6;">
                                Tidak ada siswa yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>