<div x-data="{ activeTab: @js($programs->keys()->first()) }">
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4 overflow-x-auto">
        @foreach ($programs as $programName => $items)
            <button
                type="button"
                @click="activeTab = @js($programName)"
                :class="activeTab === @js($programName)
                    ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                class="px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors"
            >
                {{ $programName }}
                <span class="ml-1 text-xs text-gray-400">({{ $items->count() }})</span>
            </button>
        @endforeach
    </div>

    @forelse ($programs as $programName => $items)
        <div x-show="activeTab === @js($programName)" x-cloak>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4 w-12 font-medium text-gray-500 dark:text-gray-400">No</th>
                            <th class="py-2 pr-4 w-32 font-medium text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 align-top">{{ $index + 1 }}</td>
                                <td class="py-2 pr-4 align-top whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y') }}
                                </td>
                                <td class="py-2 align-top">{{ $item->catatan ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 text-center py-6">Belum ada catatan progress untuk siswa ini.</p>
    @endforelse
</div>