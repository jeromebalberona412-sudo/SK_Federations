<?php

namespace App\Modules\BarangayLogos\Services;

use App\Services\CloudinaryService as BaseCloudinaryService;

class CloudinaryService extends BaseCloudinaryService
{
    public function __construct()
    {
        config([
            'services.cloudinary.folder' => config(
                'services.cloudinary.barangay_logos_folder',
                'sk_oneportal/barangay_logos'
            ),
        ]);

        parent::__construct();
    }
}
