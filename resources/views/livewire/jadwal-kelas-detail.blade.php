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
                wire:confirm="Hapus {{ count($selected) }} jadwal terpilih?"
                @disabled(empty($selected))
                class="inline-flex items-center gap-1 rounded-md bg-danger-50 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-100 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-danger-500/10 dark:text-danger-400"
            >
                Hapus Terpilih ({{ count($selected) }})
            </button>

            <button
                type="button"
                wire:click="deleteAll"
                wire:confirm="Hapus SEMUA jadwal kelas ini? Tindakan ini tidak dapat dibatalkan."
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
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Hari</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Guru</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Jam Mulai</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Jam Selesai</th>
                    <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                    <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->jadwal as $index => $item)
                    <tr class="border-b border-gray-100 dark:border-gray-800" wire:key="jadwal-{{ $item->id }}">
                        <td class="py-2 pr-2 align-top">
                            <input
                                type="checkbox"
                                wire:model.live="selected"
                                value="{{ $item->id }}"
                                class="rounded border-gray-300"
                            />
                        </td>
                        <td class="py-2 pr-4 align-top">{{ $index + 1 }}</td>
                        <td class="py-2 pr-4 align-top">{{ $item->hari }}</td>
                        <td class="py-2 pr-4 align-top">{{ $item->guru?->nama ?? '-' }}</td>
                        <td class="py-2 pr-4 align-top">
                            {{ \Carbon\Carbon::parse($item->jam_mulai)->format('H:i') }}
                        </td>
                        <td class="py-2 pr-4 align-top">
                            {{ \Carbon\Carbon::parse($item->jam_selesai)->format('H:i') }}
                        </td>
                        <td class="py-2 pr-4 align-top">
                            @if ($item->is_active)
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-500/10 dark:text-gray-400">
                                    Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="py-2 align-top">
                            <div class="flex items-center gap-3">
                                
                                    <a href="{{ \App\Filament\Resources\JadwalResource::getUrl('edit', ['record' => $item->id]) }}"
                                    class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium"
                                >
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    Ubah
                                </a>

                                <button
                                    type="button"
                                    wire:click="deleteOne({{ $item->id }})"
                                    wire:confirm="Hapus jadwal hari {{ $item->hari }}?"
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
                        <td colspan="8" class="py-6 text-center text-gray-400">
                            Belum ada jadwal untuk kelas/program ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>