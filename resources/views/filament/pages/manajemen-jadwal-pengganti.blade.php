<x-filament-panels::page>
    @php
        $j = $jadwalPengganti;
        $siswaList = $this->getSiswaList();
        $bisaInput = $this->bisaInput();
        $statusTampil = $j->statusTampil();
        $warnaStatus = match ($statusTampil) {
            'selesai' => 'success',
            'dibatalkan' => 'gray',
            'terlewat' => 'danger',
            default => 'warning',
        };
    @endphp

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
        <div style="display: flex; align-items: center; gap: .75rem;">
            <x-kelas-icon :kelas="$j->kelas" :ukuran="3" />
            <div>
                <h2 class="text-xl font-semibold">{{ $j->kelas->nama_kelas }}</h2>
                <p class="text-sm text-gray-500">{{ $j->cabang->nama_cabang }} · Guru: {{ $j->guru?->nama ?? '-' }}</p>
            </div>
        </div>
            <p class="text-sm text-gray-500">
                Pengganti sesi {{ $j->tanggal_seharusnya->translatedFormat('d F Y') }},
                dilaksanakan {{ $j->tanggal_pengganti->translatedFormat('d F Y') }}
                pukul {{ substr($j->jam_mulai, 0, 5) }}–{{ substr($j->jam_selesai, 0, 5) }}
            </p>
        </div>
        <x-filament::badge :color="$warnaStatus">{{ ucfirst($statusTampil) }}</x-filament::badge>
    </div>

    <div class="flex gap-4 border-b border-gray-200 dark:border-gray-700">
        <button type="button" wire:click="$set('activeTab', 'presensi')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'presensi' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Presensi Siswa
        </button>
        <button type="button" wire:click="$set('activeTab', 'progress')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'progress' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Progress Siswa
        </button>
    </div>

    @if(! $isGuruPengampu)
        <div class="text-xs px-3 py-2 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
            Mode lihat saja — hanya guru yang ditugaskan pada jadwal pengganti ini yang bisa mengubah data.
        </div>
    @elseif(! $this->masihTerjadwal())
        <div class="text-xs px-3 py-2 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
            Jadwal pengganti ini berstatus {{ $j->status }}; data tidak dapat diubah.
        </div>
    @elseif(! $this->isTanggalPengganti())
        <div class="text-xs px-3 py-2 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
            Mode lihat saja — data hanya dapat diinput pada tanggal pengganti ({{ $j->tanggal_pengganti->translatedFormat('d F Y') }}).
        </div>
    @endif

    @if($activeTab === 'presensi')
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            @if($siswaList->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada siswa untuk ditampilkan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">No</th>
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">Nama Siswa</th>
                                <th colspan="4" class="text-center py-2 px-2 border-b dark:border-gray-700">Presensi</th>
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">Keterangan</th>
                            </tr>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Hadir</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Izin</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Sakit</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Alpa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                <tr wire:key="presensi-row-{{ $siswa->id }}" class="border-b dark:border-gray-700 last:border-b-0">
                                    <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">{{ $siswa->nama }}</td>

                                    @foreach(['hadir', 'izin', 'sakit', 'alpa'] as $value)
                                        <td class="text-center py-2 px-2">
                                            <input
                                                type="radio"
                                                name="presensi_status_{{ $siswa->id }}"
                                                wire:model="presensiData.{{ $siswa->id }}.status"
                                                value="{{ $value }}"
                                                @disabled(! $bisaInput)
                                                class="appearance-none w-4 h-4 rounded-sm border-2 border-gray-300 dark:border-gray-600
                                                    checked:bg-primary-600 checked:border-primary-600 relative cursor-pointer
                                                    before:content-['✓'] before:hidden checked:before:flex before:absolute before:inset-0
                                                    before:items-center before:justify-center before:text-white before:text-[10px] before:leading-none
                                                    focus:ring-primary-500 focus:ring-offset-0 disabled:opacity-60 disabled:cursor-not-allowed"
                                            >
                                        </td>
                                    @endforeach

                                    <td class="py-2 px-2">
                                        <input
                                            type="text"
                                            wire:model="presensiData.{{ $siswa->id }}.keterangan"
                                            placeholder="Catatan (opsional)"
                                            @disabled(! $bisaInput)
                                            class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 disabled:opacity-60"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($bisaInput)
                    <div class="mt-6">
                        <x-filament::button wire:click="submitPresensi">
                            Submit Presensi
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            @if($siswaList->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada siswa untuk ditampilkan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 px-2">No</th>
                                <th class="text-left py-2 px-2">Nama Siswa</th>
                                <th class="text-left py-2 px-2">Catatan Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                <tr wire:key="progress-row-{{ $siswa->id }}" class="border-b dark:border-gray-700 last:border-b-0">
                                    <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">{{ $siswa->nama }}</td>
                                    <td class="py-2 px-2">
                                        <textarea
                                            wire:model="progressData.{{ $siswa->id }}"
                                            rows="2"
                                            placeholder="Tulis catatan progress belajar siswa..."
                                            @disabled(! $bisaInput)
                                            class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 disabled:opacity-60"
                                        ></textarea>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($bisaInput)
                    <div class="mt-6">
                        <x-filament::button wire:click="submitProgress">
                            Submit Progress
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if($this->bisaTandaiSelesai())
        <div class="flex justify-end">
            <x-filament::button
                color="success"
                wire:click="tandaiSelesai"
                wire:confirm="Tandai jadwal pengganti ini selesai? Setelah selesai, data tidak dapat diubah lagi."
            >
                Tandai Selesai
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>