<x-filament-panels::page>

    <style>
        /* =========================================================
         * PROGRAM GRID
         * ========================================================= */

        .program-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
        }

        @media (max-width: 1024px) {
            .program-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .program-grid {
                grid-template-columns: 1fr;
            }
        }


        /* =========================================================
         * PROGRAM CARD
         * ========================================================= */

        .program-card {
            position: relative;
            display: flex;
            flex-direction: column;

            overflow: hidden;

            background: rgb(var(--white));

            border: 1px solid rgb(var(--gray-200));
            border-radius: 1rem;

            box-shadow:
                0 1px 2px rgba(0, 0, 0, .04),
                0 4px 12px rgba(0, 0, 0, .04);

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                border-color .2s ease;
        }

        .dark .program-card {
            background: rgb(var(--gray-800));
            border-color: rgb(var(--gray-700));

            box-shadow:
                0 1px 2px rgba(0, 0, 0, .15),
                0 4px 12px rgba(0, 0, 0, .15);
        }

        .program-card:not(.program-card--disabled):hover {
            transform: translateY(-3px);

            border-color: rgb(var(--primary-300));

            box-shadow:
                0 10px 25px rgba(0, 0, 0, .08);
        }

        .dark .program-card:not(.program-card--disabled):hover {
            border-color: rgb(var(--primary-600));

            box-shadow:
                0 10px 25px rgba(0, 0, 0, .25);
        }


        /* =========================================================
         * IMAGE / ICON AREA
         * ========================================================= */

        .program-image {
            position: relative;

            display: flex;
            align-items: center;
            justify-content: center;

            min-height: 180px;

            padding: 1.5rem;

            background:
                linear-gradient(
                    135deg,
                    rgb(var(--primary-50)),
                    rgb(var(--gray-50))
                );

            border-bottom: 1px solid rgb(var(--gray-200));
        }

        .dark .program-image {
            background:
                linear-gradient(
                    135deg,
                    rgba(var(--primary-500), .12),
                    rgb(var(--gray-800))
                );

            border-bottom-color: rgb(var(--gray-700));
        }


        /* =========================================================
         * QUOTA WARNING BADGE
         * ========================================================= */

        .quota-warning-badge {
            position: absolute;

            top: .75rem;
            right: .75rem;

            display: inline-flex;
            align-items: center;
            gap: .3rem;

            padding: .4rem .65rem;

            border: 1px solid rgba(var(--danger-500), .2);
            border-radius: 999px;

            background: rgb(var(--danger-50));
            color: rgb(var(--danger-700));

            font-size: .7rem;
            font-weight: 700;

            box-shadow:
                0 2px 6px rgba(0, 0, 0, .08);
        }

        .dark .quota-warning-badge {
            border-color: rgba(var(--danger-500), .3);

            background: rgba(var(--danger-500), .15);
            color: rgb(var(--danger-400));
        }


        /* =========================================================
         * CARD CONTENT
         * ========================================================= */

        .program-content {
            display: flex;
            flex-direction: column;

            flex: 1;

            padding: 1.15rem;
        }

        .program-name {
            margin: 0;

            font-size: 1rem;
            line-height: 1.4;
            font-weight: 700;

            color: rgb(var(--gray-950));
        }

        .dark .program-name {
            color: rgb(var(--gray-100));
        }


        /* =========================================================
         * QUOTA INFORMATION
         * ========================================================= */

        .program-quota {
            margin-top: .35rem;

            font-size: .72rem;
            line-height: 1.5;

            color: rgb(var(--gray-500));
        }

        .program-quota strong {
            font-weight: 600;

            color: rgb(var(--gray-600));
        }

        .dark .program-quota strong {
            color: rgb(var(--gray-300));
        }


        /* =========================================================
         * ACTION BUTTONS
         * ========================================================= */

        .program-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;

            gap: .6rem;

            margin-top: 1rem;
        }

        .program-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;

            min-height: 2.35rem;

            padding: .5rem .7rem;

            border-radius: .55rem;

            font-size: .75rem;
            font-weight: 600;

            cursor: pointer;

            transition:
                background .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .15s ease;
        }

        .program-action:active {
            transform: scale(.98);
        }


        /* Jadwal */

        .program-action--schedule {
            border: 1px solid rgb(var(--gray-300));

            background: rgb(var(--white));
            color: rgb(var(--gray-700));
        }

        .program-action--schedule:hover {
            background: rgb(var(--gray-50));
            border-color: rgb(var(--gray-400));
        }

        .dark .program-action--schedule {
            border-color: rgb(var(--gray-600));

            background: rgb(var(--gray-800));
            color: rgb(var(--gray-300));
        }

        .dark .program-action--schedule:hover {
            background: rgb(var(--gray-700));
        }


        /* Masuk */

        .program-action--enter {
            border: 1px solid rgb(var(--primary-600));

            background: rgb(var(--primary-600));
            color: white;
        }

        .program-action--enter:hover {
            background: rgb(var(--primary-700));
        }


        /* Disabled */

        .program-action--disabled {
            cursor: not-allowed;

            border-color: rgb(var(--gray-200));

            background: rgb(var(--gray-100));
            color: rgb(var(--gray-400));
        }

        .dark .program-action--disabled {
            border-color: rgb(var(--gray-700));

            background: rgb(var(--gray-700));
            color: rgb(var(--gray-500));
        }


        /* =========================================================
         * MODAL
         * ========================================================= */

        .schedule-modal-backdrop {
            position: fixed;
            inset: 0;

            z-index: 50;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 1rem;

            background: rgba(0, 0, 0, .55);

            backdrop-filter: blur(4px);
        }

        .schedule-modal {
            width: 100%;
            max-width: 32rem;

            max-height: calc(100vh - 2rem);

            overflow: hidden;

            background: #ffffff;

            border: 1px solid rgb(var(--gray-200));
            border-radius: 1rem;

            box-shadow:
                0 25px 50px rgba(0, 0, 0, .25);
        }

        .dark .schedule-modal {
            background: rgb(var(--gray-800));
            border-color: rgb(var(--gray-700));
        }


        /* Modal Header */

        .schedule-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 1rem;

            padding: 1rem 1.15rem;

            border-bottom: 1px solid rgb(var(--gray-200));
        }

        .dark .schedule-modal-header {
            border-bottom-color: rgb(var(--gray-700));
        }

        .schedule-modal-title {
            font-size: 1rem;
            font-weight: 700;

            color: rgb(var(--gray-950));
        }

        .dark .schedule-modal-title {
            color: rgb(var(--gray-100));
        }

        .schedule-modal-close {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 2rem;
            height: 2rem;

            border-radius: .5rem;

            color: rgb(var(--gray-500));

            cursor: pointer;
        }

        .schedule-modal-close:hover {
            background: rgb(var(--gray-100));
            color: rgb(var(--gray-700));
        }

        .dark .schedule-modal-close:hover {
            background: rgb(var(--gray-700));
            color: rgb(var(--gray-200));
        }


        /* Modal Body */

        .schedule-modal-body {
            max-height: 60vh;

            overflow-y: auto;

            padding: 1rem 1.15rem;
        }

        .schedule-list {
            display: flex;
            flex-direction: column;

            gap: .6rem;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: .75rem;

            padding: .75rem;

            border: 1px solid rgb(var(--gray-200));
            border-radius: .65rem;

            background: rgb(var(--gray-50));
        }

        .dark .schedule-item {
            border-color: rgb(var(--gray-700));
            background: rgb(var(--gray-700));
        }

        .schedule-item-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 2rem;
            height: 2rem;

            flex-shrink: 0;

            border-radius: .5rem;

            background: rgb(var(--primary-100));
            color: rgb(var(--primary-600));
        }

        .dark .schedule-item-icon {
            background: rgba(var(--primary-500), .15);
            color: rgb(var(--primary-400));
        }

        .schedule-item-content {
            min-width: 0;
        }

        .schedule-item-day {
            font-size: .8rem;
            font-weight: 700;

            color: rgb(var(--gray-800));
        }

        .dark .schedule-item-day {
            color: rgb(var(--gray-200));
        }

        .schedule-item-time {
            margin-top: .1rem;

            font-size: .72rem;

            color: rgb(var(--gray-500));
        }

        .schedule-empty {
            padding: 1.5rem;

            text-align: center;

            font-size: .8rem;

            color: rgb(var(--gray-500));
        }


        /* =========================================================
         * JADWAL PENGGANTI
         * ========================================================= */

        .replacement-section {
            margin-top: 2rem;
        }

        .replacement-header {
            margin-bottom: 1rem;
        }

        .replacement-title {
            display: flex;
            align-items: center;
            gap: .5rem;

            font-size: 1rem;
            font-weight: 700;

            color: rgb(var(--gray-950));
        }

        .dark .replacement-title {
            color: rgb(var(--gray-100));
        }

        .replacement-description {
            margin-top: .3rem;

            font-size: .75rem;

            color: rgb(var(--gray-500));
        }

        .replacement-card {
            position: relative;
            overflow: hidden;

            background: rgb(var(--white));

            border: 1px solid rgb(var(--gray-200));
            border-radius: 1rem;

            box-shadow:
                0 1px 2px rgba(0, 0, 0, .04);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .dark .replacement-card {
            background: rgb(var(--gray-800));
            border-color: rgb(var(--gray-700));
        }

        .replacement-card:hover {
            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(0, 0, 0, .07);
        }

        .replacement-link {
            display: block;

            padding: 1.15rem;

            color: inherit;
            text-decoration: none;
        }

        .replacement-date {
            margin-top: .9rem;

            font-size: .8rem;
            font-weight: 600;

            color: rgb(var(--gray-800));
        }

        .dark .replacement-date {
            color: rgb(var(--gray-200));
        }

        .replacement-info {
            margin-top: .3rem;

            font-size: .72rem;
            line-height: 1.5;

            color: rgb(var(--gray-500));
        }

        .today-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;

            margin-top: .8rem;

            padding: .35rem .6rem;

            border-radius: 999px;

            background: rgb(var(--warning-50));
            color: rgb(var(--warning-700));

            font-size: .68rem;
            font-weight: 700;
        }

        .dark .today-badge {
            background: rgba(var(--warning-500), .15);
            color: rgb(var(--warning-400));
        }


        /* =========================================================
         * EMPTY STATE
         * ========================================================= */

        .program-empty {
            padding: 2rem;

            border: 1px solid rgb(var(--gray-200));
            border-radius: 1rem;

            background: rgb(var(--white));

            text-align: center;

            font-size: .875rem;

            color: rgb(var(--gray-500));
        }

        .dark .program-empty {
            border-color: rgb(var(--gray-700));
            background: rgb(var(--gray-800));
        }

        /* =========================================================
        * REPLACEMENT IMAGE
        * ========================================================= */

        .replacement-image {
            position: relative;

            display: flex;
            align-items: center;
            justify-content: center;

            min-height: 150px;

            padding: 1.25rem;

            background:
                linear-gradient(
                    135deg,
                    rgb(var(--primary-50)),
                    rgb(var(--gray-50))
                );

            border-bottom: 1px solid rgb(var(--gray-200));
        }

        .dark .replacement-image {
            background:
                linear-gradient(
                    135deg,
                    rgba(var(--primary-500), .12),
                    rgb(var(--gray-800))
                );

            border-bottom-color: rgb(var(--gray-700));
        }


        /* =========================================================
        * REPLACEMENT CONTENT
        * ========================================================= */

        .replacement-content {
            display: flex;
            flex-direction: column;

            padding: 1rem 1.15rem 1.15rem;
        }

        .replacement-name {
            margin: 0;

            font-size: .95rem;
            line-height: 1.35;
            font-weight: 700;

            color: rgb(var(--gray-950));
        }

        .dark .replacement-name {
            color: rgb(var(--gray-100));
        }

        .replacement-branch {
            margin-top: .2rem;

            font-size: .72rem;

            color: rgb(var(--gray-500));
        }


        /* =========================================================
        * REPLACEMENT DETAIL
        * ========================================================= */

        .replacement-detail {
            display: flex;
            align-items: center;
            gap: .65rem;

            margin-top: .85rem;
        }

        .replacement-detail-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 2rem;
            height: 2rem;

            flex-shrink: 0;

            border-radius: .5rem;

            background: rgb(var(--primary-50));
            color: rgb(var(--primary-600));
        }

        .dark .replacement-detail-icon {
            background: rgba(var(--primary-500), .12);
            color: rgb(var(--primary-400));
        }

        .replacement-detail-label {
            font-size: .65rem;
            line-height: 1.2;

            color: rgb(var(--gray-500));
        }

        .replacement-detail-value {
            margin-top: .1rem;

            font-size: .75rem;
            line-height: 1.35;
            font-weight: 600;

            color: rgb(var(--gray-800));
        }

        .dark .replacement-detail-value {
            color: rgb(var(--gray-200));
        }


        /* =========================================================
        * ORIGINAL SESSION
        * ========================================================= */

        .replacement-original {
            margin-top: .85rem;

            padding: .65rem .75rem;

            border-radius: .6rem;

            background: rgb(var(--gray-50));
        }

        .dark .replacement-original {
            background: rgb(var(--gray-700));
        }

        .replacement-original-label {
            font-size: .65rem;

            color: rgb(var(--gray-500));
        }

        .replacement-original-value {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .3rem;

            margin-top: .15rem;

            font-size: .7rem;
            line-height: 1.4;
            font-weight: 600;

            color: rgb(var(--gray-700));
        }

        .dark .replacement-original-value {
            color: rgb(var(--gray-300));
        }


        /* =========================================================
        * TODAY BADGE
        * ========================================================= */

        .today-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;

            align-self: flex-start;

            margin-top: .75rem;

            padding: .35rem .6rem;

            border-radius: 999px;

            background: rgb(var(--warning-50));
            color: rgb(var(--warning-700));

            font-size: .68rem;
            font-weight: 700;
        }

        .dark .today-badge {
            background: rgba(var(--warning-500), .15);
            color: rgb(var(--warning-400));
        }


        /* =========================================================
        * REPLACEMENT ACTION
        * ========================================================= */

        .replacement-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;

            width: 100%;

            min-height: 2.35rem;

            margin-top: 1rem;
            padding: .5rem .7rem;

            border: 1px solid rgb(var(--primary-600));
            border-radius: .55rem;

            background: rgb(var(--primary-600));
            color: white;

            font-size: .75rem;
            font-weight: 600;

            text-decoration: none;

            transition:
                background .2s ease,
                transform .15s ease;
        }

        .replacement-action:hover {
            background: rgb(var(--primary-700));
        }

        .replacement-action:active {
            transform: scale(.98);
        }
    </style>


    @php
        $programs = $this->getProgramList();
        $penggantiList = $this->getJadwalPenggantiList();
    @endphp


    {{-- =========================================================
     * EMPTY STATE
     * ========================================================= --}}
    @if($programs->isEmpty() && $penggantiList->isEmpty())

        <div class="program-empty">
            Kamu belum ditugaskan ke program/kelas manapun.
        </div>

    @else


        {{-- =====================================================
         * PROGRAM
         * ===================================================== --}}
        @if($programs->isNotEmpty())

            <div class="program-grid">

                @foreach($programs as $kelas)

                    @php
                        $statusKuota = $kelas->statusKuota();

                        $kuotaKurang = $statusKuota === 'kurang';
                        $kuotaLebih = $statusKuota === 'lebih';
                    @endphp


                    {{-- =================================================
                     * CARD
                     * ================================================= --}}
                    <div
                        x-data="{ scheduleOpen: false }"
                        class="program-card"
                        @if($kuotaKurang)
                            style="border-color: rgba(var(--danger-500), .35);"
                        @endif
                    >

                        {{-- =================================================
                         * GAMBAR PROGRAM
                         * ================================================= --}}
                        <div class="program-image">

                            <x-kelas-icon
                                :kelas="$kelas"
                                :ukuran="6"
                            />


                            {{-- BADGE HANYA JIKA KUOTA KURANG --}}
                            @if($kuotaKurang)

                                <div class="quota-warning-badge">

                                    <x-filament::icon
                                        icon="heroicon-m-exclamation-triangle"
                                        class="w-3.5 h-3.5"
                                    />

                                    Kuota kurang

                                </div>

                            @endif

                        </div>


                        {{-- =================================================
                         * CONTENT
                         * ================================================= --}}
                        <div class="program-content">

                            {{-- NAMA PROGRAM --}}
                            <h3 class="program-name">
                                {{ $kelas->nama_kelas }}
                            </h3>


                            {{-- INFORMASI KUOTA --}}
                            <div class="program-quota">

                                {{ $kelas->siswa_aktif_count }} siswa aktif

                                @if($kelas->kuota_minimal > 0 || $kelas->kuota_maksimal !== null)

                                    <span>•</span>

                                    <strong>

                                        @if($kelas->kuota_minimal > 0)
                                            Min. {{ $kelas->kuota_minimal }}
                                        @endif

                                        @if($kelas->kuota_minimal > 0 && $kelas->kuota_maksimal !== null)
                                            ·
                                        @endif

                                        @if($kelas->kuota_maksimal !== null)
                                            Maks. {{ $kelas->kuota_maksimal }}
                                        @endif

                                    </strong>

                                @endif

                            </div>


                            {{-- =================================================
                             * BUTTON
                             * ================================================= --}}
                            <div class="program-actions">

                                {{-- JADWAL --}}
                                <button
                                    type="button"
                                    class="program-action program-action--schedule"

                                    x-on:click="scheduleOpen = true"
                                >

                                    <x-filament::icon
                                        icon="heroicon-m-calendar-days"
                                        class="w-4 h-4"
                                    />

                                    Jadwal

                                </button>


                                {{-- MASUK --}}
                                @if($kuotaKurang)

                                    <button
                                        type="button"
                                        class="program-action program-action--disabled"
                                        disabled
                                        title="Program belum dapat dibuka karena kuota belum terpenuhi."
                                    >

                                        <x-filament::icon
                                            icon="heroicon-m-lock-closed"
                                            class="w-4 h-4"
                                        />

                                        Masuk

                                    </button>

                                @else

                                    <a
                                        href="{{ \App\Filament\Pages\ManajemenProgram::getUrl(['kelas' => $kelas->id]) }}"
                                        class="program-action program-action--enter"
                                    >

                                        <x-filament::icon
                                            icon="heroicon-m-arrow-right"
                                            class="w-4 h-4"
                                        />

                                        Masuk

                                    </a>

                                @endif

                            </div>

                        </div>


                        {{-- =================================================
                         * MODAL JADWAL
                         * ================================================= --}}
                        <template x-teleport="body">

                            <div
                                x-show="scheduleOpen"
                                x-cloak

                                x-on:keydown.escape.window="scheduleOpen = false"

                                class="schedule-modal-backdrop"

                                x-transition.opacity
                            >

                                <div
                                    class="schedule-modal"

                                    x-on:click.outside="scheduleOpen = false"

                                    x-transition.scale
                                >

                                    {{-- HEADER --}}
                                    <div class="schedule-modal-header">

                                        <div>

                                            <div class="schedule-modal-title">
                                                Jadwal Program
                                            </div>

                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ $kelas->nama_kelas }}
                                            </div>

                                        </div>


                                        <button
                                            type="button"

                                            class="schedule-modal-close"

                                            x-on:click="scheduleOpen = false"
                                        >

                                            <x-filament::icon
                                                icon="heroicon-m-x-mark"
                                                class="w-5 h-5"
                                            />

                                        </button>

                                    </div>


                                    {{-- BODY --}}
                                    <div class="schedule-modal-body">

                                        @if($kelas->jadwal->isNotEmpty())

                                            <div class="schedule-list">

                                                @foreach($kelas->jadwal as $j)

                                                    <div class="schedule-item">

                                                        <div class="schedule-item-icon">

                                                            <x-filament::icon
                                                                icon="heroicon-m-clock"
                                                                class="w-4 h-4"
                                                            />

                                                        </div>


                                                        <div class="schedule-item-content">

                                                            <div class="schedule-item-day">
                                                                {{ $j->hari }}
                                                            </div>

                                                            <div class="schedule-item-time">

                                                                {{ \Illuminate\Support\Carbon::parse($j->jam_mulai)->format('H:i') }}

                                                                –

                                                                {{ \Illuminate\Support\Carbon::parse($j->jam_selesai)->format('H:i') }}

                                                            </div>

                                                        </div>

                                                    </div>

                                                @endforeach

                                            </div>

                                        @else

                                            <div class="schedule-empty">
                                                Belum ada jadwal untuk program ini.
                                            </div>

                                        @endif

                                    </div>

                                </div>

                            </div>

                        </template>

                    </div>

                @endforeach

            </div>

        @endif



        {{-- =====================================================
         * JADWAL PENGGANTI
         * ===================================================== --}}
        @if($penggantiList->isNotEmpty())

            <div class="replacement-section">

                <div class="replacement-header">

                    <h3 class="replacement-title">

                        <x-filament::icon
                            icon="heroicon-m-calendar-days"
                            class="w-5 h-5 text-primary-600 dark:text-primary-400"
                        />

                        Jadwal Pengganti

                    </h3>

                    <p class="replacement-description">
                        Input presensi dan progress hanya dapat dilakukan pada tanggal pengganti.
                    </p>

                </div>


                <div class="program-grid">

                    @foreach($penggantiList as $jp)

                        <div class="replacement-card">

                            {{-- =============================================
                            * GAMBAR
                            * ============================================= --}}
                            <div class="replacement-image">

                                <x-kelas-icon
                                    :kelas="$jp->kelas"
                                    :ukuran="5"
                                />

                            </div>


                            {{-- =============================================
                            * CONTENT
                            * ============================================= --}}
                            <div class="replacement-content">

                                {{-- NAMA KELAS --}}
                                <h3 class="replacement-name">
                                    {{ $jp->kelas?->nama_kelas ?? '-' }}
                                </h3>


                                {{-- CABANG --}}
                                <p class="replacement-branch">
                                    {{ $jp->cabang?->nama_cabang }}
                                </p>


                                {{-- TANGGAL PENGGANTI --}}
                                <div class="replacement-detail">

                                    <div class="replacement-detail-icon">

                                        <x-filament::icon
                                            icon="heroicon-m-calendar-days"
                                            class="w-4 h-4"
                                        />

                                    </div>

                                    <div>

                                        <div class="replacement-detail-label">
                                            Jadwal Pengganti
                                        </div>

                                        <div class="replacement-detail-value">
                                            {{ $jp->tanggal_pengganti->translatedFormat('l, d F Y') }}
                                        </div>

                                    </div>

                                </div>


                                {{-- JAM --}}
                                <div class="replacement-detail">

                                    <div class="replacement-detail-icon">

                                        <x-filament::icon
                                            icon="heroicon-m-clock"
                                            class="w-4 h-4"
                                        />

                                    </div>

                                    <div>

                                        <div class="replacement-detail-label">
                                            Waktu
                                        </div>

                                        <div class="replacement-detail-value">

                                            {{ substr($jp->jam_mulai, 0, 5) }}
                                            –
                                            {{ substr($jp->jam_selesai, 0, 5) }}

                                        </div>

                                    </div>

                                </div>


                                {{-- SESI YANG DIGANTIKAN --}}
                                <div class="replacement-original">

                                    <div class="replacement-original-label">
                                        Menggantikan sesi
                                    </div>

                                    <div class="replacement-original-value">

                                        {{ $jp->tanggal_seharusnya->translatedFormat('d F Y') }}

                                        <span>•</span>

                                        {{ $jp->siswa_count }} siswa terdaftar

                                    </div>

                                </div>


                                {{-- HARI INI --}}
                                @if($jp->tanggal_pengganti->isToday())

                                    <span class="today-badge">

                                        <x-filament::icon
                                            icon="heroicon-m-bolt"
                                            class="w-3.5 h-3.5"
                                        />

                                        Hari ini

                                    </span>

                                @endif


                                {{-- =============================================
                                * BUTTON MASUK
                                * ============================================= --}}
                                <a
                                    href="{{ \App\Filament\Pages\ManajemenJadwalPengganti::getUrl(['jadwalPengganti' => $jp->id]) }}"
                                    class="replacement-action"
                                >

                                    <x-filament::icon
                                        icon="heroicon-m-arrow-right"
                                        class="w-4 h-4"
                                    />

                                    Masuk

                                </a>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        @endif

    @endif

</x-filament-panels::page>