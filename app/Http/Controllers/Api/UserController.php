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

    public function __construct(
        private UserService $userService,
        private LoggerService $logger
    ) {}

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
     * Example 1: Basic paginated response
     */
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            $users = $this->userService->getAllUsers($page, $perPage);

            return $this->paginatedResponse($users, 'Users retrieved successfully');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse(
                'Failed to retrieve users',
                $e
            );
        }
    }

    /**
     * Example 2: Created response with location and correlation ID
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
            return $this->setCorrelationId($corrId)
                ->withLinks([
                    'self' => route('users.show', $user->id),
                    'update' => route('users.update', $user->id),
                    'delete' => route('users.destroy', $user->id),
                ])
                ->createdResponse(
                    $user,
                    'User created successfully',
                    route('users.show', $user->id)
                );
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
     * Example 3: Response with debug info (non-production)
     */
    public function show(string $id)
    {
        $start = microtime(true);

        try {
            $user = $this->userService->findUser($id);

            if (!$user) {
                return $this->notFoundResponse('User not found', 'User');
            }

            $duration = microtime(true) - $start;

            // Add debug info in non-production
            return $this->withDebug([
                'query_time_ms' => round($duration * 1000, 2),
                'cache_hit' => false,
            ])
                ->withLinks([
                    'self' => route('users.show', $id),
                    'posts' => route('users.posts', $id),
                ])
                ->successResponse($user, 'User retrieved successfully');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to retrieve user', $e);
        }
    }

    /**
     * Example 4: Bulk operation response
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:users,id',
        ]);

        $successful = 0;
        $failed = 0;
        $results = [];

        foreach ($validated['ids'] as $id) {
            try {
                $this->userService->deleteUser($id);
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
     * Example 5: Conflict response for duplicate
     */
    public function checkEmail(Request $request)
    {
        $email = $request->get('email');

        $exists = User::where('email', $email)->exists();

        if ($exists) {
            return $this->conflictResponse(
                'Email already registered',
                ['email' => $email]
            );
        }

        return $this->successResponse(
            ['available' => true],
            'Email is available'
        );
    }

    /**
     * Example 6: Collection response
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
     * Example 7: Accepted response for async operation
     */
    public function export(Request $request)
    {
        $jobId = Str::uuid();

        // Dispatch async job
        ExportUsersJob::dispatch($jobId);

        return $this->withLinks([
            'status' => route('exports.status', $jobId),
        ])
            ->acceptedResponse(
                ['job_id' => $jobId],
                'Export job queued successfully'
            );
    }

    /**
     * Example 8: Rate limit response
     */
    public function rateLimit(Request $request)
    {
        $key = 'api_limit:' . $request->ip();
        $limit = 100;
        $current = Cache::increment($key);

        if ($current === 1) {
            Cache::expire($key, 3600); // 1 hour
        }

        if ($current > $limit) {
            $retryAfter = Cache::ttl($key);
            return $this->tooManyRequestsResponse(
                'Rate limit exceeded',
                $retryAfter
            );
        }

        // Continue with normal request
        return $this->successResponse(['message' => 'OK']);
    }

    /**
     * Example 9: Partial content response
     */
    public function rangeRequest(Request $request)
    {
        $from = $request->get('from', 0);
        $to = $request->get('to', 99);

        $total = User::count();
        $users = User::skip($from)->take($to - $from + 1)->get();

        return $this->partialContentResponse(
            $users,
            $from,
            $to,
            $total,
            'Partial user list'
        );
    }

    /**
     * Example 10: Custom metadata response
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
}
