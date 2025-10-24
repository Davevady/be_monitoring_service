<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\StandardJsonResponse;
use App\Helper\ValidationHelper;
use App\Service\PermissionService;
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
 * Permission Controller
 * 
 * Handles permission management operations
 */
class PermissionController
{
    #[Inject]
    protected PermissionService $permissionService;

    #[Inject]
    protected ValidationHelper $validationHelper;

    /**
     * Get all permissions
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
                'group' => $request->input('group'),
            ];

            $result = $this->permissionService->getAllPermissions($page, $perPage, $filters);

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
                'Failed to retrieve permissions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get permission by ID
     */
    public function show(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->permissionService->getPermissionById($id);

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
                'Failed to retrieve permission',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Create new permission
     */
    #[PostMapping(path: '')]
    public function store(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getCreatePermissionRules(),
                $this->validationHelper->getCreatePermissionMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->permissionService->createPermission($data);

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
                'Failed to create permission',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Update permission
     */
    public function update(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');

            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getUpdatePermissionRules($id),
                $this->validationHelper->getUpdatePermissionMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->permissionService->updatePermission($id, $data);

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
                'Failed to update permission',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Delete permission
     */
    public function destroy(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->permissionService->deletePermission($id);

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
                'Failed to delete permission',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get permission groups
     */
    #[GetMapping(path: '/groups')]
    public function groups(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $result = $this->permissionService->getPermissionGroups();

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
                'Failed to retrieve permission groups',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get permissions grouped by group
     */
    #[GetMapping(path: '/grouped')]
    public function grouped(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $result = $this->permissionService->getGroupedPermissions();

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
                'Failed to retrieve grouped permissions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get structured permissions for frontend
     */
    #[GetMapping(path: '/structured')]
    public function structured(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $structured = \App\Model\Permission::getStructuredPermissions();

            return StandardJsonResponse::success(
                $response,
                $structured,
                'Structured permissions retrieved successfully'
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve structured permissions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get permissions grouped by resource
     */
    #[GetMapping(path: '/by-resource')]
    public function byResource(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $grouped = \App\Model\Permission::getGroupedByResource();

            return StandardJsonResponse::success(
                $response,
                $grouped,
                'Permissions by resource retrieved successfully'
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve permissions by resource',
                ['error' => $e->getMessage()]
            );
        }
    }
}
