<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\LoggerService;
use App\Traits\ApiResponseTrait;

class LoginController extends Controller
{
    use ApiResponseTrait;

    protected function __construct(
        private AuthService   $authService,
        private LoggerService $logger
    )
    {
    }

    protected function login(LoginRequest $request)
    {
        try {
            $data = $request->validated();

            $auth = $this->authService->login($data);

            return $this->successResponse($auth, 'Đăng nhập thành công');
        } catch (\Exception $e) {
            $this->serverErrorResponse($e);
        }
    }

    protected function logout()
    {

    }
}
