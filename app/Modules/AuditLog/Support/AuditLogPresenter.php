<?php

namespace App\Modules\AuditLog\Support;

use App\Modules\AuditLog\Models\AdminActivityLog;
use App\Modules\Shared\Models\User;
use Carbon\CarbonInterface;

class AuditLogPresenter
{
    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            User::ROLE_SK_FED => 'SK Federation',
            User::ROLE_ADMIN, 'super_admin', 'SUPER_ADMIN' => 'Admin',
            User::ROLE_SK_OFFICIAL => 'SK Official',
            User::ROLE_USER => 'Kabataan',
            null, '' => 'System',
            default => ucwords(str_replace('_', ' ', strtolower($role))),
        };
    }

    public static function portalFromRole(?string $role): string
    {
        return match ($role) {
            User::ROLE_SK_FED => 'sk_federation',
            User::ROLE_ADMIN, 'super_admin', 'SUPER_ADMIN' => 'admin',
            User::ROLE_SK_OFFICIAL => 'sk_officials',
            User::ROLE_USER => 'kabataan',
            default => 'system',
        };
    }

    public static function moduleLabel(?string $module, ?string $eventType = null): string
    {
        if (is_string($module) && $module !== '') {
            return ucwords(str_replace(['_', '-'], ' ', $module));
        }

        if (! is_string($eventType) || $eventType === '') {
            return 'General';
        }

        $prefix = str_contains($eventType, '.') ? explode('.', $eventType, 2)[0] : $eventType;

        return ucwords(str_replace(['_', '-'], ' ', $prefix));
    }

    public static function actionLabel(?string $action, ?string $eventType = null): string
    {
        $value = $action ?: $eventType;

        if (! is_string($value) || $value === '') {
            return '-';
        }

        if (str_contains($value, '.')) {
            $value = explode('.', $value, 2)[1];
        }

        return strtoupper(str_replace('_', ' ', $value));
    }

    public static function eventTypeLabel(?string $eventType): string
    {
        if (! is_string($eventType) || $eventType === '') {
            return '-';
        }

        $value = str_contains($eventType, '.') ? explode('.', $eventType, 2)[1] : $eventType;

        return ucwords(str_replace('_', ' ', $value));
    }

    public static function resolveUserName(?string $name, ?string $email, array $metadata = []): string
    {
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        if (is_string($metadata['user_name'] ?? null) && trim($metadata['user_name']) !== '') {
            return trim($metadata['user_name']);
        }

        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        if (is_string($metadata['email'] ?? null) && trim($metadata['email']) !== '') {
            return trim($metadata['email']);
        }

        return 'System';
    }

    public static function resolveBarangayName(?string $barangayName, array $metadata = []): string
    {
        if (is_string($barangayName) && trim($barangayName) !== '') {
            return trim($barangayName);
        }

        if (is_string($metadata['barangay_name'] ?? null) && trim($metadata['barangay_name']) !== '') {
            return trim($metadata['barangay_name']);
        }

        return '-';
    }

    public static function relativeTime(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        return $timestamp->diffForHumans(['short' => false, 'parts' => 1]);
    }

    public static function formatDateTime(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        return $timestamp->timezone(config('app.timezone', 'Asia/Manila'))->format('Y-m-d h:i A');
    }

    public static function formatDate(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        return $timestamp->timezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y');
    }

    public static function formatTime(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        return $timestamp->timezone(config('app.timezone', 'Asia/Manila'))->format('h:i A');
    }

    /**
     * @return array{device: string, browser: string, os: string, label: string}
     */
    public static function parseDeviceDetails(?string $userAgent): array
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return [
                'device' => 'Unknown',
                'browser' => 'Unknown',
                'os' => 'Unknown',
                'label' => 'Unknown',
            ];
        }

        $agent = strtolower($userAgent);
        $browser = self::parseBrowserName($agent);
        $os = self::parseOperatingSystem($agent);
        $device = self::parseDeviceType($agent);

        return [
            'device' => $device,
            'browser' => $browser,
            'os' => $os,
            'label' => "{$browser} on {$os}",
        ];
    }

    public static function parseBrowser(?string $userAgent): string
    {
        return self::parseDeviceDetails($userAgent)['label'];
    }

    private static function parseBrowserName(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'brave') => 'Brave',
            str_contains($agent, 'edg/') => 'Microsoft Edge',
            str_contains($agent, 'opr/') || str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'firefox/') => 'Mozilla Firefox',
            str_contains($agent, 'chrome/') && ! str_contains($agent, 'edg/') => 'Google Chrome',
            str_contains($agent, 'safari/') && ! str_contains($agent, 'chrome/') => 'Safari',
            default => 'Unknown Browser',
        };
    }

    private static function parseOperatingSystem(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'mac os') || str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone') || str_contains($agent, 'ipad') => 'iOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Unknown OS',
        };
    }

    private static function parseDeviceType(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'ipad') || str_contains($agent, 'tablet') => 'Tablet',
            str_contains($agent, 'mobile') || str_contains($agent, 'iphone') || str_contains($agent, 'android') => 'Mobile',
            default => 'Desktop',
        };
    }

    /**
     * @param  object|array<string, mixed>  $log
     * @return array<string, mixed>
     */
    public static function toArray(object|array $log): array
    {
        $row = is_array($log) ? (object) $log : $log;
        $metadata = self::normalizeMetadata($row->metadata ?? null);

        $relatedUser = property_exists($row, 'user') && $row->user ? $row->user : null;

        $userRole = $relatedUser?->role ?? ($row->user_role ?? ($metadata['role'] ?? null));
        $userName = self::resolveUserName(
            $relatedUser?->name ?? ($row->user_name ?? null),
            $relatedUser?->email ?? ($row->user_email ?? null),
            $metadata,
        );
        $barangayName = self::resolveBarangayName(
            $relatedUser?->barangay?->name ?? ($row->barangay_name ?? null),
            $metadata,
        );
        $module = $metadata['module'] ?? null;
        $createdAt = $row->created_at ?? null;
        $deviceDetails = self::parseDeviceDetails($row->user_agent ?? null);

        if (is_string($createdAt)) {
            $createdAt = \Illuminate\Support\Carbon::parse($createdAt);
        }

        return [
            'id' => (string) ($row->id ?? ''),
            'created_at' => self::formatDateTime($createdAt),
            'created_date' => self::formatDate($createdAt),
            'created_time' => self::formatTime($createdAt),
            'created_at_iso' => $createdAt?->toIso8601String(),
            'relative_time' => self::relativeTime($createdAt),
            'user_id' => $row->user_id ?? null,
            'user_name' => $userName,
            'user_email' => $relatedUser?->email ?? ($row->user_email ?? ($metadata['email'] ?? null)),
            'role' => self::roleLabel(is_string($userRole) ? $userRole : null),
            'role_key' => $userRole,
            'barangay' => $barangayName,
            'barangay_id' => $relatedUser?->barangay_id ?? ($row->barangay_id ?? ($metadata['barangay_id'] ?? null)),
            'event_type' => self::eventTypeLabel($row->event_type ?? null),
            'event_type_key' => $row->event_type ?? null,
            'action' => self::actionLabel($row->action ?? null, $row->event_type ?? null),
            'action_key' => $row->action ?? null,
            'module' => self::moduleLabel(is_string($module) ? $module : null, $row->event_type ?? null),
            'module_key' => $module,
            'entity_type' => $row->entity_type ?: ($metadata['entity_type'] ?? '-'),
            'entity_id' => $row->entity_id ?: ($metadata['entity_id'] ?? '-'),
            'ip_address' => $row->ip_address ?? '-',
            'user_agent' => $row->user_agent ?? null,
            'browser' => $deviceDetails['label'],
            'device_type' => $deviceDetails['device'],
            'device_browser' => $deviceDetails['browser'],
            'device_os' => $deviceDetails['os'],
            'metadata' => $metadata,
            'summary' => self::activitySummary($row, $metadata),
            'is_security' => AdminActivityLog::isSecurityEvent($row->event_type ?? null),
        ];
    }

    /**
     * @param  object|array<string, mixed>  $log
     */
    public static function activitySummary(object|array $log, ?array $metadata = null): string
    {
        $row = is_array($log) ? (object) $log : $log;
        $metadata ??= self::normalizeMetadata($row->metadata ?? null);

        $actorRole = self::roleLabel($row->user_role ?? ($metadata['role'] ?? null));
        $portal = $metadata['portal'] ?? null;

        if (! is_string($portal) || $portal === '') {
            $portal = self::portalFromRole($row->user_role ?? ($metadata['role'] ?? null));
        }

        $portalLabel = match ($portal) {
            'admin' => 'Admin',
            'sk_federation', 'sk_fed' => 'Federation',
            'sk_officials', 'sk_official' => 'SK Official',
            'kabataan', 'user' => 'Kabataan',
            default => $actorRole !== 'System' ? $actorRole : 'System',
        };

        $action = strtolower((string) ($row->action ?? ''));
        $eventType = strtolower((string) ($row->event_type ?? ''));
        $entityType = strtolower((string) ($row->entity_type ?? ($metadata['entity_type'] ?? '')));

        $verb = match (true) {
            str_contains($action, 'create') || str_contains($eventType, 'create') || str_contains($eventType, 'submitted') => 'created',
            str_contains($action, 'update') || str_contains($eventType, 'update') || str_contains($eventType, 'updated') => 'updated',
            str_contains($action, 'delete') || str_contains($eventType, 'delete') => 'deleted',
            str_contains($action, 'restore') || str_contains($eventType, 'restore') => 'restored',
            str_contains($action, 'archive') || str_contains($eventType, 'archive') => 'archived',
            str_contains($action, 'approve') || str_contains($eventType, 'approve') => 'approved',
            str_contains($action, 'reject') || str_contains($eventType, 'reject') => 'rejected',
            str_contains($action, 'login') || str_contains($eventType, 'login_success') => 'logged in',
            str_contains($action, 'logout') || $eventType === 'logout' => 'logged out',
            str_contains($eventType, 'failed') => 'failed login attempt for',
            str_contains($eventType, 'password') => 'updated password for',
            str_contains($eventType, 'verified') => 'verified',
            str_contains($eventType, 'close') => 'closed',
            str_contains($eventType, 'generate') => 'generated',
            default => 'performed',
        };

        $object = match (true) {
            str_contains($entityType, 'scholarship') => 'scholarship application',
            str_contains($entityType, 'sport') => 'sports application',
            str_contains($entityType, 'survey') => 'survey',
            str_contains($entityType, 'announcement') => 'announcement',
            str_contains($entityType, 'event') => 'event',
            str_contains($entityType, 'abyip') || str_contains($entityType, 'program') => 'ABYIP program',
            str_contains($entityType, 'kk') || str_contains($entityType, 'profiling') => 'KK profiling',
            str_contains($entityType, 'user') || str_contains($entityType, 'account') => 'account',
            str_contains($entityType, 'barangay_logo') || str_contains($entityType, 'logo') => 'barangay logo',
            str_contains($entityType, 'report') => 'report',
            str_contains($entityType, 'settings') => 'system settings',
            str_contains($entityType, 'record') => 'record',
            default => $entityType !== '' ? str_replace('_', ' ', $entityType) : 'activity',
        };

        if ($verb === 'failed login attempt for' || $verb === 'updated password for') {
            return "{$portalLabel} {$verb} {$object}";
        }

        if ($verb === 'logged in' || $verb === 'logged out') {
            return "{$portalLabel} {$verb}";
        }

        return "{$portalLabel} {$verb} {$object}";
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
