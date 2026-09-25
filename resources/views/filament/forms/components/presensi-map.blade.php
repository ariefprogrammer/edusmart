<x-dynamic-component
    :component="$getFieldWrapperView()"
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :helper-text="$getHelperText()"
>
    <div
        wire:ignore
        x-data="presensiMap({
            checkInLat: @js($checkInLat),
            checkInLng: @js($checkInLng),
            checkOutLat: @js($checkOutLat),
            checkOutLng: @js($checkOutLng),
        })"
        x-init="init()"
        class="space-y-2"
    >
        <div x-ref="map" style="height: 350px; border-radius: 0.5rem;"></div>
        <p class="text-sm text-gray-400" x-show="!hasPoints">
            Belum ada data koordinat presensi.
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
            function presensiMap({ checkInLat, checkInLng, checkOutLat, checkOutLng }) {
                return {
                    map: null,
                    hasPoints: !!((checkInLat && checkInLng) || (checkOutLat && checkOutLng)),
                    init() {
                        if (!this.hasPoints) return;

                        const points = [];
                        if (checkInLat && checkInLng) points.push([checkInLat, checkInLng]);
                        if (checkOutLat && checkOutLng) points.push([checkOutLat, checkOutLng]);

                        this.map = L.map(this.$refs.map).setView(points[0], 16);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(this.map);

                        if (checkInLat && checkInLng) {
                            L.marker([checkInLat, checkInLng])
                                .addTo(this.map)
                                .bindPopup('Lokasi Check In');
                        }

                        if (checkOutLat && checkOutLng) {
                            L.marker([checkOutLat, checkOutLng])
                                .addTo(this.map)
                                .bindPopup('Lokasi Check Out');
                        }

                        if (points.length > 1) {
                            this.map.fitBounds(points, { padding: [30, 30] });
                        }

                        setTimeout(() => this.map.invalidateSize(), 200);
                    },
                };
            }
        </script>
    @endpush
@endonce