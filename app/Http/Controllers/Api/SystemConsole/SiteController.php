<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Site\StoreSiteRequest;
use App\Http\Requests\SystemConsoles\Site\UpdateSiteRequest;
use App\Models\Site;
use App\Services\siteService;
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
        private siteService $siteService,
    ) {}

    /**
     * List Sites
     *
     * @OA\Get(
     *   path="/api/Sites",
     *   tags={"System","Sites"},
     *   summary="List all Sites with pagination",
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
     *   path="/api/Sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Get Site by ID",
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

            return $this->successResponse($site, 'Site details: ' . $site->name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create Site
     *
     * @OA\Post(
     *   path="/api/Sites",
     *   tags={"System","Sites"},
     *   summary="Create a new Site",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Admin"),
     *       @OA\Property(property="slug", type="string", example="admin"),
     *       @OA\Property(property="description", type="string", example="Administrator Site"),
     *       @OA\Property(property="level", type="integer", example=1),
     *       @OA\Property(property="is_system", type="boolean", example=false)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $site = $this->siteService->createSite($data);

            return $this->createdResponse($site, 'Site created successfully');
        }  catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update Site
     *
     * @OA\Put(
     *   path="/api/Sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Update an existing Site",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Editor"),
     *       @OA\Property(property="description", type="string", example="Content editor Site"),
     *       @OA\Property(property="level", type="integer", example=2)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
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

            return $this->successResponse($site, 'Site updated successfully');
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
     *   path="/api/Sites/{id}",
     *   tags={"System","Sites"},
     *   summary="Delete a Site",
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
