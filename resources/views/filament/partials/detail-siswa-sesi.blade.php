@php
    $pivot = $siswa->pivot;
    $siswaId = (int) $siswa->getKey();
    $warnaStatus = match ($siswa->status) {
        'aktif' => 'success',
        'cuti' => 'warning',
        default => 'danger',
    };

    // Gaya inline: tata letak tidak bergantung pada class Tailwind yang mungkin tidak ter-compile di panel
    $gayaBaris = 'display:grid; grid-template-columns: 13rem 1fr; column-gap:1rem; align-items:center;';
@endphp

<div style="display:flex; flex-direction:column; gap:1.5rem;">

    <div style="display:flex; flex-direction:column; gap:1rem; font-size:0.9375rem;">
        @if ($labelJam)
            <div style="{{ $gayaBaris }}">
                <span style="opacity:0.65;">Jam</span>
                <span style="font-weight:600;">{{ $labelJam }}</span>
            </div>
        @endif

        <div style="{{ $gayaBaris }}">
            <span style="opacity:0.65;">Status</span>
            <span>
                <x-filament::badge :color="$warnaStatus">{{ ucfirst($siswa->status) }}</x-filament::badge>
            </span>
        </div>

        <div style="{{ $gayaBaris }}">
            <span style="opacity:0.65;">Aktif di Program</span>
            <span>
                <x-filament::badge :color="$pivot->is_active ? 'success' : 'gray'">
                    {{ $pivot->is_active ? 'Aktif' : 'Nonaktif' }}
                </x-filament::badge>
            </span>
        </div>

        <div style="{{ $gayaBaris }}">
            <span style="opacity:0.65;">Tanggal Bergabung</span>
            <span style="font-weight:500;">
                {{ $pivot->tanggal_gabung ? \Carbon\Carbon::parse($pivot->tanggal_gabung)->translatedFormat('d M Y') : '-' }}
            </span>
        </div>
    </div>

    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; padding-top:1.25rem; border-top:1px solid rgba(128,128,128,0.25);">
        <x-filament::button size="sm" color="gray"
            wire:click="replaceMountedTableAction('crmSiswa', null, { siswa: {{ $siswaId }} })">
            Update / CRM
        </x-filament::button>

        <x-filament::button size="sm" color="gray"
            wire:click="replaceMountedTableAction('riwayatSiswa', null, { siswa: {{ $siswaId }} })">
            Riwayat
        </x-filament::button>

        @if ($bisaPindah)
            <x-filament::button size="sm" color="gray"
                wire:click="replaceMountedTableAction('pindahSesiSiswa', null, { siswa: {{ $siswaId }}, jadwal: {{ (int) $jadwalId }} })">
                Pindah Sesi
            </x-filament::button>
        @endif

        <x-filament::button size="sm" color="danger"
            wire:click="replaceMountedTableAction('keluarkanSiswa', null, { siswa: {{ $siswaId }}, jadwal: {{ (int) $jadwalId }} })">
            {{ $jadwalId ? 'Keluarkan dari Jam Ini' : 'Keluarkan dari Program' }}
        </x-filament::button>
    </div>
</div>