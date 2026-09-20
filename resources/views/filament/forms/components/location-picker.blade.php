<x-dynamic-component
    :component="$getFieldWrapperView()"
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :helper-text="$getHelperText()"
>
    <div
        wire:ignore
        x-data="locationPicker({
            state: @entangle($getStatePath()),
            defaultLat: {{ $getDefaultLatitude() }},
            defaultLng: {{ $getDefaultLongitude() }},
            defaultZoom: {{ $getDefaultZoom() }},
        })"
        x-init="init()"
        class="space-y-2"
    >
        <div x-ref="map" style="height: 320px; border-radius: 0.5rem;"></div>
        <p class="text-sm text-gray-500 dark:text-gray-400" x-show="state.lat && state.lng">
            Lat: <span x-text="state.lat"></span>, Lng: <span x-text="state.lng"></span>
        </p>
        <p class="text-sm text-gray-400" x-show="!state.lat">
            Klik pada peta untuk memilih titik lokasi cabang.
        </p>
    </div>
</x-dynamic-component>

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            function locationPicker({ state, defaultLat, defaultLng, defaultZoom }) {
                return {
                    state,
                    map: null,
                    marker: null,
                    init() {
                        // Kalau sudah ada koordinat tersimpan (mode edit), pakai itu — jangan timpa dengan lokasi pengguna
                        if (this.state?.lat && this.state?.lng) {
                            this.setupMap(this.state.lat, this.state.lng, defaultZoom);
                            this.marker = L.marker([this.state.lat, this.state.lng]).addTo(this.map); // baris yang kemarin hilang
                            return;
                        }

                        // Mode create & belum ada koordinat — coba pakai lokasi pengguna saat ini
                        if (!navigator.geolocation) {
                            this.setupMap(defaultLat, defaultLng, defaultZoom);
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.setupMap(position.coords.latitude, position.coords.longitude, 16);
                            },
                            () => {
                                this.setupMap(defaultLat, defaultLng, defaultZoom);
                            },
                            { enableHighAccuracy: true, timeout: 8000 },
                        );
                    },
                    setupMap(lat, lng, zoom) {
                        this.map = L.map(this.$refs.map).setView([lat, lng], 15);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(this.map);

                        this.map.on('click', (e) => {
                            const { lat, lng } = e.latlng;

                            this.state = { lat, lng };

                            if (this.marker) {
                                this.marker.setLatLng([lat, lng]);
                            } else {
                                this.marker = L.marker([lat, lng]).addTo(this.map);
                            }
                        });

                        setTimeout(() => this.map.invalidateSize(), 200);
                    },
                };
            }
        </script>
    @endpush
@endonce