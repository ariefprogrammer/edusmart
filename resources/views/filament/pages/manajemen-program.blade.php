<x-filament-panels::page>
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold">{{ $kelas->nama_kelas }}</h2>
            <p class="text-sm text-gray-500">{{ $kelas->cabang->nama_cabang }}</p>
        </div>

        @if(in_array($activeTab, ['presensi', 'progress', 'nilai']))
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                <input type="checkbox" wire:model.live="tampilkanSemuaSiswa" class="rounded border-gray-300">
                Tampilkan semua siswa (termasuk yang nonaktif)
            </label>
        @endif
    </div>

    <div class="flex gap-4 border-b border-gray-200 dark:border-gray-700">
        <button type="button" wire:click="$set('activeTab', 'presensi')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'presensi' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Presensi Siswa
        </button>
        <button type="button" wire:click="$set('activeTab', 'progress')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'progress' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Progress Siswa
        </button>
        <button type="button" wire:click="$set('activeTab', 'nilai')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'nilai' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Nilai Siswa
        </button>
        <button type="button" wire:click="$set('activeTab', 'rpp')"
            class="pb-2 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'rpp' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500' }}">
            Upload RPP
        </button>
    </div>

    @unless($isGuruPengampu)
        <div class="mb-4 text-xs px-3 py-2 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
            Mode lihat saja — hanya guru yang ditugaskan di program ini yang bisa mengubah data.
        </div>
    @endunless

    @if($activeTab === 'presensi')
        @php $siswaList = $this->getSiswaList(); @endphp

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <div class="mb-4 flex gap-3 max-w-md">
                <div class="flex-1">
                    <label class="mb-2 text-xs text-gray-500 mb-1 block">Tanggal</label>
                    <x-filament::input.wrapper>
                        <input
                            type="date"
                            wire:model.live="tanggal"
                            @disabled(! $isGuruPengampu)
                            class="fi-input block w-full border-none bg-transparent py-1.5 text-sm text-gray-950 dark:text-white focus:ring-0 disabled:opacity-60"
                        >
                    </x-filament::input.wrapper>
                </div>
                <div class="flex-1">
                    <label class="mb-2 text-xs text-gray-500 mb-1 block">Periode</label>
                    <x-filament::input.wrapper>
                        <select wire:model.live="periodeId" @disabled(! $isGuruPengampu) class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                            <option value="">Pilih Periode</option>
                            @foreach($this->getPeriodeOptions() as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if($siswaList->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada siswa untuk ditampilkan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">No</th>
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">Nama Siswa</th>
                                <th colspan="4" class="text-center py-2 px-2 border-b dark:border-gray-700">Presensi</th>
                                <th rowspan="2" class="text-left py-2 px-2 align-bottom">Keterangan</th>
                            </tr>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Hadir</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Izin</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Sakit</th>
                                <th class="text-center py-2 px-2 font-normal text-xs text-gray-500">Alpa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                @php $bisaDiedit = $isGuruPengampu && $siswa->is_active; @endphp
                                <tr wire:key="presensi-row-{{ $siswa->id }}" class="border-b dark:border-gray-700 last:border-b-0">
                                    <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">
                                        {{ $siswa->nama }}
                                        @unless($siswa->is_active)
                                            <span class="text-xs text-gray-400">(Nonaktif)</span>
                                        @endunless
                                    </td>

                                    @foreach(['hadir', 'izin', 'sakit', 'alpa'] as $value)
                                        <td class="text-center py-2 px-2">
                                            <input
                                                type="radio"
                                                name="presensi_status_{{ $siswa->id }}"
                                                wire:model="presensiData.{{ $siswa->id }}.status"
                                                value="{{ $value }}"
                                                @disabled(! $bisaDiedit)
                                                class="appearance-none w-4 h-4 rounded-sm border-2 border-gray-300 dark:border-gray-600
                                                    checked:bg-primary-600 checked:border-primary-600 relative cursor-pointer
                                                    before:content-['✓'] before:hidden checked:before:flex before:absolute before:inset-0
                                                    before:items-center before:justify-center before:text-white before:text-[10px] before:leading-none
                                                    focus:ring-primary-500 focus:ring-offset-0 disabled:opacity-60 disabled:cursor-not-allowed"
                                            >
                                        </td>
                                    @endforeach

                                    <td class="py-2 px-2">
                                        <input
                                            type="text"
                                            wire:model="presensiData.{{ $siswa->id }}.keterangan"
                                            placeholder="Catatan (opsional)"
                                            @disabled(! $bisaDiedit)
                                            class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 disabled:opacity-60"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($isGuruPengampu)
                    <div class="mt-6">
                        <x-filament::button wire:click="submitPresensi">
                            Submit Presensi
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    @elseif($activeTab === 'progress')
        @php $siswaList = $this->getSiswaList(); @endphp

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <div class="mb-4 flex gap-3 max-w-md">
                <div class="flex-1">
                    <label class="mb-2 text-xs text-gray-500 mb-1 block">Tanggal</label>
                    <x-filament::input.wrapper>
                        <input
                            type="date"
                            wire:model.live="tanggal"
                            @disabled(! $isGuruPengampu)
                            class="fi-input block w-full border-none bg-transparent py-1.5 text-sm text-gray-950 dark:text-white focus:ring-0 disabled:opacity-60"
                        >
                    </x-filament::input.wrapper>
                </div>
                <div class="flex-1">
                    <label class="mb-2 text-xs text-gray-500 mb-1 block">Periode</label>
                    <x-filament::input.wrapper>
                        <select wire:model.live="periodeId" @disabled(! $isGuruPengampu) class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                            <option value="">Pilih Periode</option>
                            @foreach($this->getPeriodeOptions() as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if($siswaList->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada siswa untuk ditampilkan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 px-2">No</th>
                                <th class="text-left py-2 px-2">Nama Siswa</th>
                                <th class="text-left py-2 px-2">Catatan Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                @php $bisaDiedit = $isGuruPengampu && $siswa->is_active; @endphp
                                <tr class="border-b dark:border-gray-700 last:border-b-0">
                                    <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">
                                        {{ $siswa->nama }}
                                        @unless($siswa->is_active)
                                            <span class="text-xs text-gray-400">(Nonaktif)</span>
                                        @endunless
                                    </td>
                                    <td class="py-2 px-2">
                                        <textarea
                                            wire:model="progressData.{{ $siswa->id }}"
                                            rows="2"
                                            placeholder="Tulis catatan progress belajar siswa..."
                                            @disabled(! $bisaDiedit)
                                            class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 disabled:opacity-60"
                                        ></textarea>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($isGuruPengampu)
                    <div class="mt-6">
                        <x-filament::button wire:click="submitProgress">
                            Submit Progress
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    @elseif($activeTab === 'nilai')
        @php
            $kategoriList = $this->getKategoriList();
            $siswaList = $this->getSiswaList();
        @endphp

        @if($isGuruPengampu)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-4">
                <div class="flex flex-col md:flex-row gap-6 items-start">
                    <div class="w-full md:w-1/3">
                        <label class="text-xs text-gray-500 mb-1 block">Periode</label>
                        <x-filament::input.wrapper>
                            <select wire:model.live="periodeSemesterId" @disabled(! $isGuruPengampu) class="fi-select-input block w-full border-none bg-transparent py-1.5 text-sm">
                                <option value="">Pilih Periode</option>
                                @foreach($this->getPeriodeSemesterOptions() as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="w-full md:w-2/3 max-w-md">
                        <label class="text-xs text-transparent mb-1 hidden md:block">&nbsp;</label>
                        <form wire:submit.prevent="tambahKategori" class="flex gap-2 items-start">
                            <div class="flex-1">
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="text"
                                        wire:model="namaKategoriBaru"
                                        placeholder="Nama kategori nilai, misal: UTS, Tugas, dll"
                                    />
                                </x-filament::input.wrapper>
                                @error('namaKategoriBaru') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <x-filament::button type="submit">
                                Tambah Kategori
                            </x-filament::button>
                        </form>
                    </div>
                    
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            @if($siswaList->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada siswa untuk ditampilkan.</p>
            @elseif($kategoriList->isEmpty())
                <p class="text-sm text-gray-500">Belum ada kategori nilai.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 px-2">No</th>
                                <th class="text-left py-2 px-2">Nama Siswa</th>
                                @foreach($kategoriList as $kategori)
                                    <th class="text-center py-2 px-2 min-w-[100px]">
                                        <div class="flex items-center justify-center gap-1">
                                            <span>{{ $kategori->nama_kategori }}</span>
                                            @if($isGuruPengampu)
                                                <button
                                                    type="button"
                                                    wire:click="hapusKategori({{ $kategori->id }})"
                                                    wire:confirm="Yakin ingin menghapus kategori '{{ $kategori->nama_kategori }}'? Semua nilai di kategori ini juga akan terhapus."
                                                    class="text-gray-400 hover:text-red-600"
                                                    title="Hapus kategori"
                                                >
                                                    <x-heroicon-o-trash class="w-4 h-4" />
                                                </button>
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                @php $bisaDiedit = $isGuruPengampu && $siswa->is_active; @endphp
                                <tr class="border-b dark:border-gray-700 last:border-b-0">
                                    <td class="py-2 px-2 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium">
                                        {{ $siswa->nama }}
                                        @unless($siswa->is_active)
                                            <span class="text-xs text-gray-400">(Nonaktif)</span>
                                        @endunless
                                    </td>
                                    @foreach($kategoriList as $kategori)
                                        <td class="py-2 px-2 text-center">
                                            <input
                                                type="number"
                                                step="0.01"
                                                wire:model="nilaiData.{{ $siswa->id }}.{{ $kategori->id }}"
                                                @disabled(! $bisaDiedit)
                                                class="w-20 text-sm text-center rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 disabled:opacity-60"
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($isGuruPengampu)
                    <div class="mt-6">
                        <x-filament::button wire:click="submitNilai">
                            Simpan Nilai
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    @else
        @php $rppList = $this->getRppList(); @endphp

        @if($isGuruPengampu)
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-5">Upload RPP Baru</h2>

                <div class="space-y-6">
                    <!-- Periode Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Periode Semester</label>
                        <x-filament::input.wrapper>
                            <select wire:model.live="periodeSemesterId" @disabled(! $isGuruPengampu) class="fi-select-input block w-full border-none bg-transparent py-2 text-sm focus:ring-0">
                                <option value="">Pilih Periode</option>
                                @foreach($this->getPeriodeSemesterOptions() as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </x-filament::input.wrapper>
                    </div>

                    <!-- Upload Form -->
                    <form wire:submit.prevent="uploadRpp">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Judul RPP</label>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="rppJudul" placeholder="Masukkan judul RPP..." />
                            </x-filament::input.wrapper>
                            @error('rppJudul') <p class="text-xs text-danger-600 dark:text-danger-400 mt-1.5">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File Dokumen (PDF)</label>
                            <label for="rppFileDropzone" class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl cursor-pointer bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 overflow-hidden">
                                @if($rppFile)
                                    <div class="flex flex-col items-center justify-center w-full h-full bg-green-50 dark:bg-green-900/20 pt-5 pb-6 text-center mt-6">
                                        <p class="mb-1 text-sm font-semibold text-green-700 dark:text-green-300">File PDF berhasil dilampirkan!</p>
                                        <p class="text-xs text-green-600 dark:text-green-400">
                                            {{ is_string($rppFile) ? 'Dokumen siap diupload' : (method_exists($rppFile, 'getClientOriginalName') ? $rppFile->getClientOriginalName() : 'Dokumen siap diupload') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 underline">Klik untuk mengganti file</p>
                                    </div>
                                
                                @else
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                        <svg class="w-10 h-10 mb-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mb-1 text-sm text-gray-600 dark:text-gray-400"><span class="font-semibold text-primary-600 dark:text-primary-400">Klik untuk unggah</span> atau seret file ke sini</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-500">Mendukung file PDF</p>
                                    </div>
                                @endif

                                <div wire:loading wire:target="rppFile" class="absolute inset-0 bg-white/90 dark:bg-gray-900/90 flex flex-col items-center justify-center z-10">
                                    <svg class="animate-spin h-8 w-8 text-primary-600 dark:text-primary-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Menyiapkan dokumen...</span>
                                </div>

                                <input id="rppFileDropzone" type="file" wire:model="rppFile" accept="application/pdf" class="hidden">
                            </label>
                            @error('rppFile') <p class="text-xs text-danger-600 dark:text-danger-400 mt-1.5">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end pt-2">
                            <x-filament::button type="submit" icon="heroicon-m-arrow-up-tray">
                                Upload RPP
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">Daftar RPP</h3>

            @forelse($rppList as $rpp)
                <div class="border-b dark:border-gray-700 last:border-b-0 py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">{{ $rpp->judul }}</p>
                            <p class="text-xs text-gray-500">
                                Diunggah oleh {{ $rpp->guru->nama ?? '(guru dihapus)' }} · {{ $rpp->created_at->format('d M Y H:i') }}
                            </p>
                        </div>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($rpp->file_path) }}" target="_blank"
                        class="text-sm text-primary-600 hover:underline">
                            Lihat PDF
                        </a>
                    </div>

                    <div class="mt-3 pl-3 border-l-2 dark:border-gray-700 space-y-2">
                        @forelse($rpp->ulasan as $ulasan)
                            <div class="text-sm">
                                <span class="font-medium">{{ $ulasan->user->name ?? '(pengguna dihapus)' }}</span>
                                <span class="text-gray-400 text-xs">· {{ $ulasan->created_at->diffForHumans() }}</span>
                                <p class="text-gray-600 dark:text-gray-300">{{ $ulasan->ulasan }}</p>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">Belum ada ulasan.</p>
                        @endforelse

                        <form wire:submit.prevent="submitUlasan({{ $rpp->id }})" class="flex gap-2 mt-2">
                            <input
                                type="text"
                                wire:model="ulasanBaru.{{ $rpp->id }}"
                                placeholder="Tulis ulasan..."
                                class="flex-1 text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                            >
                            <x-filament::button type="submit" size="sm">Kirim</x-filament::button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada RPP yang diunggah untuk periode ini.</p>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>