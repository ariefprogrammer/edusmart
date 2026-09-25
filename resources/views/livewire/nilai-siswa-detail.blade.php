<div>
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300" />
            Pilih Semua
        </label>

        <div class="flex gap-2">
            <button
                type="button"
                wire:click="deleteSelected"
                wire:confirm="Hapus {{ count($selected) }} nilai terpilih?"
                @disabled(empty($selected))
                class="inline-flex items-center gap-1 rounded-md bg-danger-50 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-100 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-danger-500/10 dark:text-danger-400"
            >
                Hapus Terpilih ({{ count($selected) }})
            </button>

            <button
                type="button"
                wire:click="deleteAll"
                wire:confirm="Hapus SEMUA nilai siswa ini? Tindakan ini tidak dapat dibatalkan."
                class="inline-flex items-center gap-1 rounded-md bg-danger-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-danger-500"
            >
                Hapus Semua
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="py-2 pr-2 w-8"></th>
                    <th class="py-2 pr-4 w-12 font-medium text-gray-500 dark:text-gray-400">No</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Program</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Kategori</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Nilai</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Guru</th>
                    <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->nilai as $index => $item)
                    <tr class="border-b border-gray-100 dark:border-gray-800" wire:key="nilai-{{ $item->id }}">
                        <td class="py-2 pr-2 align-top">
                            <input
                                type="checkbox"
                                wire:model.live="selected"
                                value="{{ $item->id }}"
                                class="rounded border-gray-300"
                            />
                        </td>
                        <td class="py-2 pr-4 align-top">{{ $index + 1 }}</td>
                        <td class="py-2 pr-4 align-top">{{ $item->kelas?->nama_kelas ?? '-' }}</td>
                        <td class="py-2 pr-4 align-top">{{ $item->kategoriNilai?->nama_kategori ?? '-' }}</td>
                        <td class="py-2 pr-4 align-top font-medium">{{ $item->nilai ?? '-' }}</td>
                        <td class="py-2 pr-4 align-top">{{ $item->guru?->nama ?? '-' }}</td>
                        <td class="py-2 align-top">
                            <div class="flex items-center gap-3">
                                
                                    <a href="{{ \App\Filament\Resources\NilaiSiswaResource::getUrl('edit', ['record' => $item->id]) }}"
                                    class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium"
                                >
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    Ubah
                                </a>

                                <button
                                    type="button"
                                    wire:click="deleteOne({{ $item->id }})"
                                    wire:confirm="Hapus nilai ini?"
                                    class="inline-flex items-center gap-1 text-danger-600 hover:text-danger-500 dark:text-danger-400 font-medium"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-gray-400">
                            Belum ada nilai untuk siswa ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>