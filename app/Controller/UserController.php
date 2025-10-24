<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\StandardJsonResponse;
use App\Helper\ValidationHelper;
use App\Service\UserService;
use App\Service\RoleService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\{Controller, GetMapping, PostMapping, PutMapping, DeleteMapping};
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface as HttpResponse};
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * User Controller
 * 
 * Handles user management operations
 */
class UserController extends AbstractController
{
    #[Inject]
    protected UserService $userService;

    #[Inject]
    protected RoleService $roleService;

    #[Inject]
    protected ValidationHelper $validationHelper;

    /**
     * Get all users with pagination and filters
     * 
     * Permission: users.index (view list)
     * 
     * @return PsrResponse
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
                'role_id' => $request->input('role_id'),
            ];

            $result = $this->userService->getAllUsers($page, $perPage, $filters);

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
                'Failed to retrieve users',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get data for create form (dropdown options, etc)
     * 
     * Permission: users.create
     * 
     * @return PsrResponse
     */
    #[GetMapping(path: '/create')]
    public function create(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Get roles for dropdown
            $roles = $this->roleService->getAllRolesForDropdown();

            $data = [
                'roles' => $roles['data'] ?? [],
                'status_options' => [
                    ['value' => true, 'label' => 'Active'],
                    ['value' => false, 'label' => 'Inactive'],
                ],
            ];

            return StandardJsonResponse::success(
                $response,
                $data,
                'Create form data retrieved successfully'
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve create form data',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Store new user
     * 
     * Permission: users.create (checked via middleware mapping users.store → users.create)
     * 
     * @return PsrResponse
     */
    #[PostMapping(path: '')]
    public function store(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:150'],
                'password' => ['required', 'string', 'min:6', 'max:255'],
                'phone' => ['string', 'max:20'],
                'role_id' => ['required', 'integer'],
                'is_active' => ['boolean'],
            ];
            
            $messages = [
                'name.required' => 'Name is required',
                'email.required' => 'Email is required',
                'email.email' => 'Email must be valid',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 6 characters',
                'role_id.required' => 'Role is required',
            ];

            $validation = $this->validationHelper->validate($request, $rules, $messages);
            
            if (!$validation['success']) {
                return StandardJsonResponse::validationError($response, $validation['errors']);
            }

            $payload = $validation['data'];
            $result = $this->userService->createUser($payload);

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
                $result['data'],
                $result['code'] ?? 400
            );

        } catch (\Throwable $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to create user',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get user by ID (for detail view)
     * 
     * Permission: users.show (view detail)
     * 
     * @return PsrResponse
     */
    #[GetMapping(path: '/{id:\d+}')]
    public function show(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->userService->getUserById($id);

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
                'Failed to retrieve user',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get data for edit form (current data + dropdown options)
     * 
     * Permission: users.update
     * 
     * @return PsrResponse
     */
    #[GetMapping(path: '/{id:\d+}/edit')]
    public function edit(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            
            // Get user data
            $userResult = $this->userService->getUserById($id);
            
            if (!$userResult['success']) {
                return StandardJsonResponse::notFound($response, $userResult['message']);
            }

            // Get roles for dropdown
            $roles = $this->roleService->getAllRolesForDropdown();

            $data = [
                'user' => $userResult['data'],
                'roles' => $roles['data'] ?? [],
                'status_options' => [
                    ['value' => true, 'label' => 'Active'],
                    ['value' => false, 'label' => 'Inactive'],
                ],
            ];

            return StandardJsonResponse::success(
                $response,
                $data,
                'Edit form data retrieved successfully'
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to retrieve edit form data',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Update user
     * 
     * Permission: users.update (checked via middleware mapping users.update → users.update)
     * 
     * @return PsrResponse
     */
    #[PutMapping(path: '/{id:\d+}')]
    public function update(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');

            $rules = [
                'name' => ['string', 'max:255'],
                'email' => ['email', 'max:150'],
                'password' => ['string', 'min:6', 'max:255'],
                'phone' => ['string', 'max:20'],
                'role_id' => ['integer'],
                'is_active' => ['boolean'],
            ];
            
            $messages = [
                'email.email' => 'Email must be valid',
                'password.min' => 'Password must be at least 6 characters',
            ];

            $validation = $this->validationHelper->validate($request, $rules, $messages);
            
            if (!$validation['success']) {
                return StandardJsonResponse::validationError($response, $validation['errors']);
            }

            $payload = $validation['data'];
            $result = $this->userService->updateUser($id, $payload);

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
                $result['data'],
                $result['code'] ?? 400
            );

        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to update user',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Delete user
     * 
     * Permission: users.delete (checked via middleware mapping users.destroy → users.delete)
     * 
     * @return PsrResponse
     */
    #[DeleteMapping(path: '/{id:\d+}')]
    public function destroy(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->userService->deleteUser($id);

            if ($result['success']) {
                return StandardJsonResponse::deleted($response, $result['message']);
            }

            return StandardJsonResponse::error(
                $response,
                $result['message'],
                $result['data'],
                $result['code'] ?? 400
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to delete user',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get user permissions (via role)
     * 
     * Permission: users.permissions (custom action)
     * 
     * @return PsrResponse
     */
    #[GetMapping(path: '/{id:\d+}/permissions')]
    public function permissions(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $id = (int) $request->route('id');
            $result = $this->userService->getUserPermissions($id);

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
                'Failed to retrieve user permissions',
                ['error' => $e->getMessage()]
            );
        }
    }
}