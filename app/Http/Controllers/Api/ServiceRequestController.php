<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\DynamicService\Service;
use App\Models\DynamicService\ServiceRequest;
use App\Services\ServiceRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * @OA\Tag(
 *   name="Service Requests",
 *   description="Endpoints for submitting and tracking service requests"
 * )
 */
class ServiceRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ServiceRequestService $requestService
    ) {}

    /**
     * Submit a new service request.
     *
     * @OA\Post(
     *   path="/api/service-requests",
     *   tags={"Service Requests"},
     *   summary="Submit a service request",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"svc_code", "answers"},
     *       @OA\Property(property="svc_code", type="string", example="S20260212002"),
     *       @OA\Property(property="answers", type="object", example={"plant": "P1", "description": "Need access"}),
     *       @OA\Property(property="attachments", type="array", @OA\Items(type="object"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ServiceRequest")),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $svcCode = $request->input('svc_code');
            $service = Service::query()->where('svc_code', $svcCode)->first();

            if (!$service) {
                return $this->notFoundResponse('Service not found');
            }

            $requesterId = Auth::id() ?? 'system'; 
            $serviceRequest = $this->requestService->submitRequest(
                $service, 
                $request->all(), 
                $requesterId
            );

            return $this->createdResponse(
                new ServiceRequestResource($serviceRequest),
                'Service request submitted successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Get details of a specific service request.
     *
     * @OA\Get(
     *   path="/api/service-requests/{requestNo}",
     *   tags={"Service Requests"},
     *   summary="Get service request by request number",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="requestNo", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/ServiceRequest")
     *   )
     * )
     */
    public function show(string $requestNo): JsonResponse
    {
        try {
            $serviceRequest = ServiceRequest::query()
                ->where('request_no', $requestNo)
                ->with(['answers.field', 'service.translations', 'attachments'])
                ->first();

            if (!$serviceRequest) {
                return $this->notFoundResponse('Service request not found');
            }

            return $this->successResponse(
                new ServiceRequestResource($serviceRequest),
                'Service request details retrieved.'
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
