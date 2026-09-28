<x-filament-panels::page.simple>
    <style>
        main:has(.login-split) {
            width: 100% !important;
            max-width: 60rem !important;
            padding: 0 !important;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35) !important;
        }
        .fi-simple-layout {
            background-color: rgb(var(--gray-900));
            background-image:
                linear-gradient(rgba(15, 23, 42, .45), rgba(15, 23, 42, .45)),
                url('{{ asset('images/login-bg.png') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .fi-simple-main {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35) !important;
        }

        .login-split {
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .login-split { grid-template-columns: 1fr 1fr; }
        }

        .login-brand {
            display: none;
            position: relative;
            overflow: hidden;
            padding: 3rem;
            color: #fff;
            background: linear-gradient(135deg, rgb(var(--gray-900)) 0%, rgb(var(--primary-700)) 100%);
        }

        @media (min-width: 768px) {
            .login-brand {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
        }

        .login-brand::before,
        .login-brand::after {
            content: '';
            position: absolute;
            border-radius: 9999px;
            background: rgb(var(--primary-500));
            opacity: .18;
        }

        .login-brand::before { width: 18rem; height: 18rem; top: -6rem; right: -6rem; }
        .login-brand::after  { width: 12rem; height: 12rem; bottom: -4rem; left: -3rem; }

        .login-brand > * { position: relative; z-index: 1; }

        .login-brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .login-brand-title {
            font-size: 1.875rem;
            line-height: 1.2;
            font-weight: 700;
            margin-bottom: .75rem;
        }

        .login-brand-text {
            font-size: .95rem;
            opacity: .85;
            line-height: 1.6;
        }

        .login-brand-points {
            list-style: none;
            margin: 1.5rem 0 0;
            padding: 0;
            display: grid;
            gap: .6rem;
            font-size: .875rem;
        }

        .login-brand-points li::before {
            content: '✓';
            display: inline-block;
            margin-right: .6rem;
            font-weight: 700;
            color: rgb(var(--primary-400));
        }

        .login-brand-footer {
            font-size: .75rem;
            opacity: .6;
        }

        .login-form-side {
            padding: 2.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .login-form-side { padding: 3.5rem 3rem; }
        }

        .login-form-head { margin-bottom: 1.75rem; }

        .login-form-head h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: rgb(var(--gray-950));
        }

        .login-form-head p {
            margin-top: .35rem;
            font-size: .9rem;
            color: rgb(var(--gray-500));
        }

        .dark .login-form-head h1 { color: #fff; }
    </style>

    <div class="login-split">
        {{-- Panel kiri: branding --}}
        <div class="login-brand">
            <div class="login-brand-name">{{ config('app.name') }}</div>

            <div>
                <h2 class="login-brand-title">Kelola semua cabang dalam satu tempat.</h2>
                <p class="login-brand-text">
                    Pantau presensi, jadwal, dan perkembangan siswa dengan mudah dan terintegrasi.
                </p>

                <ul class="login-brand-points">
                    <li>Presensi karyawan & guru</li>
                    <li>Jadwal kelas dan program</li>
                    <li>Progress dan nilai siswa</li>
                </ul>
            </div>

            <div class="login-brand-footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </div>
        </div>

        {{-- Panel kanan: form login --}}
        <div class="login-form-side">
            <div class="login-form-head">
                <h1>Selamat datang</h1>
                <p>Masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <x-filament-panels::form wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>
        </div>
    </div>
</x-filament-panels::page.simple>