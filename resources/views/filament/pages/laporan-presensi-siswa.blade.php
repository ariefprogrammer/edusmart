<x-filament-panels::page>
    <style>
        .presensi-badge-edit {
            cursor: pointer;
            border: 0;
            transition: box-shadow .15s ease;
        }
        .presensi-badge-edit:hover {
            box-shadow: 0 0 0 2px rgb(var(--primary-500));
        }
    </style>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Dari Tanggal</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="dateStart" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-xs text-gray-500 mb-1 block">Sampai Tanggal</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="dateEnd" />
                </x-filament::input.wrapper>
            </div>

            @if($this->isSuperAdmin())
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Cabang</label>
                    <x-filament::input.wrapper>
                        <select wire:model.live="cabangId" class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                            <option value="">Semua Cabang</option>
                            @foreach($this->getCabangOptions() as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div>
                <label class="text-xs text-gray-500 mb-1 block">Kelas / Program</label>
                <x-filament::input.wrapper>
                    <select wire:model.live="kelasId" class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                        <option value="">Semua Program</option>
                        @foreach($this->getKelasOptions() as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-xs text-gray-500 mb-1 block">Status Siswa</label>
                <x-filament::input.wrapper>
                    <select wire:model.live="statusSiswa" class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                        <option value="aktif">Aktif</option>
                        <option value="tidak_aktif">Tidak Aktif</option>
                        <option value="semua">Semua Status</option>
                    </select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
        @php
            $rekap = $this->getRekap();
            $tanggalList = $this->getDateRange();
            $bisaUbah = $this->bisaUbah();
            $badgeStyle = [
                'hadir' => ['H', '#dbeafe', '#1d4ed8'],
                'izin' => ['I', '#fef9c3', '#a16207'],
                'sakit' => ['S', '#dcfce7', '#15803d'],
                'alpa' => ['A', '#fee2e2', '#b91c1c'],
            ];
        @endphp

        @if($rekap->isEmpty())
            <p class="text-sm text-gray-500">Tidak ada data siswa pada filter yang dipilih.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2 px-2 whitespace-nowrap">No</th>
                            <th class="text-left py-2 px-2 whitespace-nowrap">Nama Siswa</th>
                            @foreach($tanggalList as $tanggal)
                                <th class="text-center py-2 px-1 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($tanggal)->format('d') }}
                                </th>
                            @endforeach
                            <th class="text-center py-2 px-2 whitespace-nowrap" style="background-color:#eff6ff;">H</th>
                            <th class="text-center py-2 px-2 whitespace-nowrap" style="background-color:#fefce8;">I</th>
                            <th class="text-center py-2 px-2 whitespace-nowrap" style="background-color:#f0fdf4;">S</th>
                            <th class="text-center py-2 px-2 whitespace-nowrap" style="background-color:#fef2f2;">A</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rekap as $i => $row)
                            <tr class="border-b dark:border-gray-700 last:border-b-0">
                                <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                <td class="py-2 px-2 font-medium whitespace-nowrap">{{ $row->siswa->nama }}</td>

                                @foreach($tanggalList as $tanggal)
                                    @php $status = $row->harian[$tanggal] ?? null; @endphp
                                    <td class="py-1 px-1 text-center">
                                        @if($status && isset($badgeStyle[$status]))
                                            @php $b = $badgeStyle[$status]; @endphp

                                            @if($bisaUbah)
                                                <button
                                                    type="button"
                                                    wire:click="bukaUbah({{ $row->siswa->id }}, '{{ $tanggal }}')"
                                                    title="Klik untuk mengubah kehadiran"
                                                    class="presensi-badge-edit inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold"
                                                    style="background-color:{{ $b[1] }};color:{{ $b[2] }};"
                                                >{{ $b[0] }}</button>
                                            @else
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold" style="background-color:{{ $b[1] }};color:{{ $b[2] }};">{{ $b[0] }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="py-2 px-2 text-center font-semibold" style="background-color:#eff6ff;color:#1d4ed8;">{{ $row->hadir }}</td>
                                <td class="py-2 px-2 text-center font-semibold" style="background-color:#fefce8;color:#a16207;">{{ $row->izin }}</td>
                                <td class="py-2 px-2 text-center font-semibold" style="background-color:#f0fdf4;color:#15803d;">{{ $row->sakit }}</td>
                                <td class="py-2 px-2 text-center font-semibold" style="background-color:#fef2f2;color:#b91c1c;">{{ $row->alpa }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-filament::modal id="ubah-presensi" width="lg">
        <x-slot name="heading">Ubah Kehadiran</x-slot>

        <x-slot name="description">
            {{ $editNamaSiswa }}
            @if($editTanggal)
                · {{ \Carbon\Carbon::parse($editTanggal)->translatedFormat('l, d F Y') }}
            @endif
        </x-slot>

        <form wire:submit.prevent="simpanUbah" class="space-y-4">
            @foreach($editRows as $recordId => $row)
                <div wire:key="ubah-row-{{ $recordId }}">
                    <p class="text-sm font-medium mb-1">{{ $row['kelas'] }}</p>

                    <x-filament::input.wrapper>
                        <select wire:model="editRows.{{ $recordId }}.status" class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                            <option value="hadir">Hadir</option>
                            <option value="izin">Izin</option>
                            <option value="sakit">Sakit</option>
                            <option value="alpa">Alpa</option>
                        </select>
                    </x-filament::input.wrapper>

                    @error("editRows.$recordId.status")
                        <p class="text-xs text-danger-600 dark:text-danger-400 mt-1">{{ $message }}</p>
                    @enderror

                    @if(filled($row['keterangan'] ?? null))
                        <p class="text-xs text-gray-500 mt-1">Keterangan: {{ $row['keterangan'] }}</p>
                    @endif
                </div>
            @endforeach

            <div class="flex justify-end gap-2 pt-2">
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'ubah-presensi' })">
                    Batal
                </x-filament::button>
                <x-filament::button type="submit">
                    Simpan
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>
</x-filament-panels::page>