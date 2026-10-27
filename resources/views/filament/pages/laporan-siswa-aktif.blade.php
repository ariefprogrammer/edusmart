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

    <x-filament::section
        heading="Pergerakan bulan ini"
        :description="'Dihitung dari posisi ' . $tgl($laporan['awal']) . ' sampai ' . $tgl($laporan['akhir'])"
    >
        <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 24px;">
            <div>
                <div style="font-size: 12px; opacity: .7;">Aktif {{ $tgl($laporan['awal'], 'd M Y') }}</div>
                <div style="font-size: 24px; font-weight: 700;">{{ $r['aktif_awal'] }}</div>
            </div>
            <div>
                <div style="font-size: 12px; opacity: .7;">+ Baru bergabung</div>
                <div style="font-size: 24px; font-weight: 700; color: #16a34a;">{{ $r['baru'] }}</div>
            </div>
            <div>
                <div style="font-size: 12px; opacity: .7;">+ Kembali aktif</div>
                <div style="font-size: 24px; font-weight: 700; color: #2563eb;">{{ $r['kembali'] }}</div>
            </div>
            <div>
                <div style="font-size: 12px; opacity: .7;">− Berhenti</div>
                <div style="font-size: 24px; font-weight: 700; color: #dc2626;">{{ $r['berhenti'] }}</div>
            </div>
            <div>
                <div style="font-size: 12px; opacity: .7;">= Aktif {{ $tgl($laporan['akhir'], 'd M Y') }}</div>
                <div style="font-size: 24px; font-weight: 700;">{{ $r['aktif'] }}</div>
            </div>
        </div>

        @if($r['masuk_keluar'] > 0)
            <p style="font-size: 13px; opacity: .7; margin: 16px 0 0;">
                {{ $r['masuk_keluar'] }} siswa bergabung lalu berhenti dalam bulan yang sama, sehingga tidak muncul di hitungan di atas dan masuk ke siswa tidak aktif.
            </p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Detail siswa">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <select wire:model.live="filterStatus" style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(128,128,128,.4); background: transparent; color: inherit;">
                <option value="semua">Semua status</option>
                <option value="aktif">Aktif</option>
                <option value="tidak_aktif">Tidak aktif</option>
            </select>

            <input
                type="search"
                wire:model.live.debounce.400ms="cari"
                placeholder="Cari nama siswa..."
                style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(128,128,128,.4); background: transparent; color: inherit; min-width: 220px;"
            >

            <span style="align-self: center; font-size: 13px; opacity: .7;">
                Menampilkan {{ $this->siswaTampil->count() }} siswa
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(128,128,128,.3);">
                        <th style="padding: 8px;">Nama siswa</th>
                        <th style="padding: 8px;">Status</th>
                        <th style="padding: 8px;">Pergerakan</th>
                        <th style="padding: 8px;">Program</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->siswaTampil as $siswa)
                        <tr style="border-bottom: 1px solid rgba(128,128,128,.2); vertical-align: top;">
                            <td style="padding: 8px; font-weight: 600;">{{ $siswa['nama'] }}</td>
                            <td style="padding: 8px;">
                                @if($siswa['status'] === 'aktif')
                                    <span style="padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: rgba(34,197,94,.15); color: #16a34a;">Aktif</span>
                                @else
                                    <span style="padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: rgba(239,68,68,.15); color: #dc2626;">Tidak aktif</span>
                                @endif
                            </td>
                            <td style="padding: 8px; font-size: 13px;">
                                {{ $labelGerak[$siswa['gerak']] ?? '-' }}
                            </td>
                            <td style="padding: 8px;">
                                @foreach($siswa['program'] as $program)
                                    <div style="{{ $program['aktif'] ? '' : 'opacity: .6;' }}">
                                        {{ $program['nama'] }}
                                        <span style="font-size: 12px; opacity: .8;">
                                            — {{ $program['aktif'] ? 'aktif sejak' : 'nonaktif sejak' }} {{ $tgl($program['sejak'], 'd M Y') }}
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 24px; text-align: center; opacity: .6;">
                                Tidak ada siswa yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>