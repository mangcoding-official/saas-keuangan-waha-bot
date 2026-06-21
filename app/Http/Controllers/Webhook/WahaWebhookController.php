<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Waha\WahaWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WahaWebhookController extends Controller
{
    public function __construct(
        private readonly WahaWebhookService $wahaWebhookService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->wahaWebhookService->handle($request->all());

        return response()->json($result);
    }
}
