<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\SubSite\StoreSubSiteRequest;
use App\Http\Requests\SystemConsoles\SubSite\UpdateSubSiteRequest;
use App\Http\Resources\SubSiteResource;
use App\Models\SubSite;
use App\Services\SubSiteService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="SubSites",
 *   description="Sub-site management endpoints"
 * )
 */
class SubSiteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private SubSiteService $subSiteService,
    ) {}

    /**
     * List sub-sites
     *
     * @OA\Get(
     *   path="/api/sub-sites",
     *   tags={"System","SubSites"},
     *   summary="List all sub-sites with pagination",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Parameter(name="plant_id", in="query", required=false, @OA\Schema(type="integer"), description="Filter by plant"),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SubSite"))
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
            $plantId = $request->query('plant_id');

            if ($plantId) {
                $subSites = $this->subSiteService->getSubSitesByPlant((int) $plantId, $page, $perPage);
            } elseif ($search) {
                $subSites = $this->subSiteService->searchSubSites($search, $page, $perPage);
            } else {
                $subSites = $this->subSiteService->getAllSubSites($page, $perPage);
            }

            return $this->paginatedResponse($subSites, 'Sub-site list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Show sub-site detail
     *
     * @OA\Get(
     *   path="/api/sub-sites/{id}",
     *   tags={"System","SubSites"},
     *   summary="Get sub-site by ID",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", ref="#/components/schemas/SubSite")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $subSite = $this->subSiteService->getSubSiteById($id);

            if (!$subSite) {
                return $this->notFoundResponse('Sub-site not found');
            }

            return $this->successResponse(new SubSiteResource($subSite), 'Sub-site details: ' . $subSite->sub_site_name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create sub-site
     *
     * @OA\Post(
     *   path="/api/sub-sites",
     *   tags={"System","SubSites"},
     *   summary="Create a new sub-site",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"plant_id","sub_site_code","sub_site_name"},
     *       @OA\Property(property="plant_id", type="integer", example=1),
     *       @OA\Property(property="sub_site_code", type="string", example="SS-001"),
     *       @OA\Property(property="sub_site_name", type="string", example="SubSite Alpha"),
     *       @OA\Property(property="sub_site_slug", type="string", example="subsite-alpha"),
     *       @OA\Property(property="status", type="string", example="active", description="active, inactive, maintenance"),
     *       @OA\Property(property="sub_site_description", type="string", example="Secondary area A")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/SubSite"))),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StoreSubSiteRequest $request): JsonResponse
    {
        try {
            $data    = $request->validated();
            $subSite = $this->subSiteService->createSubSite($data);

            return $this->createdResponse(new SubSiteResource($subSite), 'Sub-site created successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update sub-site
     *
     * @OA\Put(
     *   path="/api/sub-sites/{id}",
     *   tags={"System","SubSites"},
     *   summary="Update an existing sub-site",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="plant_id", type="integer", example=2),
     *       @OA\Property(property="sub_site_name", type="string", example="SubSite Beta"),
     *       @OA\Property(property="status", type="string", example="maintenance")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/SubSite"))),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdateSubSiteRequest $request, string $id): JsonResponse
    {
        try {
            $subSite = SubSite::query()->find($id);

            if (!$subSite) {
                return $this->notFoundResponse('Sub-site not found');
            }

            $data    = $request->validated();
            $subSite = $this->subSiteService->updateSubSite($subSite, $data);

            return $this->successResponse(new SubSiteResource($subSite), 'Sub-site updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Delete sub-site
     *
     * @OA\Delete(
     *   path="/api/sub-sites/{id}",
     *   tags={"System","SubSites"},
     *   summary="Soft-delete a sub-site",
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
            $subSite = SubSite::query()->find($id);

            if (!$subSite) {
                return $this->notFoundResponse('Sub-site not found');
            }

            $this->subSiteService->deleteSubSite($subSite);

            return $this->successResponse(['deleted' => true], 'Sub-site deleted successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
