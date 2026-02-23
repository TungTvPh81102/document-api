<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Plant\StorePlantRequest;
use App\Http\Requests\SystemConsoles\Plant\UpdatePlantRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Services\PlantService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="Plants",
 *   description="Plant management endpoints"
 * )
 */
class PlantController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PlantService $plantService,
    ) {}

    /**
     * List plants
     *
     * @OA\Get(
     *   path="/api/plants",
     *   tags={"System","Plants"},
     *   summary="List all plants with pagination",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Parameter(name="site_id", in="query", required=false, @OA\Schema(type="integer"), description="Filter by site"),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Plant"))
     *     )
     *   ),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $page    = (int) $request->query('page', 1);
            $search  = $request->query('search');
            $siteId  = $request->query('site_id');

            if ($siteId) {
                $plants = $this->plantService->getPlantsBySite((int) $siteId, $page, $perPage);
            } elseif ($search) {
                $plants = $this->plantService->searchPlants($search, $page, $perPage);
            } else {
                $plants = $this->plantService->getAllPlants($page, $perPage);
            }

            return $this->paginatedResponse($plants, 'Plant list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Show plant detail
     *
     * @OA\Get(
     *   path="/api/plants/{id}",
     *   tags={"System","Plants"},
     *   summary="Get plant by ID",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", ref="#/components/schemas/Plant")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $plant = $this->plantService->getPlantById($id);

            if (!$plant) {
                return $this->notFoundResponse('Plant not found');
            }

            return $this->successResponse(new PlantResource($plant), 'Plant details: ' . $plant->plant_name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create plant
     *
     * @OA\Post(
     *   path="/api/plants",
     *   tags={"System","Plants"},
     *   summary="Create a new plant",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"site_id","plant_code","plant_name"},
     *       @OA\Property(property="site_id", type="integer", example=1),
     *       @OA\Property(property="plant_code", type="string", example="PLT-001"),
     *       @OA\Property(property="plant_name", type="string", example="Plant Alpha"),
     *       @OA\Property(property="plant_slug", type="string", example="plant-alpha"),
     *       @OA\Property(property="status", type="string", example="active", description="active, inactive, maintenance"),
     *       @OA\Property(property="plant_description", type="string", example="Main production plant")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Plant"))),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StorePlantRequest $request): JsonResponse
    {
        try {
            $data  = $request->validated();
            $plant = $this->plantService->createPlant($data);

            return $this->createdResponse(new PlantResource($plant), 'Plant created successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update plant
     *
     * @OA\Put(
     *   path="/api/plants/{id}",
     *   tags={"System","Plants"},
     *   summary="Update an existing plant",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="site_id", type="integer", example=1),
     *       @OA\Property(property="plant_name", type="string", example="Plant Beta"),
     *       @OA\Property(property="status", type="string", example="inactive")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Plant"))),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdatePlantRequest $request, string $id): JsonResponse
    {
        try {
            $plant = Plant::query()->find($id);

            if (!$plant) {
                return $this->notFoundResponse('Plant not found');
            }

            $data  = $request->validated();
            $plant = $this->plantService->updatePlant($plant, $data);

            return $this->successResponse(new PlantResource($plant), 'Plant updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Delete plant
     *
     * @OA\Delete(
     *   path="/api/plants/{id}",
     *   tags={"System","Plants"},
     *   summary="Soft-delete a plant",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function delete(string $id): JsonResponse
    {
        try {
            $plant = Plant::query()->find($id);

            if (!$plant) {
                return $this->notFoundResponse('Plant not found');
            }

            $this->plantService->deletePlant($plant);

            return $this->successResponse(['deleted' => true], 'Plant deleted successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
