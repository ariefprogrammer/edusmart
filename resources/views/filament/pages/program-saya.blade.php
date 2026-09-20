<x-filament-panels::page>
    @php $programs = $this->getProgramList(); @endphp

    @if($programs->isEmpty())
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow text-center text-gray-500">
            Kamu belum ditugaskan ke program/kelas manapun.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($programs as $kelas)
                <a
                    href="{{ \App\Filament\Pages\ManajemenProgram::getUrl(['kelas' => $kelas->id]) }}"
                    class="block p-4 bg-white dark:bg-gray-800 rounded-xl shadow hover:shadow-md transition"
                >
                    <h3 class="font-semibold text-lg">{{ $kelas->nama_kelas }}</h3>
                    <p class="text-sm text-gray-500">{{ $kelas->cabang->nama_cabang }}</p>

                    <div class="mt-2 space-y-1">
                        @foreach($kelas->jadwal as $j)
                            <p class="text-xs text-gray-500">
                                {{ $j->hari }}, {{ \Illuminate\Support\Carbon::parse($j->jam_mulai)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($j->jam_selesai)->format('H:i') }}
                            </p>
                        @endforeach
                    </div>

                    <span class="inline-block mt-3 text-xs px-2 py-0.5 rounded bg-primary-100 text-primary-700">
                        {{ $kelas->siswa_aktif_count }} siswa aktif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>