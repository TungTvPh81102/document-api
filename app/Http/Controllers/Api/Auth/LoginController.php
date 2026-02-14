<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Tag(
 *   name="Auth",
 *   description="Authentication endpoints"
 * )
 */
class LoginController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AuthService $authService,
    ) {}

    /**
     * Login
     *
     * @OA\Post(
     *   path="/api/auth/login",
     *   tags={"Auth"},
     *   summary="Login with email/employee_id and password",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"password"},
     *       @OA\Property(property="email", type="string", format="email", example="admin@example.com", description="Login email"),
     *       @OA\Property(property="employee_id", type="string", example="V23050343", description="Employee ID (alternative to email)"),
     *       @OA\Property(property="password", type="string", format="password", example="P@ssw0rd!", description="Password"),
     *       @OA\Property(property="remember", type="boolean", example=false, description="Remember login")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Login successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Login successful"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="user", ref="#/components/schemas/User"),
     *         @OA\Property(property="token", type="string", example="1|abc123...")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *   @OA\Response(response=429, description="Too many attempts"),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $auth = $this->authService->login($data);

            return $this->successResponse($auth, 'Login successful');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }

    /**
     * Logout
     *
     * @OA\Post(
     *   path="/api/auth/logout",
     *   tags={"Auth"},
     *   summary="Logout and revoke current token",
     *   security={{"sanctum":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="Logout successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Logout successful")
     *     )
     *   ),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout();

            return $this->successResponse(null, 'Logout successful');
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e->getMessage(), $e);
        }
    }
}
