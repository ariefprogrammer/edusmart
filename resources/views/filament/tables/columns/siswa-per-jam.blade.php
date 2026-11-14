@php
    $record = $getRecord();
    $daftar = $record instanceof \App\Models\Jadwal ? $record->siswa : collect([$record]);
@endphp

<div class="flex flex-wrap items-center px-3 py-2 text-sm">
    @forelse ($daftar as $siswa)
        @php
            $aktif = (bool) ($programAktif[$siswa->id] ?? false);
            // Di baris jam, id jam ikut dikirim supaya aksi tahu jam mana yang dimaksud
            $argumen = ['siswa' => $siswa->id]
                + ($record instanceof \App\Models\Jadwal ? ['jadwal' => $record->getKey()] : []);
        @endphp

        <button
            type="button"
            wire:key="siswa-{{ $record->getKey() }}-{{ $siswa->id }}"
            wire:click="mountTableAction('detailSiswa', null, @js($argumen))"
            title="{{ $aktif ? 'Lihat detail' : 'Nonaktif di program ini' }}"
            class="font-medium text-primary-600 hover:underline dark:text-primary-400 {{ $aktif ? '' : 'line-through opacity-60' }}"
        >{{ $siswa->nama }}</button>

        @if (! $loop->last)
            <span class="pe-1 text-gray-400">,</span>
        @endif
    @empty
        <span class="text-gray-400">Belum ada siswa</span>
    @endforelse
</div>