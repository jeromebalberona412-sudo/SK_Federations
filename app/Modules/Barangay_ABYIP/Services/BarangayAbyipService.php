<?php

namespace App\Modules\Barangay_ABYIP\Services;

use App\Models\Abyip;
use App\Modules\Shared\Models\User;
use App\Services\AbyipApprovalProvisioningService;
use App\Services\SkFedPageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BarangayAbyipService
{
    public function __construct(
        private readonly AbyipApprovalProvisioningService $approvalProvisioningService,
    ) {}

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
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'draft');
            })
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

        if (! filled($document->pdf_data)) {
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

        $programCount = Abyip::query()
            ->where('document_id', $document->id)
            ->where(function ($query): void {
                $query->where('row_type', 'youth_program')
                    ->orWhereIn('code', ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']);
            })
            ->count();

        if ($programCount === 0 && ! empty($document->pdf_data)) {
            throw ValidationException::withMessages([
                'document' => [
                    'Cannot approve this ABYIP yet: no youth programs were extracted from the uploaded PDF. '
                    .'Ask the barangay SK Officials to re-upload a text-based ABYIP PDF (or run abyip:reparse), then try again.',
                ],
            ]);
        }

        DB::transaction(function () use ($document, $reviewer) {
            $document->update([
                'status' => Abyip::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => null,
            ]);
        });

        $document->refresh(['barangay:id,name']);
        $this->approvalProvisioningService->provision($document);
        app(SkFedPageCache::class)->bump('barangay.monitoring.index');

        return $this->formatSubmission($document);
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
            $payload = [
                'status' => Abyip::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => $reason,
            ];

            if (Schema::hasColumn('abyip', 'rejected_at')) {
                $payload['rejected_at'] = now();
            }

            $document->update($payload);
        });

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listRejected(int $retentionDays = 30): Collection
    {
        if (! Schema::hasTable('abyip')) {
            return collect();
        }

        return Abyip::query()
            ->documents()
            ->where('status', Abyip::STATUS_REJECTED)
            ->with(['barangay:id,name', 'creator:id,name'])
            ->orderByDesc(Schema::hasColumn('abyip', 'rejected_at') ? 'rejected_at' : 'reviewed_at')
            ->get()
            ->map(fn (Abyip $document) => $this->formatRejected($document, $retentionDays));
    }

    /**
     * @return array<string, mixed>
     */
    public function showRejected(int $id, int $retentionDays = 30): array
    {
        $document = $this->findRejectedDocument($id);

        return $this->formatRejected($document, $retentionDays, includeContent: true);
    }

    public function restoreRejected(User $actor, int $id): array
    {
        $document = $this->findRejectedDocument($id);

        DB::transaction(function () use ($document) {
            $payload = [
                'status' => Abyip::STATUS_PENDING,
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
            ];

            if (Schema::hasColumn('abyip', 'rejected_at')) {
                $payload['rejected_at'] = null;
            }

            $document->update($payload);
        });

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    public function permanentlyDeleteRejected(User $actor, int $id): void
    {
        $document = $this->findRejectedDocument($id);

        DB::transaction(function () use ($document) {
            if (Schema::hasColumn('abyip', 'document_id')) {
                Abyip::query()
                    ->where('document_id', $document->id)
                    ->delete();
            }

            $document->delete();
        });

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
    }

    public function purgeExpiredRejected(int $days = 30): int
    {
        if (! Schema::hasTable('abyip') || ! Schema::hasColumn('abyip', 'rejected_at')) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $expiredIds = Abyip::query()
            ->documents()
            ->where('status', Abyip::STATUS_REJECTED)
            ->whereNotNull('rejected_at')
            ->where('rejected_at', '<=', $cutoff)
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            return 0;
        }

        $deleted = 0;

        DB::transaction(function () use ($expiredIds, &$deleted) {
            if (Schema::hasColumn('abyip', 'document_id')) {
                Abyip::query()
                    ->whereIn('document_id', $expiredIds)
                    ->delete();
            }

            $deleted = Abyip::query()
                ->whereIn('id', $expiredIds)
                ->delete();
        });

        if ($deleted > 0) {
            app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        }

        return (int) $deleted;
    }

    private function findRejectedDocument(int $id): Abyip
    {
        $document = Abyip::query()
            ->documents()
            ->where('status', Abyip::STATUS_REJECTED)
            ->with(['barangay:id,name', 'creator:id,name'])
            ->find($id);

        if ($document === null) {
            throw ValidationException::withMessages([
                'document' => ['Rejected ABYIP submission not found.'],
            ]);
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRejected(Abyip $document, int $retentionDays = 30, bool $includeContent = false): array
    {
        $barangayName = $document->barangay?->name ?? $document->barangay_name ?? '—';
        $rejectedAt = $document->rejected_at ?? $document->reviewed_at;
        $daysRemaining = $this->rejectedDaysRemaining($rejectedAt, $retentionDays);
        $daysTier = $this->rejectedDaysTier($daysRemaining);
        $creator = $document->creator;
        $submittedBy = $creator?->name ?: '—';

        return [
            'id' => $document->id,
            'title' => $document->document_title ?: ('ABYIP '.$document->fiscal_year),
            'barangay_name' => $barangayName,
            'barangay_slug' => $barangayName !== '—' ? Str::slug($barangayName) : null,
            'calendar_year' => $document->fiscal_year,
            'fiscal_year' => $document->fiscal_year,
            'rejected_at' => $rejectedAt?->toIso8601String(),
            'rejection_reason' => $document->rejection_reason,
            'submitted_by' => $submittedBy !== '' ? $submittedBy : '—',
            'days_remaining' => $daysRemaining,
            'days_tier' => $daysTier,
            'auto_delete_label' => $daysRemaining === 0
                ? 'Auto delete today'
                : 'Auto delete in '.$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's'),
            'file_url' => filled($document->pdf_data)
                ? url('/api/barangay-abyip/'.$document->id.'/file')
                : null,
            'document_html' => $includeContent ? $document->document_html : null,
            'has_pdf' => filled($document->pdf_data),
            'has_html' => filled($document->document_html),
        ];
    }

    private function rejectedDaysRemaining(?Carbon $rejectedAt, int $retentionDays): int
    {
        if ($rejectedAt === null) {
            return 0;
        }

        $expiresAt = $rejectedAt->copy()->addDays($retentionDays);

        return max(0, (int) now()->diffInDays($expiresAt, false));
    }

    private function rejectedDaysTier(int $daysRemaining): string
    {
        if ($daysRemaining <= 3) {
            return 'red';
        }

        if ($daysRemaining <= 7) {
            return 'orange';
        }

        return 'green';
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

        DB::transaction(function () use ($document) {
            $payload = [
                'status' => Abyip::STATUS_PENDING,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'rejection_reason' => null,
            ];

            if (Schema::hasColumn('abyip', 'rejected_at')) {
                $payload['rejected_at'] = null;
            }

            $document->update($payload);
        });

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');

        return $this->formatSubmission($document->fresh(['barangay', 'creator']));
    }

    private function findDocument(int $id): Abyip
    {
        $document = Abyip::query()
            ->documents()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'draft');
            })
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
