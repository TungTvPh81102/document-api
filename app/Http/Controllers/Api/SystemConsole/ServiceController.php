<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\ServiceResource;
use App\Models\DynamicService\Service;
use App\Models\DynamicService\ServiceCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @OA\Tag(
 *   name="Services",
 *   description="Dynamic Service discovery and form definition endpoints"
 * )
 */
class ServiceController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all service categories and their services.
     *
     * @OA\Get(
     *   path="/api/services/listing",
     *   tags={"Services"},
     *   summary="List all services grouped by category",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ServiceCategory"))
     *     )
     *   )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $categories = ServiceCategory::query()
                ->where('is_active', true)
                ->with(['services' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->get();

            return $this->successResponse(
                ServiceCategoryResource::collection($categories),
                'Service listing retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Get service details and current form schema.
     *
     * @OA\Get(
     *   path="/api/services/{svcCode}/form",
     *   tags={"Services"},
     *   summary="Get current form schema for a service",
     *   @OA\Parameter(name="svcCode", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/Service")
     *   ),
     *   @OA\Response(response=404, description="Service not found")
     * )
     */
    public function show(string $svcCode): JsonResponse
    {
        try {
            $service = Service::query()
                ->where('svc_code', $svcCode)
                ->with(['currentVersion.fields.translations', 'translations'])
                ->first();

            if (!$service) {
                return $this->notFoundResponse('Service not found');
            }

            return $this->successResponse(
                new ServiceResource($service),
                'Service form schema retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
