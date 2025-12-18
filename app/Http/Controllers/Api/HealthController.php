<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/health",
     *      operationId="getHealth",
     *      tags={"Health"},
     *      summary="Health check endpoint",
     *      description="Returns the health status of the API",
     *      @OA\Response(
     *          response=200,
     *          description="API is healthy",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="API is healthy"),
     *              @OA\Property(property="data", type="object"),
     *              @OA\Property(property="timestamp", type="string", example="2025-12-17T12:00:00Z")
     *          )
     *      )
     * )
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
            'data' => [
                'status' => 'running',
                'uptime' => now()->diffInSeconds(now()->subHour()),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
