<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Report Siswa</title>
    @include('reports.partials.report-siswa-style')
</head>
<body>
    @foreach ($reports as $report)
        <div class="rs-doc" @if (! $loop->last) style="page-break-after: always;" @endif>
            @include('reports.partials.report-siswa-body', ['data' => $report->data, 'report' => $report])
        </div>
    @endforeach
</body>
</html>
