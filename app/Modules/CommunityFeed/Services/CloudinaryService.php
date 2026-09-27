<?php

namespace App\Modules\CommunityFeed\Services;

use App\Services\CloudinaryService as BaseCloudinaryService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryService extends BaseCloudinaryService
{
    private const FOLDER = 'SK_Federations_Post';

    /**
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function upload(UploadedFile $file, string $publicId, bool $invalidate = false): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not configured.');
        }

        $options = [
            'folder'                       => self::FOLDER,
            'public_id'                    => $publicId,
            'overwrite'                    => false,
            'use_filename'                 => false,
            'unique_filename'              => false,
            'use_filename_as_display_name' => true,
            'use_asset_folder_as_public_id_prefix' => false,
            'resource_type'                => 'image',
        ];

        if ($invalidate) {
            $options['invalidate'] = true;
        }

        $result = $this->uploadImageAsset(
            $file->getRealPath() ?: $file->getPathname(),
            $options,
            $file->getClientOriginalName()
        );
        $version = isset($result['version']) ? (int) $result['version'] : null;

        return [
            'public_id' => $result['public_id'],
            'url'       => $this->deliverUrl($result['public_id'], $version),
            'version'   => $version,
        ];
    }
}
