<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KK Survey Questionnaire — Batch Print</title>
    <link rel="stylesheet" href="{{ url('/modules/kabataan-monitoring/css/kkprofiling.css') }}?v={{ @filemtime(app_path('Modules/KabataanMonitoring/assets/css/kkprofiling.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ url('/modules/kabataan-monitoring/css/kk-questionnaire-view.css') }}?v={{ @filemtime(app_path('Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css')) ?: time() }}">
    <style>
        @page {
            size: Letter portrait;
            margin: 0.35in;
        }
        body {
            margin: 0;
            background: #fff;
        }
        .kk-print-sheet {
            page-break-after: always;
            break-after: page;
            page-break-inside: avoid;
            break-inside: avoid-page;
        }
        .kk-print-sheet:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        .kk-qs-scroll-wrapper,
        .kk-view-paper {
            max-width: none;
            margin: 0;
            padding: 0;
            box-shadow: none;
            border: none;
        }
        @media print {
            .kk-view-paper {
                zoom: 0.88;
            }
            .kkp-notice-body br {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    @foreach ($sheets as $html)
        <div class="kk-print-sheet">
            {!! $html !!}
        </div>
    @endforeach
</body>
</html>
