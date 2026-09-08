<?php

namespace App\Services;

use App\Modules\Shared\Models\User;
use App\Rules\ValidEmailAddress;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmailValidationService
{
    public function __construct(
        private readonly InvalidEmailService $invalidEmails,
    ) {}

    public function normalize(string $email): string
    {
        return $this->invalidEmails->normalize($email);
    }

    public function isValidFormat(string $email): bool
    {
        return $this->formatFailureMessage($email) === null;
    }

    /**
     * Returns null when format + disposable checks pass; otherwise the user-facing message.
     */
    public function formatFailureMessage(string $email): ?string
    {
        $normalized = $this->normalize($email);
        if ($normalized === '') {
            return ValidEmailAddress::MSG_FORMAT;
        }

        $validator = Validator::make(
            ['email' => $normalized],
            ['email' => ['required', 'string', 'max:'.ValidEmailAddress::MAX_LENGTH, new ValidEmailAddress]]
        );

        if (! $validator->fails()) {
            return null;
        }

        $message = (string) $validator->errors()->first('email');

        return $message !== '' ? $message : ValidEmailAddress::MSG_FORMAT;
    }

    /**
     * Full pre-send gate: format + disposable + optional duplicate + invalid email DB.
     *
     * @return array{
     *     allowed: bool,
     *     status: string,
     *     message: string|null,
     *     retry_after: \Carbon\CarbonInterface|null,
     *     reason: string|null,
     *     normalized_email: string
     * }
     */
    public function checkEmailBeforeSending(
        string $email,
        bool $checkDuplicate = true,
        ?Authenticatable $exceptUser = null,
        string $duplicateMessage = 'This email address is already associated with another account.'
    ): array {
        $normalized = $this->normalize($email);

        $formatMessage = $this->formatFailureMessage($normalized);
        if ($formatMessage !== null) {
            $isDisposable = $formatMessage === ValidEmailAddress::MSG_DISPOSABLE;

            return [
                'allowed' => false,
                'status' => $isDisposable ? 'disposable' : 'invalid_format',
                'message' => $formatMessage,
                'retry_after' => null,
                'reason' => $isDisposable ? 'disposable' : 'invalid_format',
                'normalized_email' => $normalized,
            ];
        }

        if ($checkDuplicate && $this->emailBelongsToAnotherAccount($normalized, $exceptUser)) {
            return [
                'allowed' => false,
                'status' => 'duplicate',
                'message' => $duplicateMessage,
                'retry_after' => null,
                'reason' => 'duplicate',
                'normalized_email' => $normalized,
            ];
        }

        $invalidCheck = $this->invalidEmails->checkBeforeSending($normalized);

        return [
            'allowed' => (bool) $invalidCheck['allowed'],
            'status' => (string) $invalidCheck['status'],
            'message' => $invalidCheck['message'],
            'retry_after' => $invalidCheck['retry_after'],
            'reason' => $invalidCheck['reason'],
            'normalized_email' => $normalized,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSend(
        string $email,
        bool $checkDuplicate = true,
        ?Authenticatable $exceptUser = null,
        string $field = 'new_email'
    ): string {
        $result = $this->checkEmailBeforeSending($email, $checkDuplicate, $exceptUser);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                $field => [$result['message'] ?? 'This email address cannot be used.'],
            ]);
        }

        return $result['normalized_email'];
    }

    public function emailBelongsToAnotherAccount(string $email, ?Authenticatable $exceptUser = null): bool
    {
        $normalized = $this->normalize($email);
        $query = User::query()->whereRaw('LOWER(email) = ?', [$normalized]);

        if ($exceptUser !== null && isset($exceptUser->id)) {
            $query->whereKeyNot((int) $exceptUser->id);
        }

        if ($query->exists()) {
            return true;
        }

        $pending = User::query()->whereRaw('LOWER(pending_email) = ?', [$normalized]);
        if ($exceptUser !== null && isset($exceptUser->id)) {
            $pending->whereKeyNot((int) $exceptUser->id);
        }

        return $pending->exists();
    }
}
