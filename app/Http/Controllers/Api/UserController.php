<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Systems\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

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
        private UserService   $userService,
        private LoggerService $logger
    )
    {
    }

    /**
     * List users
     *
     * @OA\Get(
     *   path="/api/users",
     *   tags={"System","Users"},
     *   summary="List users",
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
     *   tags={"System","Users"},
     *   summary="Create user",
     *   description="Tạo mới người dùng. Nếu cần upload ảnh đại diện (avatar), sử dụng content-type multipart/form-data.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password","password_confirmation"},
     *       @OA\Property(property="name", type="string", maxLength=255, example="Nguyễn Văn A", description="Tên người dùng, tối đa 255 ký tự"),
     *       @OA\Property(property="email", type="string", format="email", maxLength=255, example="user@example.com", description="Email duy nhất, định dạng hợp lệ"),
     *       @OA\Property(property="password", type="string", format="password", minLength=8, example="P@ssw0rd!", description="Mật khẩu, tối thiểu 8 ký tự"),
     *       @OA\Property(property="password_confirmation", type="string", format="password", minLength=8, example="P@ssw0rd!", description="Xác nhận mật khẩu, phải khớp với password"),
     *       @OA\Property(property="phone", type="string", maxLength=20, example="+84901234567", description="Số điện thoại duy nhất, tối đa 20 ký tự"),
     *       @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Ngày sinh, định dạng YYYY-MM-DD"),
     *       @OA\Property(property="gender", type="string", example="male", description="Giới tính: male, female, other"),
     *       @OA\Property(property="enabled", type="boolean", example=true, description="Trạng thái kích hoạt (true/false)")
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
    public function store(StoreUserRequest $request)
    {
        $corrId = (string)Str::orderedUuid();

        try {
            $data = $request->validated();
            $user = $this->userService->createUser($data);

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
                $e->errors()
            );
        } catch (\Throwable $e) {
            $this->logger->logApiError($e, $request);
            return $this->setCorrelationId($corrId)
                ->serverErrorResponse('Failed to create user', $e);
        }
    }


    /**
     * Bulk delete users
     *
     * @OA\Post(
     *   path="/api/users/bulk-delete",
     *   tags={"System","Users"},
     *   summary="Bulk delete users",
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
     *   tags={"System","Users"},
     *   summary="Search users",
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
     *   tags={"System","Users"},
     *   summary="User statistics",
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
     *   tags={"System","Users"},
     *   summary="Current authenticated user",
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
     *   tags={"System","Users"},
     *   summary="Update user",
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
     *   tags={"System","Users"},
     *   summary="Restore user",
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
    public function lock(Request $request, int $id)
    {
        $user = User::find($id);
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
    public function unlock(int $id)
    {
        $user = User::find($id);
        if (!$user) return $this->notFoundResponse('User not found', 'User');
        $user = $this->userService->unlockUser($user);
        return $this->successResponse(new UserResource($user), 'User unlocked');
    }
}


/**
 * @OA\Schema(
 *   schema="ApiResponse",
 *   type="object",
 *   required={"success","message","code","timestamp"},
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="Operation successful"),
 *   @OA\Property(property="code", type="integer", example=200),
 *   @OA\Property(property="data"),
 *   @OA\Property(property="errors", type="object", nullable=true, example=null),
 *   @OA\Property(property="correlation_id", type="string", nullable=true, example="01JDN3X6H1V5C0Y8K9P2R4M7TQ"),
 *   @OA\Property(property="links", type="object", nullable=true,
 *     @OA\Property(property="self", type="string", example="https://api.example.com/api/users/123"),
 *     @OA\Property(property="update", type="string", example="https://api.example.com/api/users/123"),
 *     @OA\Property(property="delete", type="string", example="https://api.example.com/api/users/123")
 *   ),
 *   @OA\Property(property="meta", type="object", nullable=true),
 *   @OA\Property(property="debug", type="object", nullable=true),
 *   @OA\Property(property="timestamp", type="string", format="date-time"),
 *   @OA\Property(property="request_id", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *   schema="User",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=123),
 *   @OA\Property(property="code", type="string", example="USR-2024-000123"),
 *   @OA\Property(property="name", type="string", example="Nguyen Van A"),
 *   @OA\Property(property="email", type="string", example="a.nguyen@example.com"),
 *   @OA\Property(property="is_active", type="boolean", example=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="UserResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", ref="#/components/schemas/User")
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="UserListResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *       ),
 *       @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=150)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="ErrorResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="message", type="string", example="Validation failed"),
 *       @OA\Property(property="code", type="integer", example=422),
 *       @OA\Property(property="errors", type="object",
 *         @OA\Property(property="email", type="array",
 *           @OA\Items(type="string", example="The email has already been taken.")
 *         )
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Tag(
 *   name="System",
 *   description="System module APIs (User management, configuration, etc.)"
 * )
 */


/**
 * @OA\Schema(
 *   schema="UserCollectionResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="BulkOperationResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="operation", type="string", example="delete"),
 *       @OA\Property(property="successful", type="integer", example=3),
 *       @OA\Property(property="failed", type="integer", example=1),
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(type="object",
 *           @OA\Property(property="id", type="integer", example=10),
 *           @OA\Property(property="status", type="string", example="success"),
 *           @OA\Property(property="error", type="string", nullable=true, example=null)
 *         )
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="DeleteResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="deleted", type="boolean", example=true)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="UserStatsResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="total_users", type="integer", example=120),
 *         @OA\Property(property="active_users", type="integer", example=110),
 *         @OA\Property(property="new_today", type="integer", example=5)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="CurrentUserResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         nullable=true,
 *         oneOf={
 *           @OA\Schema(ref="#/components/schemas/User")
 *         }
 *       )
 *     )
 *   }
 * )
 */
