<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\User\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Tag(
     *   name="Users",
     *   description="User management endpoints"
     * )
     */
    public function __construct(
        private UserService $userService,
    ) {}

    /**
     * List users
     *
     * @OA\Get(
     *   path="/api/users",
     *   tags={"System","Users"},
     *   summary="List users",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserListResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $page    = (int) $request->query('page', 1);
            $search  = $request->query('search');

            $users = $search
                ? $this->userService->searchUsers($search, $page, $perPage)
                : $this->userService->getAllUsers($page, $perPage);

            return $this->paginatedResponse($users, 'User list retrieved successfully.');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }


    /**
     * Create a user
     *
     * @OA\Post(
     *   path="/api/users",
     *   tags={"System","Users"},
     *   summary="Create user",
     *   security={{"bearerAuth": {}}},
     *   description="Create a new user. To upload an avatar, use multipart/form-data.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password","password_confirmation"},
     *       @OA\Property(property="name", type="string", maxLength=255, example="John Doe", description="User name, max 255 chars"),
     *       @OA\Property(property="email", type="string", format="email", maxLength=255, example="user@example.com", description="Unique email"),
     *       @OA\Property(property="password", type="string", format="password", minLength=8, example="P@ssw0rd!", description="Password, min 8 chars"),
     *       @OA\Property(property="password_confirmation", type="string", format="password", minLength=8, example="P@ssw0rd!", description="Password confirmation, must match password"),
     *       @OA\Property(property="phone", type="string", maxLength=20, example="+84901234567", description="Unique phone number, max 20 chars"),
     *       @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Date of birth, YYYY-MM-DD"),
     *       @OA\Property(property="gender", type="string", example="male", description="Gender: male, female, other"),
     *       @OA\Property(property="enabled", type="boolean", example=true, description="Enabled status")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $this->userService->createUser($data);

            return $this->withLinks([
                'self'   => route('users.show', $user->code ?? $user->id),
                'update' => route('users.update', $user->id),
                'delete' => route('users.destroy', $user->id),
            ])
                ->createdResponse(
                    new UserResource($user),
                    'User created successfully',
                    route('users.show', $user->code ?? $user->id)
                );
        } catch (Throwable $e) {
            return $this->serverErrorResponse('Failed to create user', $e);
        }
    }


    /**
     * Bulk delete users
     *
     * @OA\Post(
     *   path="/api/users/bulk-delete",
     *   tags={"System","Users"},
     *   summary="Bulk delete users",
     *   security={{"bearerAuth": {}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"ids"},
     *       @OA\Property(property="ids", type="array", @OA\Items(type="integer"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/BulkOperationResponse")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:users,id',
        ]);

        $successful = 0;
        $failed = 0;
        $results = [];

        foreach ($validated['ids'] as $id) {
            try {
                $user = User::query()->findOrFail($id);
                $this->userService->deleteUser($user);
                $successful++;
                $results[] = [
                    'id' => $id,
                    'status' => 'success',
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'id' => $id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->bulkOperationResponse(
            $successful,
            $failed,
            $results,
            'delete'
        );
    }

    /**
     * Search users (quick)
     *
     * @OA\Get(
     *   path="/api/users/search",
     *   tags={"System","Users"},
     *   summary="Search users",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserCollectionResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');

        $users = User::query()->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(20)
            ->get();

        return $this->collectionResponse($users, 'Search results');
    }

    /**
     * Example 10: Custom metadata response
     */
    /**
     * Get statistics
     *
     * @OA\Get(
     *   path="/api/users/stats",
     *   tags={"System","Users"},
     *   summary="User statistics",
     *   security={{"bearerAuth": {}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserStatsResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('enable', true)->count(),
            'new_today' => User::query()->whereDate('created_at', today())->count(),
        ];

        return $this->withMeta([
            'generated_at' => now()->toISOString(),
            'cached' => false,
            'cache_ttl' => 300,
        ])
            ->successResponse($stats, 'User statistics');
    }

    /**
     * Get current user
     *
     * @OA\Get(
     *   path="/api/user",
     *   tags={"System","Users"},
     *   summary="Current authenticated user",
     *   security={{"bearerAuth": {}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/CurrentUserResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        return $this->successResponse(
            $user ? new UserResource($user) : null,
            'Current user'
        );
    }

    /**
     * Show user by code
     *
     * @OA\Get(
     *   path="/api/users/{code}",
     *   tags={"System","Users"},
     *   summary="Get user by code",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function show(string $code): JsonResponse
    {
        try {
            $user = $this->userService->getUserByCode($code);

            if (!$user) {
                return $this->notFoundResponse('User not found', 'User');
            }

            return $this->successResponse(
                new UserResource($user),
                'User retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Update user
     *
     * @OA\Put(
     *   path="/api/users/{id}",
     *   tags={"System","Users"},
     *   summary="Update user",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="password", type="string", format="password")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) {
            return $this->notFoundResponse('User not found', 'User');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|min:8',
        ]);

        $user = $this->userService->updateUser($user, $validated);
        return $this->successResponse(new UserResource($user), 'User updated');
    }

    /**
     * Delete user
     *
     * @OA\Delete(
     *   path="/api/users/{id}",
     *   tags={"System","Users"},
     *   summary="Delete user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/DeleteResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) {
            return $this->notFoundResponse('User not found', 'User');
        }
        $this->userService->deleteUser($user);
        return $this->successResponse(['deleted' => true], 'User deleted');
    }

    /**
     * Restore user
     *
     * @OA\Post(
     *   path="/api/users/{id}/restore",
     *   tags={"System","Users"},
     *   summary="Restore user",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return $this->notFoundResponse('User not found', 'User');
        }
        $this->userService->restoreUser($user);
        return $this->successResponse(new UserResource($user->fresh()), 'User restored');
    }

    /**
     * Force delete user
     *
     * @OA\Delete(
     *   path="/api/users/{id}/force",
     *   tags={"System","Users"},
     *   summary="Force delete user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/DeleteResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return $this->notFoundResponse('User not found', 'User');
        }
        $this->userService->forceDeleteUser($user);
        return $this->successResponse(['deleted' => true], 'User permanently deleted');
    }

    /**
     * Enable user
     * @OA\Post(
     *   path="/api/users/{id}/enable",
     *   tags={"System","Users"},
     *   summary="Enable user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function enable(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->enableUser($user);
        return $this->successResponse(new UserResource($user), 'User enabled');
    }

    /**
     * Disable user
     * @OA\Post(
     *   path="/api/users/{id}/disable",
     *   tags={"System","Users"},
     *   summary="Disable user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function disable(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->disableUser($user);
        return $this->successResponse(new UserResource($user), 'User disabled');
    }

    /**
     * Lock user
     * @OA\Post(
     *   path="/api/users/{id}/lock",
     *   tags={"System","Users"},
     *   summary="Lock user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="seconds", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $seconds = (int)$request->query('seconds', 3600);
        $user = $this->userService->lockUser($user, $seconds);
        return $this->successResponse(new UserResource($user), 'User locked');
    }

    /**
     * Unlock user
     * @OA\Post(
     *   path="/api/users/{id}/unlock",
     *   tags={"System","Users"},
     *   summary="Unlock user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function unlock(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->unlockUser($user);
        return $this->successResponse(new UserResource($user), 'User unlocked');
    }

    /**
     * Sync roles for a user
     *
     * @OA\Post(
     *   path="/api/users/{id}/roles",
     *   tags={"System","Users"},
     *   summary="Sync roles for a user (replaces all existing roles)",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"role_ids"},
     *       @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), example={1,2})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function syncRoles(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::query()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            $validated = $request->validate([
                'role_ids'   => 'required|array',
                'role_ids.*' => 'integer|exists:roles,id',
            ]);

            $user = $this->userService->syncRoles($user, $validated['role_ids']);

            return $this->successResponse(new UserResource($user), 'User roles synced successfully');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
