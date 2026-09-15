<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    private string $folder;

    public function __construct()
    {
        $this->folder = (string) config('services.cloudinary.folder', 'sk_oneportal/uploads');
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    /**
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function upload(UploadedFile $file, string $publicId, bool $invalidate = false): array
    {
        $this->ensureConfigured();

        $options = [
            'public_id' => $this->folder.'/'.$publicId,
            'overwrite' => true,
            'resource_type' => 'image',
        ];

        if ($invalidate) {
            $options['invalidate'] = true;
        }

        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            $options
        );

        $version = isset($result['version']) ? (int) $result['version'] : null;

        return [
            'public_id' => $result['public_id'],
            'url' => $this->deliverUrl($result['public_id'], $version),
            'version' => $version,
        ];
    }

    /**
     * Upload a communication messenger image into the shared `communication` folder.
     *
     * @return array{public_id: string, url: string, version: int|null}
     */
    /**
     * Upload a Community Feed video using the configured unsigned/signed preset options.
     *
     * @return array{public_id: string, url: string, bytes: int|null, mime_type: string|null, duration: float|null}
     */
    public function uploadCommunityVideo(UploadedFile $file): array
    {
        $this->ensureConfigured();

        $preset = trim((string) config('services.cloudinary.community_video_upload_preset', 'SK_Federations_community_video'));
        $path = $file->getRealPath() ?: $file->getPathname();

        $options = [
            'resource_type' => 'video',
            'upload_preset' => $preset !== '' ? $preset : 'SK_Federations_community_video',
        ];

        $result = $this->cloudinary->uploadApi()->upload($path, $options);
        $url = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        return [
            'public_id' => (string) ($result['public_id'] ?? ''),
            'url' => $url,
            'bytes' => isset($result['bytes']) ? (int) $result['bytes'] : null,
            'mime_type' => isset($result['format']) ? 'video/'.strtolower((string) $result['format']) : $file->getMimeType(),
            'duration' => isset($result['duration']) ? (float) $result['duration'] : null,
        ];
    }

    public function uploadCommunicationImage(UploadedFile $file): array
    {
        $this->ensureConfigured();

        $folder = trim((string) config('services.cloudinary.communication_folder', 'communication'), '/');
        $preset = trim((string) config('services.cloudinary.communication_upload_preset', ''));
        $path = $file->getRealPath() ?: $file->getPathname();
        $displayName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $options = [
            'folder' => $folder !== '' ? $folder : 'communication',
            'resource_type' => 'image',
            'overwrite' => false,
            'use_filename' => false,
            'unique_filename' => false,
            'display_name' => $displayName !== '' ? $displayName : 'communication_image',
        ];

        // Preserve uploaded format; only force svg when the asset is intentionally an SVG.
        if ($ext === 'svg' && (bool) config('communications.attachments.allow_svg', false)) {
            $options['format'] = 'svg';
        }

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);
        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl((string) $result['public_id'], $version);
        }

        return [
            'public_id' => (string) ($result['public_id'] ?? ''),
            'url' => $deliveryUrl,
            'version' => $version,
        ];
    }

    /**
     * Upload default group-chat avatar using Avatar_Profile_GroupChats preset.
     *
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function uploadGroupChatAvatar(UploadedFile $file): array
    {
        $this->ensureConfigured();

        $folder = trim((string) config('services.cloudinary.group_chat_avatar_folder', 'Avatar_Profile_GroupChats'), '/');
        $preset = trim((string) config('services.cloudinary.group_chat_avatar_upload_preset', 'Avatar_Profile_GroupChats'));
        $path = $file->getRealPath() ?: $file->getPathname();
        $displayName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);

        $options = [
            'folder' => $folder !== '' ? $folder : 'Avatar_Profile_GroupChats',
            'resource_type' => 'image',
            'overwrite' => false,
            'use_filename' => false,
            // Unique public ids so the President can change the photo unlimited times.
            'unique_filename' => true,
            'use_filename_as_display_name' => true,
            'display_name' => $displayName !== '' ? $displayName : 'group_chat_avatar',
            'type' => 'upload',
        ];

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);
        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl((string) $result['public_id'], $version);
        }

        return [
            'public_id' => (string) ($result['public_id'] ?? ''),
            'url' => self::cacheBust($deliveryUrl, now()),
            'version' => $version,
        ];
    }

    public function delete(string $publicId): void
    {
        $this->ensureConfigured();
        $this->cloudinary->uploadApi()->destroy($publicId, ['invalidate' => true]);
    }

    public function deliverUrl(string $publicId, ?int $version = null): string
    {
        $this->ensureConfigured();

        $image = $this->cloudinary
            ->image($publicId)
            ->format('auto')
            ->quality('auto');

        if ($version && $version > 0) {
            $image->version($version);
        }

        return (string) $image->toUrl();
    }

    public function normalizeUrl(?string $url): ?string
    {
        if (! $url || ! $this->isConfigured()) {
            return $url;
        }

        if (! str_contains($url, 'res.cloudinary.com')) {
            return $url;
        }

        $publicId = $this->extractPublicIdFromUrl($url);

        return $publicId
            ? $this->deliverUrl($publicId, $this->extractVersionFromUrl($url))
            : $url;
    }

    public function extractPublicIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, '/sk_oneportal/')) {
            return null;
        }

        if (! preg_match('#(/sk_oneportal/.+)$#', $path, $matches)) {
            return null;
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '', ltrim($matches[1], '/')) ?: null;
    }

    public function extractVersionFromUrl(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('#/v(\d+)/#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function cacheBust(string $url, DateTimeInterface|string|int|null $updatedAt = null): string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return $url;
        }

        $token = match (true) {
            $updatedAt instanceof DateTimeInterface => $updatedAt->getTimestamp(),
            is_int($updatedAt) => $updatedAt,
            is_string($updatedAt) && $updatedAt !== '' => strtotime($updatedAt) ?: time(),
            default => time(),
        };

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'cb='.$token;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in .env.'
            );
        }
    }
}
