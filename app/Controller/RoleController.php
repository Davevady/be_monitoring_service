<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\StandardJsonResponse;
use App\Helper\ValidationHelper;
use App\Service\RoleService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\{
    Controller,
    PostMapping,
    GetMapping,
    PutMapping,
    DeleteMapping,
    Middleware
};
use Hyperf\HttpServer\Contract\{
    RequestInterface,
    ResponseInterface as HttpResponse
};
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * Role Controller
 * 
 * Handles role management operations
 */
class RoleController
{
    #[Inject]
    protected RoleService $roleService;

    #[Inject]
    protected ValidationHelper $validationHelper;

    /**
     * Get all roles
     */
    #[GetMapping(path: '')]
    public function index(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 15);
            
            $filters = [
                'search' => $request->input('search'),
                'is_active' => $request->input('is_active'),
            ];

            $result = $this->roleService->getAllRoles($page, $perPage, $filters);

            if ($result['success']) {
                return StandardJsonResponse::paginated(
                    $response,
                    $result['data'],
                    $result['meta']['pagination'],
                    $result['message']
                );
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve roles',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get role by ID
     */
    public function show(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->roleService->getRoleById($id);

            if ($result['success']) {
                return StandardJsonResponse::success(
                    $response,
                    $result['data'],
                    $result['message']
                );
            }

            return StandardJsonResponse::notFound($response, $result['message']);
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve role',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Create new role
     */
    #[PostMapping(path: '')]
    public function store(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getCreateRoleRules(),
                $this->validationHelper->getCreateRoleMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->roleService->createRole($data);

            if ($result['success']) {
                return StandardJsonResponse::created(
                    $response,
                    $result['data'],
                    $result['message']
                );
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to create role',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Update role
     */
    public function update(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');

            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getUpdateRoleRules($id),
                $this->validationHelper->getUpdateRoleMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->roleService->updateRole($id, $data);

            if ($result['success']) {
                return StandardJsonResponse::updated(
                    $response,
                    $result['data'],
                    $result['message']
                );
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to update role',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Delete role
     */
    public function destroy(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->roleService->deleteRole($id);

            if ($result['success']) {
                return StandardJsonResponse::deleted($response, $result['message']);
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to delete role',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Assign permissions to role
     */
    #[PutMapping(path: '/{id:\d+}/permissions')]
    public function syncPermissions(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $roleId = (int) $request->route('id');

            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getAssignPermissionsRules(),
                $this->validationHelper->getAssignPermissionsMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->roleService->assignPermissions($roleId, $data['permission_ids']);

            if ($result['success']) {
                return StandardJsonResponse::success(
                    $response,
                    $result['data'],
                    $result['message']
                );
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to assign permissions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get role permissions
     */
    public function permissions(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $roleId = (int) $request->route('id');
            $result = $this->roleService->getRolePermissions($roleId);

            if ($result['success']) {
                return StandardJsonResponse::success(
                    $response,
                    $result['data'],
                    $result['message']
                );
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve role permissions',
                ['error' => $e->getMessage()]
            );
        }
    }
}
