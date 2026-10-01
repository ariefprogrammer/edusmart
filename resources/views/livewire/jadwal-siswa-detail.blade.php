<div class="space-y-6">
    @forelse ($this->programList as $kelas)
        <div>
            <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $kelas->nama_kelas }}</h4>

                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Gabung: {{ \Carbon\Carbon::parse($kelas->pivot->tanggal_gabung)->format('d M Y') }}</span>

                    @if ($kelas->pivot->is_active)
                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                            Aktif di Program
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 font-medium text-gray-600 dark:bg-gray-500/10 dark:text-gray-400">
                            Nonaktif di Program
                        </span>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pl-4 pr-4 w-12 font-medium text-gray-500 dark:text-gray-400">No</th>
                            <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Hari</th>
                            <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Guru</th>
                            <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Jam Mulai</th>
                            <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Jam Selesai</th>
                            <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kelas->jadwal as $index => $item)
                            <tr class="border-b border-gray-100 dark:border-gray-800 last:border-b-0" wire:key="jadwal-{{ $item->id }}">
                                <td class="py-2 pl-4 pr-4 align-top">{{ $index + 1 }}</td>
                                <td class="py-2 pr-4 align-top">{{ $item->hari }}</td>
                                <td class="py-2 pr-4 align-top">{{ $item->guru?->nama ?? '-' }}</td>
                                <td class="py-2 pr-4 align-top">{{ \Carbon\Carbon::parse($item->jam_mulai)->format('H:i') }}</td>
                                <td class="py-2 pr-4 align-top">{{ \Carbon\Carbon::parse($item->jam_selesai)->format('H:i') }}</td>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-400">
                                    Belum ada jadwal untuk program ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-center text-gray-400 py-6">Siswa ini belum terdaftar di program manapun.</p>
    @endforelse
</div>