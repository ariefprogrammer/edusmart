<x-filament-panels::page>
    @php $presensi = $this->getPresensiHariIni(); @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Kiri: Info Status Hari Ini -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 space-y-4">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Presensi Hari Ini</h2>
            
            <div class="flex items-center space-x-2 text-gray-600 dark:text-gray-300">
                <x-heroicon-o-calendar class="w-5 h-5 text-primary-500" />
                <p class="font-medium">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
            
            <hr class="border-gray-200 dark:border-gray-700">

            <div class="space-y-3">
                <!-- Check In -->
                <div class="flex justify-between items-center">
                    <span class="text-gray-500 dark:text-gray-400 font-medium">Jam Masuk (Check In)</span>
                    <div class="text-right">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $presensi?->check_in?->format('H:i') ?? '--:--' }}</span>
                        @if($presensi?->status_masuk)
                            <div class="mt-1">
                                <span class="text-xs px-2 py-1 rounded-md font-semibold {{ $presensi->status_masuk === 'terlambat' ? 'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' : 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' }}">
                                    {{ $presensi->status_masuk === 'terlambat' ? 'Terlambat' : 'Tepat Waktu' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Check Out -->
                <div class="flex justify-between items-center">
                    <span class="text-gray-500 dark:text-gray-400 font-medium">Jam Pulang (Check Out)</span>
                    <div class="text-right">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $presensi?->check_out?->format('H:i') ?? '--:--' }}</span>
                        @if($presensi?->status_keluar)
                            <div class="mt-1">
                                <span class="text-xs px-2 py-1 rounded-md font-semibold {{ $presensi->status_keluar === 'bolos' ? 'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' : 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' }}">
                                    {{ ucfirst(str_replace('_', ' ', $presensi->status_keluar)) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanan: Form Kamera & Aksi -->
        <div x-data="presensiKaryawan()" x-init="init()" class="p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col items-center space-y-4">
            
            <!-- Area Kamera -->
            <div class="relative w-full max-w-sm bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-700" style="aspect-ratio: 16 / 9; min-height: 200px;">
                <video x-ref="video" autoplay playsinline muted class="absolute inset-0 w-full h-full object-cover" x-show="cameraReady && !photo"></video>
                <img :src="photo" x-show="photo" class="absolute inset-0 w-full h-full object-cover" />
                <canvas x-ref="canvas" class="hidden"></canvas>
                
                <!-- Loading state -->
                <div x-show="!cameraReady && !locationError" class="text-gray-400 text-sm flex flex-col items-center">
                    <x-heroicon-o-camera class="w-8 h-8 mb-2 animate-pulse" />
                    Menyiapkan kamera...
                </div>
            </div>

            <!-- Pesan Info/Error GPS -->
            <p class="text-sm font-medium text-danger-600 text-center" x-show="locationError" x-text="locationError"></p>
            <p class="text-sm text-warning-600 text-center flex items-center gap-1" x-show="!coords.lat && !locationError">
                <x-filament::loading-indicator class="h-4 w-4" />
                Menunggu lokasi GPS...
            </p>
            <p class="text-xs text-gray-500 text-center" x-show="coords.lat">
                Akurasi GPS: <span class="font-bold" x-text="coords.accuracy ? Math.round(coords.accuracy) + ' meter' : '-'"></span>
            </p>

            <button type="button" x-show="!coords.lat" @click="ambilLokasi()" class="text-primary-600 text-sm underline">
                Coba Lagi Ambil Lokasi
            </button>

            <!-- Tombol Aksi (Foto & Check In/Out) -->
            <div class="flex flex-wrap justify-center gap-3 w-full">
                <x-filament::button color="primary" x-show="cameraReady && !photo" @click="ambilFoto()">
                    Ambil Foto
                </x-filament::button>

                <x-filament::button color="gray" x-show="photo" @click="photo = null">
                    Ambil Ulang
                </x-filament::button>

                @if(! $presensi?->check_in)
                    <x-filament::button color="success" x-show="photo && coords.lat" @click="submit('checkIn')">
                        Check In
                    </x-filament::button>
                @elseif(! $presensi?->check_out)
                    <x-filament::button color="danger" x-show="photo && coords.lat" @click="submit('checkOut')">
                        Check Out
                    </x-filament::button>
                @else
                    <p class="text-sm text-gray-500 font-medium py-2 w-full text-center">Presensi hari ini sudah lengkap.</p>
                @endif
            </div>
        </div>

    </div>

    <!-- Bagian Bawah: Tabel Riwayat Presensi -->
    <div class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-white">Riwayat Presensi</h2>
        
        {{ $this->table }}
    </div>

    @script
    <script>
        Alpine.data('presensiKaryawan', () => ({
            stream: null,
            cameraReady: false,
            photo: null,
            coords: { lat: null, lng: null, accuracy: null },
            locationError: null,

            async init() {
                this.ambilLokasi();

                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });

                    this.$nextTick(() => {
                        const video = this.$refs.video;
                        if (!video) return;

                        video.srcObject = this.stream;

                        video.onloadedmetadata = () => {
                            video.play()
                                .then(() => {
                                    this.cameraReady = true; // baru true SETELAH video benar-benar play
                                })
                                .catch(err => {
                                    console.error("Gagal memutar video:", err);
                                    this.locationError = 'Kamera aktif tapi gagal menampilkan preview. Coba refresh halaman.';
                                });
                        };
                    });

                } catch (e) {
                    console.error("Error akses kamera:", e);
                    this.locationError = 'Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan.';
                }
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
                    () => { this.locationError = 'Izin lokasi ditolak. Presensi tidak bisa dilakukan tanpa GPS.'; },
                    { enableHighAccuracy: true, timeout: 10000 },
                );
            },

            ambilFoto() {
                const video = this.$refs.video;
                const canvas = this.$refs.canvas;
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                this.photo = canvas.toDataURL('image/jpeg', 0.8);
            },

            submit(method) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.coords = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };

                        $wire.call(method, this.coords.lat, this.coords.lng, Math.round(this.coords.accuracy), this.photo)
                            .then(() => {
                                this.stream?.getTracks().forEach((t) => t.stop());
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
</x-filament-panels::page>