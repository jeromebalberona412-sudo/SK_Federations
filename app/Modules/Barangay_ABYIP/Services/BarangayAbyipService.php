<?php

namespace App\Modules\Barangay_ABYIP\Services;

use App\Models\Abyip;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BarangayAbyipService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listSubmissions(): Collection
    {
        if (! Schema::hasTable('abyip')) {
            return collect();
        }

        return Abyip::query()
            ->documents()
            ->with(['barangay:id,name', 'creator:id,name', 'creator.officialProfile:id,user_id,position'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Abyip $document) => $this->formatSubmission($document));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $document = $this->findDocument($id);

        return $this->formatSubmission($document, includeContent: true);
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function pdfFile(int $id): array
    {
        $document = $this->findDocument($id);

        if ($document->source_type !== 'pdf' || ! filled($document->pdf_data)) {
            throw ValidationException::withMessages([
                'document' => ['PDF file not found for this submission.'],
            ]);
        }

        $binary = $this->decodePdfBinary((string) $document->pdf_data);

        if ($binary === null) {
            throw ValidationException::withMessages([
                'document' => ['Stored PDF data is invalid or corrupted.'],
            ]);
        }

        $filename = Str::slug($document->document_title ?: 'abyip-'.$document->fiscal_year).'.pdf';

        return [
            'content' => $binary,
            'filename' => $filename,
        ];
    }

    public function approve(User $reviewer, int $id): array
    {
        $document = $this->findDocument($id);
        $this->assertPending($document);

        DB::transaction(function () use ($document, $reviewer) {
            $document->update([
                'status' => Abyip::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => null,
            ]);
        });

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    public function reject(User $reviewer, int $id, string $reason): array
    {
        $document = $this->findDocument($id);
        $this->assertPending($document);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        DB::transaction(function () use ($document, $reviewer, $reason) {
            $document->update([
                'status' => Abyip::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);
        });

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    public function revoke(User $reviewer, int $id, string $reason): array
    {
        $document = $this->findDocument($id);

        if (strtolower((string) ($document->status ?? '')) !== Abyip::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'document' => ['Only approved ABYIP submissions can be revoked.'],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A revoke reason is required.'],
            ]);
        }

        DB::transaction(function () use ($document, $reviewer, $reason) {
            $document->update([
                'status' => Abyip::STATUS_PENDING,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'rejection_reason' => null,
            ]);
        });

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    private function findDocument(int $id): Abyip
    {
        $document = Abyip::query()
            ->documents()
            ->with(['barangay:id,name', 'creator:id,name', 'creator.officialProfile:id,user_id,position'])
            ->find($id);

        if ($document === null) {
            throw ValidationException::withMessages([
                'document' => ['ABYIP submission not found.'],
            ]);
        }

        return $document;
    }

    private function assertPending(Abyip $document): void
    {
        if ((string) ($document->status ?? Abyip::STATUS_PENDING) !== Abyip::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'document' => ['Only pending ABYIP submissions can be reviewed.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubmission(Abyip $document, bool $includeContent = false): array
    {
        $creator = $document->creator;
        $submittedBy = $creator?->name ?: '—';

        $status = strtolower((string) ($document->status ?? Abyip::STATUS_PENDING));

        return [
            'id' => $document->id,
            'title' => $document->document_title,
            'barangay' => $document->barangay?->name ?? $document->barangay_name ?? '—',
            'barangay_id' => $document->barangay_id,
            'date_submitted' => $document->created_at?->format('M j, Y') ?? '—',
            'date_submitted_raw' => $document->created_at?->toDateString(),
            'submitted_by' => $submittedBy !== '' ? $submittedBy : '—',
            'submitted_by_role' => $this->formatOfficialPosition($creator?->officialProfile?->position),
            'submitted_time' => $document->created_at?->format('g:i A') ?? '—',
            'fiscal_year' => $document->fiscal_year,
            'status' => $status,
            'status_label' => ucfirst($status),
            'source_type' => $document->source_type,
            'rejection_reason' => $document->rejection_reason,
            'document_html' => $includeContent ? $document->document_html : null,
            'file_url' => $includeContent && filled($document->pdf_data)
                ? url('/api/barangay-abyip/'.$document->id.'/file')
                : null,
            'has_pdf' => filled($document->pdf_data),
            'has_html' => filled($document->document_html),
        ];
    }

    private function decodePdfBinary(string $rawPdfData): ?string
    {
        $raw = trim($rawPdfData);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'data:')) {
            $commaPos = strpos($raw, ',');
            if ($commaPos === false) {
                return null;
            }

            $raw = substr($raw, $commaPos + 1);
        }

        $binary = base64_decode($raw, true);

        return $binary === false ? null : $binary;
    }

    private function formatOfficialPosition(?string $position): ?string
    {
        if ($position === null || trim($position) === '') {
            return null;
        }

        return match ($position) {
            'Chairperson' => 'SK Chairperson',
            'Secretary' => 'SK Secretary',
            'Treasurer' => 'SK Treasurer',
            'Kagawad' => 'SK Kagawad',
            default => $position,
        };
    }
}
