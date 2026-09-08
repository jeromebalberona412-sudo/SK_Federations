<?php

namespace App\Modules\Accounts\Controllers;

use App\Models\InvalidEmailAddress;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Controllers\Controller;
use App\Services\InvalidEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvalidEmailController extends Controller
{
    public function __construct(
        private readonly InvalidEmailService $invalidEmails,
        private readonly AuditLogInterface $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $query = InvalidEmailAddress::query()->orderByDesc('updated_at');

        if ($status !== '' && in_array($status, [
            InvalidEmailAddress::STATUS_ACTIVE,
            InvalidEmailAddress::STATUS_TEMPORARILY_INVALID,
            InvalidEmailAddress::STATUS_PERMANENTLY_BLOCKED,
            InvalidEmailAddress::STATUS_VERIFIED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('LOWER(normalized_email) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(last_failure_reason, \'\')) like ?', [$like]);
            });
        }

        return view('accounts::invalid-emails', [
            'records' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'search' => $search,
            'reasons' => config('invalid_email.failure_reasons', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email'],
            'reason' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = $this->invalidEmails->markTemporarilyInvalid(
            $validated['email'],
            $validated['reason'],
            $validated['notes'] ?? null
        );

        $this->auditLog->log('email_temporarily_blocked', $request->user(), [
            'email' => $record?->normalized_email,
            'reason' => $validated['reason'],
            'source' => 'admin_manual',
        ]);

        return back()->with('success', 'Invalid email recorded as temporarily invalid.');
    }

    public function reset(InvalidEmailAddress $invalidEmail): RedirectResponse
    {
        $this->invalidEmails->resetToActive($invalidEmail->normalized_email);
        $this->auditLog->log('email_status_reset', request()->user(), [
            'email' => $invalidEmail->normalized_email,
        ]);

        return back()->with('success', 'Email status reset. It can be used again.');
    }

    public function permanentlyBlock(Request $request, InvalidEmailAddress $invalidEmail): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->invalidEmails->permanentlyBlock(
            $invalidEmail->normalized_email,
            $validated['reason'] ?? 'other',
            $validated['notes'] ?? null
        );

        $this->auditLog->log('email_permanently_blocked', $request->user(), [
            'email' => $invalidEmail->normalized_email,
        ]);

        return back()->with('success', 'Email permanently blocked.');
    }

    public function destroy(InvalidEmailAddress $invalidEmail): RedirectResponse
    {
        $email = $invalidEmail->normalized_email;
        $this->invalidEmails->remove($email);
        $this->auditLog->log('email_status_reset', request()->user(), [
            'email' => $email,
            'action' => 'removed',
        ]);

        return back()->with('success', 'Invalid email record removed.');
    }
}
