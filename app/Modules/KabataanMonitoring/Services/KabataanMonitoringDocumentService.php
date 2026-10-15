<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class KabataanMonitoringDocumentService
{
    private const DISK = 'local';

    public function __construct(
        private readonly KabataanMonitoringService $monitoring,
        private readonly AuditLogInterface $auditLog,
    ) {}

    /**
     * @return array{filename:string,size:int,uploaded_at:string}
     */
    public function upload(User $user, int $recordId, UploadedFile $file): array
    {
        $record = $this->authorizedRecord($user, $recordId);
        $this->assertPdfContent($file);

        $directory = $this->directoryFor($record);
        $storedName = Str::uuid().'.pdf';
        $newPath = $file->storeAs($directory, $storedName, self::DISK);
        if (! is_string($newPath) || $newPath === '') {
            throw new \RuntimeException('The PDF could not be stored. Please retry the upload.');
        }

        $formData = is_array($record->form_data) ? $record->form_data : [];
        $previous = $formData['monitoring_document'] ?? null;
        $uploadedAt = now();
        $document = [
            'path' => $newPath,
            'filename' => $this->safeOriginalFilename($file),
            'size' => (int) $file->getSize(),
            'mime_type' => 'application/pdf',
            'uploaded_at' => $uploadedAt->toIso8601String(),
        ];
        $formData['monitoring_document'] = $document;

        try {
            $record->update(['form_data' => $formData]);
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPath);
            throw $exception;
        }

        $previousPath = is_array($previous) ? ($previous['path'] ?? null) : null;
        if (is_string($previousPath) && $this->isRecordPath($previousPath, $record)) {
            if (! Storage::disk(self::DISK)->delete($previousPath)) {
                Log::warning('Unable to remove replaced Kabataan monitoring document.', [
                    'registration_id' => (int) $record->id,
                ]);
            }
        }

        $this->auditLog->log('kabataan_registration.document_uploaded', $user, [
            'action' => 'document_upload',
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $record->id,
            'module' => 'kabataan_monitoring',
            'barangay_id' => (int) $record->barangay_id,
            'file_size' => $document['size'],
        ]);
        $this->monitoring->forgetListCache($record->barangay?->name, (int) $user->tenant_id);

        return [
            'filename' => $document['filename'],
            'size' => $document['size'],
            'uploaded_at' => $document['uploaded_at'],
        ];
    }

    public function response(User $user, int $recordId, bool $download = false, bool $forReview = false): Response
    {
        $record = $this->authorizedRecord($user, $recordId, $forReview);
        $formData = is_array($record->form_data) ? $record->form_data : [];
        $document = $formData['monitoring_document'] ?? null;
        $path = is_array($document) ? ($document['path'] ?? null) : null;

        abort_unless(
            is_string($path)
                && $this->isRecordPath($path, $record)
                && Storage::disk(self::DISK)->exists($path),
            404,
            'The PDF document is unavailable.'
        );

        $filename = $this->safeStoredFilename($document['filename'] ?? 'kabataan-monitoring.pdf');
        $headers = [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($download) {
            return Storage::disk(self::DISK)->download($path, $filename, $headers);
        }

        return Storage::disk(self::DISK)->response($path, $filename, $headers, 'inline');
    }

    /**
     * @return array{filename:string,size:int,uploaded_at:?string}|null
     */
    public function metadata(User $user, int $recordId, bool $forReview = false): ?array
    {
        $record = $this->authorizedRecord($user, $recordId, $forReview);
        $formData = is_array($record->form_data) ? $record->form_data : [];
        $document = $formData['monitoring_document'] ?? null;
        $path = is_array($document) ? ($document['path'] ?? null) : null;

        if (! is_string($path) || ! $this->isRecordPath($path, $record) || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return [
            'filename' => $this->safeStoredFilename($document['filename'] ?? 'kabataan-monitoring.pdf'),
            'size' => max(0, (int) ($document['size'] ?? 0)),
            'uploaded_at' => isset($document['uploaded_at']) ? (string) $document['uploaded_at'] : null,
        ];
    }

    private function authorizedRecord(User $user, int $recordId, bool $forReview = false): KabataanRegistration
    {
        abort_unless($user->canAccessFederationPortal() && (int) $user->tenant_id > 0, 403);

        $record = $forReview
            ? $this->monitoring->findSubmissionForReview($recordId, (int) $user->tenant_id)
            : $this->monitoring->find($recordId, (int) $user->tenant_id);
        abort_if($record === null, 404, 'Kabataan record not found.');

        return $record;
    }

    private function assertPdfContent(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        $handle = is_string($realPath) ? fopen($realPath, 'rb') : false;
        $header = $handle === false ? false : fread($handle, 1024);
        if (is_resource($handle)) {
            fclose($handle);
        }

        if (
            $header === false
            || $file->getMimeType() !== 'application/pdf'
            || strpos($header, '%PDF-') === false
        ) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded document must contain a valid PDF file.'],
            ]);
        }
    }

    private function directoryFor(KabataanRegistration $record): string
    {
        return sprintf(
            'kabataan-monitoring/%d/%d/%d',
            (int) $record->tenant_id,
            (int) $record->barangay_id,
            (int) $record->id
        );
    }

    private function isRecordPath(string $path, KabataanRegistration $record): bool
    {
        return str_starts_with($path, $this->directoryFor($record).'/')
            && ! str_contains($path, '..');
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        $filename = trim($filename);

        return mb_substr($filename !== '' ? $filename : 'kabataan-monitoring.pdf', 0, 255);
    }

    private function safeStoredFilename(mixed $filename): string
    {
        $name = basename(str_replace('\\', '/', (string) $filename));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return trim($name) !== '' ? mb_substr(trim($name), 0, 255) : 'kabataan-monitoring.pdf';
    }
}
