<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;

final readonly class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }
}
