<?php

namespace App\Modules\Turnover\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TurnoverBatchTemplateService
{
    public const MAX_ROWS = 2;

    public const TEMPLATE_FILENAME = 'turnover-officers-batch-template.xlsx';

    public function downloadResponse(): BinaryFileResponse
    {
        $path = __DIR__.'/../assets/templates/'.self::TEMPLATE_FILENAME;

        if (! is_file($path)) {
            abort(404, 'Turnover batch template file is missing.');
        }

        return response()->download($path, self::TEMPLATE_FILENAME, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
