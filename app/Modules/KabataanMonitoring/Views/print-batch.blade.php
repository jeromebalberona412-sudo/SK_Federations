<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title></title>
    <style>
        @page { margin: 0; }
        html, body { margin: 0 !important; padding: 0 !important; }
    </style>
    @vite([
        'app/Modules/KabataanMonitoring/assets/css/kkprofiling.css',
        'app/Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css',
        'app/Modules/KabataanMonitoring/assets/css/kabataan-print-questionnaire.css',
    ])
</head>
<body class="kk-print-body" onload="window.print()">
    <div class="kk-print-root">
        @foreach ($sheets as $sheet)
            @include('kabataan_monitoring::partials.print-kk-profiling-sheet', ['sheet' => $sheet])
        @endforeach
    </div>
</body>
</html>
