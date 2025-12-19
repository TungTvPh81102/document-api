<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        private LoggerService $logger
    ) {}

    /**
     * List users
     *
     * @OA\Get(
     *   path="/api/users",
     *   tags={"Users"},
     *   summary="List users",
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $start = microtime(true);
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        $search = $request->query('search');

        try {
            $users = $search
                ? $this->userService->searchUsers($search, $page, $perPage)
                : $this->userService->getAllUsers($page, $perPage);

            $response = $this->successResponse(
                UserResource::collection($users),
                'Danh sách người dùng trong hệ thống'
            );

            $duration = microtime(true) - $start;
            $this->logger->logApiRequest($request, $response->getStatusCode(), $duration);

            return $response;
        } catch (\Exception $e) {
            $this->logger->logApiError($e, $request);
            return $this->serverErrorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * Create a user
     *
     * @OA\Post(
     *   path="/api/users",
     *   tags={"Users"},
     *   summary="Create user",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password"},
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string"),
     *       @OA\Property(property="password", type="string", format="password")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $corrId = (string) Str::orderedUuid();

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
            ]);

            $user = $this->userService->createUser($validated);

            // Set correlation ID and add HATEOAS links
            $response = $this->setCorrelationId($corrId)
                ->withLinks([
                    'self' => route('users.show', $user->code ?? $user->id),
                    'update' => route('users.update', $user->id),
                    'delete' => route('users.destroy', $user->id),
                ])
                ->createdResponse(
                    new UserResource($user),
                    'User created successfully',
                    route('users.show', $user->code ?? $user->id)
                );

            return $response;
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse(
                $e->errors(),
                'Validation failed'
            );
        } catch (\Throwable $e) {
            $this->logger->logServiceError(
                self::class,
                __FUNCTION__,
                $e,
                ['correlation_id' => $corrId]
            );

            return $this->setCorrelationId($corrId)
                ->serverErrorResponse('Failed to create user', $e);
        }
    }


    /**
     * Bulk delete users
     *
     * @OA\Post(
     *   path="/api/users/bulk-delete",
     *   tags={"Users"},
     *   summary="Bulk delete users",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"ids"},
     *       @OA\Property(property="ids", type="array", @OA\Items(type="integer"))
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function bulkDelete(Request $request)
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
                $user = \App\Models\User::findOrFail($id);
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
     *   tags={"Users"},
     *   summary="Search users",
     *   @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        $users = User::where('name', 'like', "%{$query}%")
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
     *   tags={"Users"},
     *   summary="User statistics",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'new_today' => User::whereDate('created_at', today())->count(),
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
     *   tags={"Users"},
     *   summary="Current authenticated user",
     *   @OA\Response(response=200, description="OK")
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
     *   tags={"Users"},
     *   summary="Get user by code",
     *   @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show(string $code)
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
        } catch (\Throwable $e) {
            $this->logger->logApiError($e, request());

            return $this->serverErrorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * Update user
     *
     * @OA\Put(
     *   path="/api/users/{id}",
     *   tags={"Users"},
     *   summary="Update user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string"),
     *       @OA\Property(property="password", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);
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
     *   tags={"Users"},
     *   summary="Delete user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(int $id)
    {
        $user = User::find($id);
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
     *   tags={"Users"},
     *   summary="Restore user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function restore(int $id)
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
     *   tags={"Users"},
     *   summary="Force delete user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function forceDelete(int $id)
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
     *   tags={"Users"},
     *   summary="Enable user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function enable(int $id)
    {
        $user = User::find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->enableUser($user);
        return $this->successResponse(new UserResource($user), 'User enabled');
    }

    /**
     * Disable user
     * @OA\Post(
     *   path="/api/users/{id}/disable",
     *   tags={"Users"},
     *   summary="Disable user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function disable(int $id)
    {
        $user = User::find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->disableUser($user);
        return $this->successResponse(new UserResource($user), 'User disabled');
    }

    /**
     * Lock user
     * @OA\Post(
     *   path="/api/users/{id}/lock",
     *   tags={"Users"},
     *   summary="Lock user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="seconds", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function lock(Request $request, int $id)
    {
        $user = User::find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $seconds = (int) $request->query('seconds', 3600);
        $user = $this->userService->lockUser($user, $seconds);
        return $this->successResponse(new UserResource($user), 'User locked');
    }

    /**
     * Unlock user
     * @OA\Post(
     *   path="/api/users/{id}/unlock",
     *   tags={"Users"},
     *   summary="Unlock user",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function unlock(int $id)
    {
        $user = User::find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->unlockUser($user);
        return $this->successResponse(new UserResource($user), 'User unlocked');
    }
}
