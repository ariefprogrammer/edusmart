<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Report {{ $report->siswa_nama }}</title>
    @include('reports.partials.report-siswa-style')
</head>
<body>
    <div class="rs-doc">
        @include('reports.partials.report-siswa-body', ['data' => $data, 'report' => $report])
    </div>
</body>
</html>
