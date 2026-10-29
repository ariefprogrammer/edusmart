<x-filament-panels::page>
    <style>
        .program-card {
            display: flex;
            flex-direction: column;
        }

        .program-card-link {
            display: block;
            flex: 1 1 auto;
            padding: 1rem 1rem .5rem;
            color: inherit;
            text-decoration: none;
        }

        .program-card-footer {
            margin-top: auto;
            padding: 0 1rem 1rem;
        }

        .program-card--disabled {
            opacity: .6;
            outline: 1px dashed rgb(var(--danger-500));
            outline-offset: -1px;
        }

        .program-card--disabled .program-card-link {
            cursor: not-allowed;
            user-select: none;
        }

        .program-card--over {
            outline: 1px solid rgb(var(--warning-500));
            outline-offset: -1px;
        }

        .program-warning {
            margin-top: .75rem;
            padding: .5rem .625rem;
            border-radius: .5rem;
            font-size: .75rem;
            line-height: 1.3;
        }

        .program-warning--danger {
            background: rgb(var(--danger-50));
            color: rgb(var(--danger-700));
        }

        .program-warning--warning {
            background: rgb(var(--warning-50));
            color: rgb(var(--warning-700));
        }

        .dark .program-warning--danger {
            background: rgba(var(--danger-500), .15);
            color: rgb(var(--danger-400));
        }

        .dark .program-warning--warning {
            background: rgba(var(--warning-500), .15);
            color: rgb(var(--warning-400));
        }

        .program-toggle {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .25rem 0;
            border: 0;
            background: none;
            font-size: .75rem;
            font-weight: 500;
            color: rgb(var(--primary-600));
            cursor: pointer;
        }

        .program-toggle:hover {
            text-decoration: underline;
        }

        .dark .program-toggle {
            color: rgb(var(--primary-400));
        }

        .program-toggle-icon {
            width: 1rem;
            height: 1rem;
            transition: transform .2s ease;
        }

        .program-jadwal {
            margin-top: .5rem;
            padding-top: .5rem;
            border-top: 1px solid rgb(var(--gray-200));
        }

        .dark .program-jadwal {
            border-top-color: rgb(var(--gray-700));
        }
    </style>

    @php
        $programs = $this->getProgramList();
        $penggantiList = $this->getJadwalPenggantiList();
    @endphp

        @if($programs->isEmpty() && $penggantiList->isEmpty())
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow text-center text-gray-500">
            Kamu belum ditugaskan ke program/kelas manapun.
        </div>
    @else
        @if($programs->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($programs as $kelas)
                    @php
                        $statusKuota = $kelas->statusKuota();
                        $kuotaKurang = $statusKuota === 'kurang';
                        $kuotaLebih = $statusKuota === 'lebih';
                    @endphp

                    <div
                        x-data="{ open: false }"
                        @class([
                            'program-card bg-white dark:bg-gray-800 rounded-xl shadow transition',
                            'hover:shadow-md' => ! $kuotaKurang,
                            'program-card--disabled' => $kuotaKurang,
                            'program-card--over' => $kuotaLebih,
                        ])
                    >
                        {{-- Area yang bisa diklik: membuka program --}}
                        <a
                            @unless($kuotaKurang)
                                href="{{ \App\Filament\Pages\ManajemenProgram::getUrl(['kelas' => $kelas->id]) }}"
                            @endunless
                            @if($kuotaKurang)
                                aria-disabled="true"
                                tabindex="-1"
                            @endif
                            class="program-card-link"
                        >
                            <h3 class="font-semibold text-lg">{{ $kelas->nama_kelas }}</h3>
                            <p class="text-sm text-gray-500">{{ $kelas->cabang->nama_cabang }}</p>

                            <span class="inline-block mt-3 text-xs px-2 py-0.5 rounded bg-primary-100 text-primary-700">
                                {{ $kelas->siswa_aktif_count }} siswa aktif
                            </span>

                            @if($kelas->kuota_minimal > 0 || $kelas->kuota_maksimal !== null)
                                <p class="mt-2 text-xs text-gray-500">
                                    Kuota:
                                    @if($kelas->kuota_minimal > 0)minimal {{ $kelas->kuota_minimal }}@endif
                                    @if($kelas->kuota_minimal > 0 && $kelas->kuota_maksimal !== null) · @endif
                                    @if($kelas->kuota_maksimal !== null)maksimal {{ $kelas->kuota_maksimal }}@endif
                                </p>
                            @endif

                            @if($kuotaKurang)
                                <div class="program-warning program-warning--danger">
                                    <strong>Kuota tidak memenuhi.</strong>
                                    Baru {{ $kelas->siswa_aktif_count }} siswa aktif dari minimal {{ $kelas->kuota_minimal }}.
                                    Program tidak dapat dibuka sampai kuota terpenuhi.
                                </div>
                            @elseif($kuotaLebih)
                                <div class="program-warning program-warning--warning">
                                    <strong>Kuota melebihi batas.</strong>
                                    {{ $kelas->siswa_aktif_count }} siswa aktif, batas maksimal {{ $kelas->kuota_maksimal }}.
                                    Hubungi admin pusat.
                                </div>
                            @endif
                        </a>

                        {{-- Area jadwal: disembunyikan secara default --}}
                        <div class="program-card-footer">
                            <button
                                type="button"
                                class="program-toggle"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open.toString()"
                                aria-controls="jadwal-{{ $kelas->id }}"
                            >
                                <span x-text="open ? 'Sembunyikan jadwal' : 'Tampilkan jadwal'"></span>
                                <span>({{ $kelas->jadwal->count() }})</span>
                                <x-filament::icon
                                    icon="heroicon-m-chevron-down"
                                    class="program-toggle-icon"
                                    x-bind:style="open ? 'transform: rotate(180deg)' : ''"
                                />
                            </button>

                            <div id="jadwal-{{ $kelas->id }}" x-show="open" x-collapse x-cloak>
                                <div class="program-jadwal space-y-1">
                                    @foreach($kelas->jadwal as $j)
                                        <p class="text-xs text-gray-500">
                                            {{ $j->hari }}, {{ \Illuminate\Support\Carbon::parse($j->jam_mulai)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($j->jam_selesai)->format('H:i') }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($penggantiList->isNotEmpty())
            <div>
                <h3 class="text-base font-semibold mb-1">Jadwal Pengganti</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Input presensi dan progress hanya dapat dilakukan pada tanggal pengganti.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($penggantiList as $jp)
                        <div class="program-card bg-white dark:bg-gray-800 rounded-xl shadow transition hover:shadow-md">
                            <a
                                href="{{ \App\Filament\Pages\ManajemenJadwalPengganti::getUrl(['jadwalPengganti' => $jp->id]) }}"
                                class="program-card-link"
                            >
                                <h3 class="font-semibold text-lg">{{ $jp->kelas?->nama_kelas ?? '-' }}</h3>
                                <p class="text-sm text-gray-500">{{ $jp->cabang?->nama_cabang }}</p>

                                <p class="mt-3 text-sm">
                                    {{ $jp->tanggal_pengganti->translatedFormat('l, d F Y') }}
                                    · {{ substr($jp->jam_mulai, 0, 5) }}–{{ substr($jp->jam_selesai, 0, 5) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Menggantikan sesi {{ $jp->tanggal_seharusnya->translatedFormat('d F Y') }}
                                    · {{ $jp->siswa_count }} siswa terdaftar
                                </p>

                                @if($jp->tanggal_pengganti->isToday())
                                    <span class="inline-block mt-3 text-xs px-2 py-0.5 rounded bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                        Hari ini
                                    </span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>