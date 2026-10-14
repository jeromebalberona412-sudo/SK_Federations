@extends('layout::app')

@section('title', 'KK Profiling Submission - SK OnePortal')

@push('main-class')
    km-main
@endpush

@push('styles')
    @vite([
        'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring.css',
        'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring-responsive.css',
        'app/Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css',
    ])
@endpush

@section('content')
    <div class="km-container km-review-detail-page">
        <a class="km-review-all-link" href="{{ $backUrl }}">← Back to KK Profiling</a>
        <section class="km-review-detail-card">
            <div class="km-review-heading">
                <div>
                    <p class="km-review-eyebrow">KK Profiling submission</p>
                    <h1>{{ $record->full_name }}</h1>
                    <p>{{ $barangayName ?? '—' }} · Submitted {{ $record->submitted_at?->format('M j, Y') ?? '—' }}</p>
                </div>
                <span class="km-review-status km-review-status--{{ strtolower($statusLabel) }}">{{ $statusLabel }}</span>
            </div>
            @if($record->profiling_year)
                <p class="km-review-year">Profiling year: {{ $record->profiling_year }}</p>
            @endif
            @if($statusLabel === 'Rejected' && $record->rejection_reason)
                <p class="km-review-rejection-reason"><strong>Rejection reason:</strong> {{ $record->rejection_reason }}</p>
            @endif
        </section>

        <section class="km-review-detail-card" aria-labelledby="kmReviewQuestionnaireTitle">
            <h2 id="kmReviewQuestionnaireTitle">Submission details</h2>
            {!! $questionnaireHtml !!}
        </section>

        <section class="km-review-detail-card" aria-labelledby="kmReviewDocumentTitle">
            <h2 id="kmReviewDocumentTitle">Supporting document</h2>
            @if($document)
                <p>{{ $document['filename'] }} @if($document['uploaded_at']) · Uploaded {{ \Illuminate\Support\Carbon::parse($document['uploaded_at'])->format('M j, Y') }} @endif</p>
                <div class="km-review-actions">
                    <a class="km-review-button km-review-button--secondary" href="{{ route('api.kabataan-monitoring.review-document', ['id' => $record->id]) }}" target="_blank" rel="noopener">View PDF</a>
                    <a class="km-review-button km-review-button--secondary" href="{{ route('api.kabataan-monitoring.review-document.download', ['id' => $record->id]) }}">Download PDF</a>
                </div>
            @else
                <p>No supporting PDF is attached to this submission.</p>
            @endif
        </section>
    </div>
@endsection
