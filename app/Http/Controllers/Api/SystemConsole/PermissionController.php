<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Permission\StorePermissionRequest;
use App\Http\Requests\SystemConsoles\Permission\UpdatePermissionRequest;
use App\Models\Permission;
use App\Services\PermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="Permissions",
 *   description="Permission management endpoints"
 * )
 */
class PermissionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PermissionService $permissionService,
    ) {}

    /**
     * List permissions
     *
     * @OA\Get(
     *   path="/api/permissions",
     *   tags={"System","Permissions"},
     *   summary="List all permissions with pagination",
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Permission"))
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

            $permissions = $search
                ? $this->permissionService->searchPermissions($search, $page, $perPage)
                : $this->permissionService->getAllPermissions($page, $perPage);

            return $this->paginatedResponse($permissions, 'Permission list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Show permission detail
     *
     * @OA\Get(
     *   path="/api/permissions/{id}",
     *   tags={"System","Permissions"},
     *   summary="Get permission by ID",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", ref="#/components/schemas/Permission")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $permission = $this->permissionService->getPermissionById($id);

            if (!$permission) {
                return $this->notFoundResponse('Permission not found');
            }

            return $this->successResponse($permission, 'Permission details: ' . $permission->name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create permission
     *
     * @OA\Post(
     *   path="/api/permissions",
     *   tags={"System","Permissions"},
     *   summary="Create a new permission",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","action","resource"},
     *       @OA\Property(property="name", type="string", example="View Users"),
     *       @OA\Property(property="slug", type="string", example="view-users"),
     *       @OA\Property(property="action", type="string", example="view", description="view, create, edit, delete"),
     *       @OA\Property(property="resource", type="string", example="users"),
     *       @OA\Property(property="description", type="string", example="Permission to view user list"),
     *       @OA\Property(property="is_system", type="boolean", example=false)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $data       = $request->validated();
            $permission = $this->permissionService->createPermission($data);

            return $this->createdResponse($permission, 'Permission created successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update permission
     *
     * @OA\Put(
     *   path="/api/permissions/{id}",
     *   tags={"System","Permissions"},
     *   summary="Update an existing permission",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Manage Users"),
     *       @OA\Property(property="action", type="string", example="edit"),
     *       @OA\Property(property="resource", type="string", example="users"),
     *       @OA\Property(property="description", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdatePermissionRequest $request, string $id): JsonResponse
    {
        try {
            $permission = Permission::query()->find($id);

            if (!$permission) {
                return $this->notFoundResponse('Permission not found');
            }

            $data       = $request->validated();
            $permission = $this->permissionService->updatePermission($permission, $data);

            return $this->successResponse($permission, 'Permission updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Delete permission
     *
     * @OA\Delete(
     *   path="/api/permissions/{id}",
     *   tags={"System","Permissions"},
     *   summary="Delete a permission",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function delete(string $id): JsonResponse
    {
        try {
            $permission = Permission::query()->find($id);

            if (!$permission) {
                return $this->notFoundResponse('Permission not found');
            }

            $this->permissionService->deletePermission($permission);

            return $this->successResponse(['deleted' => true], 'Permission deleted successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
