@php
    $pivot = $siswa->pivot;
    $siswaId = (int) $siswa->getKey();
    $warnaStatus = match ($siswa->status) {
        'aktif' => 'success',
        'cuti' => 'warning',
        default => 'danger',
    };
@endphp

<div class="space-y-5">
    <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
        @if ($labelJam)
            <dt class="text-gray-500 dark:text-gray-400">Jam</dt>
            <dd class="col-span-2 font-medium">{{ $labelJam }}</dd>
        @endif

        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
        <dd class="col-span-2">
            <x-filament::badge :color="$warnaStatus">{{ ucfirst($siswa->status) }}</x-filament::badge>
        </dd>

        <dt class="text-gray-500 dark:text-gray-400">Aktif di Program</dt>
        <dd class="col-span-2">
            <x-filament::badge :color="$pivot->is_active ? 'success' : 'gray'">
                {{ $pivot->is_active ? 'Aktif' : 'Nonaktif' }}
            </x-filament::badge>
        </dd>

        <dt class="text-gray-500 dark:text-gray-400">Tanggal Bergabung</dt>
        <dd class="col-span-2">
            {{ $pivot->tanggal_gabung ? \Carbon\Carbon::parse($pivot->tanggal_gabung)->translatedFormat('d M Y') : '-' }}
        </dd>
    </dl>

    <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-white/10">
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