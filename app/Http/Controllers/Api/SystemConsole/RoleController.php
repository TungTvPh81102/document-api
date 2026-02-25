<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Role\StoreRoleRequest;
use App\Http\Requests\SystemConsoles\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="Roles",
 *   description="Role management endpoints"
 * )
 */
class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RoleService $roleService,
    ) {}

    /**
     * List roles
     *
     * @OA\Get(
     *   path="/api/roles",
     *   tags={"System","Roles"},
     *   summary="List all roles with pagination",
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
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Role"))
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

            $roles = $search
                ? $this->roleService->searchRoles($search, $page, $perPage)
                : $this->roleService->getAllRoles($page, $perPage);

            return $this->paginatedResponse($roles, 'Role list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Show role detail
     *
     * @OA\Get(
     *   path="/api/roles/{id}",
     *   tags={"System","Roles"},
     *   summary="Get role by ID",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="data", ref="#/components/schemas/Role")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            return $this->successResponse(new RoleResource($role), 'Role details: ' . $role->name);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Create role
     *
     * @OA\Post(
     *   path="/api/roles",
     *   tags={"System","Roles"},
     *   summary="Create a new role",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Admin"),
     *       @OA\Property(property="slug", type="string", example="admin"),
     *       @OA\Property(property="description", type="string", example="Administrator role"),
     *       @OA\Property(property="level", type="integer", example=1),
     *       @OA\Property(property="is_system", type="boolean", example=false)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $role = $this->roleService->createRole($data);

            return $this->createdResponse(new RoleResource($role), 'Role created successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update role
     *
     * @OA\Put(
     *   path="/api/roles/{id}",
     *   tags={"System","Roles"},
     *   summary="Update an existing role",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Editor"),
     *       @OA\Property(property="description", type="string", example="Content editor role"),
     *       @OA\Property(property="level", type="integer", example=2)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        try {
            $role = Role::query()->find($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            $data = $request->validated();
            $role = $this->roleService->updateRole($role, $data);

            return $this->successResponse(new RoleResource($role), 'Role updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Delete role
     *
     * @OA\Delete(
     *   path="/api/roles/{id}",
     *   tags={"System","Roles"},
     *   summary="Delete a role",
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
            $role = Role::query()->find($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            $this->roleService->deleteRole($role);

            return $this->successResponse(['deleted' => true], 'Role deleted successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Sync permissions for a role
     *
     * @OA\Post(
     *   path="/api/roles/{id}/permissions",
     *   tags={"System","Roles"},
     *   summary="Sync permissions for a role (replaces all existing permissions)",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"permission_ids"},
     *       @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        try {
            $role = Role::query()->find($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            $validated = $request->validate([
                'permission_ids'   => 'required|array',
                'permission_ids.*' => 'integer|exists:permissions,id',
            ]);

            $role = $this->roleService->syncPermissions($role, $validated['permission_ids']);

            return $this->successResponse(new RoleResource($role), 'Role permissions synced successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
