{{-- Dipakai oleh ViewEntry di ReportSiswaResource::infolist(). Membaca snapshot saja. --}}
@php $report = $getRecord(); @endphp

@include('reports.partials.report-siswa-style')

<div class="rs-screen">
    <div class="rs-doc">
        @include('reports.partials.report-siswa-body', ['data' => $report->data, 'report' => $report])
    </div>
</div>
