<x-filament-panels::page>
    <div class="flex gap-4 border-b border-gray-200 dark:border-gray-700 mb-4">
        <button
            type="button"
            wire:click="$set('activeTab', 'presensi')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'presensi' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            Presensi
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'hasil')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'hasil' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            Hasil Presensi
        </button>
    </div>

    @if($activeTab === 'presensi')
        @php $jadwalList = $this->getJadwalHariIni(); @endphp

        @if($jadwalList->isEmpty())
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow text-center text-gray-500">
                Tidak ada jadwal mengajar untuk hari ini.
            </div>
        @else
            <div class="space-y-4">
                @foreach($jadwalList as $jadwal)
                    @php $presensi = $jadwal->presensiHariIni; @endphp

                    <div
                        x-data="presensiJadwal({{ $jadwal->id }})"
                        x-init="init()"
                        class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold">{{ $jadwal->kelas->nama_kelas }}</h3>
                                <p class="text-sm text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($jadwal->jam_mulai)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($jadwal->jam_selesai)->format('H:i') }}
                                    · {{ $jadwal->cabang->nama_cabang }}
                                </p>
                            </div>

                            @if($presensi?->status_masuk)
                                <span class="text-xs px-2 py-0.5 rounded {{ $presensi->status_masuk === 'terlambat' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $presensi->status_masuk === 'terlambat' ? 'Terlambat' : 'Hadir' }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-2 text-sm text-gray-500">
                            Check In: {{ $presensi?->check_in?->format('H:i') ?? '-' }}
                            · Check Out: {{ $presensi?->check_out?->format('H:i') ?? '-' }}
                        </div>

                        <p class="text-sm text-amber-600 mt-2" x-show="!coords.lat && !locationError">Menunggu lokasi GPS...</p>
                        <p class="text-sm text-red-600 mt-2" x-show="locationError" x-text="locationError"></p>

                        <div class="mt-3 flex gap-2">
                            @if(! $presensi?->check_in)
                                <x-filament::button color="success" x-show="coords.lat" @click="submit('checkIn')">
                                    Check In
                                </x-filament::button>
                            @elseif(! $presensi?->check_out)
                                <x-filament::button color="danger" x-show="coords.lat" @click="submit('checkOut')">
                                    Check Out
                                </x-filament::button>
                            @else
                                <span class="text-sm text-gray-400">Sesi ini sudah selesai dipresensi.</span>
                            @endif

                            <x-filament::button color="gray" outlined x-show="!coords.lat" @click="ambilLokasi()">
                                Coba Lagi Ambil Lokasi
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @script
        <script>
            Alpine.data('presensiJadwal', (jadwalId) => ({
                jadwalId,
                coords: { lat: null, lng: null, accuracy: null },
                locationError: null,

                init() {
                    this.ambilLokasi();
                },

                ambilLokasi() {
                    if (!navigator.geolocation) {
                        this.locationError = 'Perangkat tidak mendukung GPS.';
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.coords = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
                            this.locationError = null;
                        },
                        () => { this.locationError = 'Izin lokasi ditolak.'; },
                        { enableHighAccuracy: true, timeout: 10000 },
                    );
                },

                submit(method) {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const lat = pos.coords.latitude;
                            const lng = pos.coords.longitude;
                            const accuracy = Math.round(pos.coords.accuracy);

                            $wire.call(method, this.jadwalId, lat, lng, accuracy).then(() => {
                                window.location.reload();
                            });
                        },
                        () => { this.locationError = 'Gagal mengambil lokasi terbaru. Coba lagi.'; },
                        { enableHighAccuracy: true, timeout: 10000 },
                    );
                },
            }));
        </script>
        @endscript
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>