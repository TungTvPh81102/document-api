<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Site\StoreSiteRequest;
use App\Http\Requests\SystemConsoles\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="Sites",
 *   description="Site management endpoints"
 * )
 */
class SiteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private SiteService $siteService,
    ) {}

    /**
     * List Sites
     *
     * @OA\Get(
     *   path="/api/sites",
     *   tags={"System","Sites"},
     *   summary="List all sites with pagination",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Site"))
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

            $sites = $search
                ? $this->siteService->searchSites($search, $page, $perPage)
                : $this->siteService->getAllSites($page, $perPage);

            return $this->paginatedResponse($sites, 'Site list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Show Site detail
     *
     * @OA\Get(
     *   path="/api/sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Get site by ID",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", ref="#/components/schemas/Site")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $site = $this->siteService->getSiteById($id);

            if (!$site) {
                return $this->notFoundResponse('Site not found');
            }

            return $this->successResponse(new SiteResource($site), 'Site details: ' . $site->name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create Site
     *
     * @OA\Post(
     *   path="/api/sites",
     *   tags={"System","Sites"},
     *   summary="Create a new site",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Main Factory"),
     *       @OA\Property(property="code", type="string", example="FAC-01"),
     *       @OA\Property(property="slug", type="string", example="main-factory"),
     *       @OA\Property(property="status", type="string", example="active"),
     *       @OA\Property(property="description", type="string", example="Primary production site"),
     *       @OA\Property(property="location", type="string", example="Ho Chi Minh"),
     *       @OA\Property(property="timezone", type="string", example="Asia/Ho_Chi_Minh")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Site"))),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $site = $this->siteService->createSite($data);

            return $this->createdResponse(new SiteResource($site), 'Site created successfully');
        }  catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update Site
     *
     * @OA\Put(
     *   path="/api/sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Update an existing site",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Updated Site Name"),
     *       @OA\Property(property="status", type="string", example="inactive")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Site"))),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        try {
            $site = Site::query()->find($id);

            if (!$site) {
                return $this->notFoundResponse('Site not found');
            }

            $data = $request->validated();
            $site = $this->siteService->updateSite($site, $data);

            return $this->successResponse(new SiteResource($site), 'Site updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Delete Site
     *
     * @OA\Delete(
     *   path="/api/sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Delete a site",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $site = Site::query()->find($id);

            if (!$site) {
                return $this->notFoundResponse('Site not found');
            }

            $this->siteService->deleteSite($site);

            return $this->successResponse(['deleted' => true], 'Site deleted successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
