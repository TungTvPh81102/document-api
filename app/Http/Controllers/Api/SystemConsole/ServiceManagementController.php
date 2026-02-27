<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\DynamicService\Service;
use App\Models\DynamicService\ServiceCategory;
use App\Services\FormEngineService;
use App\Services\ServiceManagementService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @OA\Tag(
 *   name="Admin Services",
 *   description="Administrative endpoints for managing service templates"
 * )
 */
class ServiceManagementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ServiceManagementService $managementService,
        private FormEngineService $formEngine
    ) {}

    /**
     * Ingest a raw upstream JSON payload to create or update a service.
     *
     * @OA\Post(
     *   path="/api/admin/services/ingest",
     *   tags={"Admin Services"},
     *   summary="Ingest raw JSON to create/update service",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"raw_payload", "business_category_id"},
     *       @OA\Property(property="raw_payload", type="object", description="The JSON from upstream system"),
     *       @OA\Property(property="business_category_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/Service"))
     * )
     */
    public function ingest(Request $request): JsonResponse
    {
        try {
            $rawPayload = $request->input('raw_payload');
            $businessCategoryId = $request->input('business_category_id');

            if (!$rawPayload) {
                return $this->validationErrorResponse(['raw_payload' => ['Payload is required']]);
            }

            // 1. Standardize
            $canonical = $this->formEngine->standardizePayload($rawPayload);

            // 2. Check if service exists
            $service = Service::query()->where('svc_code', $canonical['service']['svcCode'])->first();

            if ($service) {
                // Update: Create new form version
                $version = $this->managementService->createFormVersion($service, $canonical['form']['fields']);
                $message = 'Service form updated to version ' . $version->version_no;
            } else {
                // Create: New service and form template
                $service = $this->managementService->createServiceWithForm($canonical, $businessCategoryId);
                $message = 'New service created successfully';
            }

            return $this->successResponse(
                new ServiceResource($service->load(['translations', 'currentVersion.fields'])),
                $message
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
