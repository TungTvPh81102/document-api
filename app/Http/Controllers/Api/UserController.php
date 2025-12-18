<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Log\Logger;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private UserService $userService) {}

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
            LoggerService::logApiRequest($request, $response->getStatusCode(), $duration);
            LoggerService::logApiSuccess($request, 'Fetched user list successfully');

            return $response;
        } catch (\Exception $e) {
            LoggerService::logApiError($e, $request);

            return $this->serverErrorResponse(
                $e->getMessage()
            );
        }
    }

    /**
     * Create new user (Admin only)
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        try {
            $user = $this->userService->createUser($request->validated());

            return $this->successResponse(
                new UserResource($user),
                'User created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get user by ID
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        try {
            return $this->successResponse(
                new UserResource($user),
                'User retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userService->updateUser($user, $request->validated());

            return $this->successResponse(
                new UserResource($updatedUser),
                'User updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->deleteUser($user);

            return $this->successResponse(
                null,
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return $this->successResponse(
                new UserResource($user),
                'Current user retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lock user account (Admin only)
     */
    public function lock(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        try {
            $lockedUser = $this->userService->lockUser($user);

            return $this->successResponse(
                new UserResource($lockedUser),
                'User account locked successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Unlock user account (Admin only)
     */
    public function unlock(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        try {
            $unlockedUser = $this->userService->unlockUser($user);

            return $this->successResponse(
                new UserResource($unlockedUser),
                'User account unlocked successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Enable user (Admin only)
     */
    public function enable(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        try {
            $enabledUser = $this->userService->enableUser($user);

            return $this->successResponse(
                new UserResource($enabledUser),
                'User enabled successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Disable user (Admin only)
     */
    public function disable(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        try {
            $disabledUser = $this->userService->disableUser($user);

            return $this->successResponse(
                new UserResource($disabledUser),
                'User disabled successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get user statistics (Admin only)
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        try {
            $stats = $this->userService->getUserStatistics();

            return $this->successResponse(
                $stats,
                'User statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }
}
