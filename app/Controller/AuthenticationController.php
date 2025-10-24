<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\StandardJsonResponse;
use App\Helper\ValidationHelper;
use App\Service\AuthService;
use App\Service\JwtService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\{
    Controller,
    PostMapping,
    GetMapping,
    PutMapping,
    Middleware
};
use Hyperf\HttpServer\Contract\{
    RequestInterface,
    ResponseInterface as HttpResponse
};
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * Authentication Controller
 * 
 * Handles user authentication, profile management, and password operations
 */
#[Controller(prefix: '/auth')]
class AuthenticationController
{
    #[Inject]
    protected AuthService $authService;

    #[Inject]
    protected JwtService $jwtService;

    #[Inject]
    protected ValidationHelper $validationHelper;

    /**
     * User login
     */
    #[PostMapping(path: '/login')]
    public function login(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getLoginRules(),
                $this->validationHelper->getLoginMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->authService->login($data['email'], $data['password']);

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
                $result['data'],
                [],
                401
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Login failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * User logout
     */
    #[PostMapping(path: '/logout')]
    #[Middleware(\App\Middleware\AuthMiddleware::class)]
    public function logout(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $authorization = $request->getHeaderLine('Authorization');
            $token = $this->jwtService->extractTokenFromHeader($authorization);

            if (!$token) {
                return StandardJsonResponse::unauthorized($response, 'Invalid token');
            }

            $result = $this->authService->logout($token);

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
                'Logout failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Refresh access token
     */
    #[PostMapping(path: '/refresh')]
    public function refresh(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $data = $request->all();
            
            if (empty($data['refresh_token'])) {
                return StandardJsonResponse::error(
                    $response,
                    'Refresh token is required',
                    null,
                    [],
                    400
                );
            }

            $result = $this->authService->refreshToken($data['refresh_token']);

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
                $result['data'],
                [],
                401
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Token refresh failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get user profile
     */
    public function profile(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $user = $request->getAttribute('user');
            $result = $this->authService->getProfile($user);

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
                'Failed to retrieve profile',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $user = $request->getAttribute('user');

            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getUpdateProfileRules(),
                $this->validationHelper->getUpdateProfileMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->authService->updateProfile($user, $data);

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
                'Failed to update profile',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Change user password
     */
    #[PutMapping(path: '/change-password')]
    #[Middleware(\App\Middleware\AuthMiddleware::class)]
    public function changePassword(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $user = $request->getAttribute('user');

            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getChangePasswordRules(),
                $this->validationHelper->getChangePasswordMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->authService->changePassword(
                $user,
                $data['current_password'],
                $data['new_password']
            );

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
                'Failed to change password',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Forgot password
     */
    #[PostMapping(path: '/forgot-password')]
    public function forgotPassword(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getForgotPasswordRules(),
                $this->validationHelper->getForgotPasswordMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->authService->forgotPassword($data['email']);

            return StandardJsonResponse::success(
                $response,
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $response,
                'Failed to process forgot password request',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Reset password
     */
    #[PostMapping(path: '/reset-password')] 
    public function resetPassword(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            // Validate request
            $validation = $this->validationHelper->validate(
                $request,
                $this->validationHelper->getResetPasswordRules(),
                $this->validationHelper->getResetPasswordMessages()
            );

            if (!$validation['success']) {
                return StandardJsonResponse::validationError(
                    $response,
                    $validation['errors'],
                    'Validation failed'
                );
            }

            $data = $validation['data'];
            $result = $this->authService->resetPassword($data['token'], $data['password']);

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
                'Failed to reset password',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Check password reset token status
     */
    #[PostMapping(path: '/check-reset-token')]
    public function checkResetToken(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $data = $request->all();
            
            if (empty($data['token'])) {
                return StandardJsonResponse::error(
                    $response,
                    'Token is required',
                    null,
                    [],
                    400
                );
            }

            $result = $this->authService->checkResetTokenStatus($data['token']);

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
                'Failed to check token status',
                ['error' => $e->getMessage()]
            );
        }
    }
}
