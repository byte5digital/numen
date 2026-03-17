<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Migration\MigrationDetectRequest;
use App\Models\Space;
use App\Services\Migration\CmsDetectorService;
use Illuminate\Http\JsonResponse;

class MigrationDetectController extends Controller
{
    public function __construct(
        private readonly CmsDetectorService $detector,
    ) {}

    public function detect(MigrationDetectRequest $request, Space $space): JsonResponse
    {
        $result = $this->detector->detect($request->validated('url'));

        return response()->json([
            'data' => $result,
        ]);
    }
}
