<?php

namespace App\Http\Controllers\Api\SystemConsole;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConsoles\Role\StoreRoleRequest;
use App\Http\Requests\SystemConsoles\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\LoggerService;
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Composer\DependencyResolver\Request;

class RoleController extends Controller
{
    use ApiResponseTrait;

    protected function __construct(
        private LoggerService $logger,
        private RoleService   $roleService
    )
    {
    }

    protected function index(Request $request)
    {
        $start = microtime(true);
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        $search = $request->query('search');

        try {
            $roles = $search
                ? $this->roleService->searchRoles($search, $page, $perPage)
                : $this->roleService->getAllUsers($page, $perPage);

            $response = $this->successResponse(
                $roles,
                'Danh sách vai trò của hệ thống'
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

    protected function show(string $id)
    {
        try {
            $role = $this->roleService->getRoleById($id);

            if (!$role) {
                return $this->notFoundResponse('Không tìm thấy vai trò trong hệ thống');
            }

            return $this->successResponse(new $role, 'Thông tin vai trò: ' . $role->name);
        } catch (\Exception $e) {
            return $this->serverErrorResponse($e->getMessage());
        }
    }

    protected function store(StoreRoleRequest $request)
    {
        try {
            $data = $request->validated();

            $role = $this->roleService->createRole($data);

            $response = $this->createdResponse(
                new $role,
                'Tạo vai trò thành công'
            );

            return $response;
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse(
                $e->errors()
            );
        } catch (\Throwable $e) {
            $this->logger->logApiError($e, $request);

            return $this->serverErrorResponse($e->getMessage());
        }
    }

    protected function update(UpdateRoleRequest $request, string $id)
    {
        try {
            $role = Role::query()->find($id);

            if (!$role) {
                return $this->notFoundResponse('Không tìm thấy vai trò trong hệ thống');
            }

            $data = $request->validated();

            $role = $this->roleService->updateRole($data);

            return $this->successResponse(
                new $role,
                'Cập nhật vai trò thành công'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse($e->getMessage());
        }
    }
}
