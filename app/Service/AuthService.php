<?php

declare(strict_types=1);

namespace App\Service;

use App\Helper\StandardJsonResponse;
use App\Model\PasswordReset;
use App\Model\User;
use Carbon\Carbon;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Log\LoggerInterface;

use function Hyperf\Collection\collect;

/**
 * Authentication Service
 * 
 * Handles user authentication, password management, and profile operations
 */
class AuthService
{
    #[Inject]
    protected JwtService $jwtService;

    #[Inject]
    protected Redis $redis;

    #[Inject]
    protected LoggerFactory $loggerFactory;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = $this->loggerFactory->get('auth');
    }

    /**
     * Authenticate user with email and password
     */
    public function login(string $email, string $password): array
    {
        try {
            // Find user by email WITH eager loading
            $user = User::with(['role.permissions'])->where('email', $email)->first();

            if (!$user) {
                $this->logger->warning('Login attempt with non-existent email', ['email' => $email]);
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'data' => null,
                ];
            }

            // Check if user is active
            if (!$user->isActive()) {
                $this->logger->warning('Login attempt with inactive user', ['email' => $email, 'user_id' => $user->id]);
                return [
                    'success' => false,
                    'message' => 'Account is deactivated',
                    'data' => null,
                ];
            }

            // Verify password
            if (!password_verify($password, $user->password)) {
                $this->logger->warning('Login attempt with invalid password', ['email' => $email, 'user_id' => $user->id]);
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'data' => null,
                ];
            }

            // Generate JWT tokens
            $tokens = $this->jwtService->generateToken($user);

            // Log successful login
            $this->logger->info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role?->name,
            ]);

            return [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'avatar' => $user->avatar,
                        'role' => [
                            'id' => $user->role?->id,
                            'name' => $user->role?->name,
                            'display_name' => $user->role?->display_name,
                        ],
                        'permissions' => $user->getPermissions(),
                    ],
                    'tokens' => $tokens,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Login error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Login failed',
                'data' => null,
            ];
        }
    }

    /**
     * Logout user and invalidate tokens
     */
    public function logout(string $token): array
    {
        try {
            // Get user from token first
            $user = $this->jwtService->getUserFromToken($token);
            if ($user) {
                // Invalidate all tokens for user using token versioning
                $this->jwtService->invalidateAllTokensForUser($user->id);

                // Also revoke all refresh tokens
                $this->jwtService->revokeAllRefreshTokens($user->id);

                $this->logger->info('User logged out successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Logout successful',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Logout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Logout failed',
                'data' => null,
            ];
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $tokens = $this->jwtService->refreshToken($refreshToken);

            if (!$tokens) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired refresh token',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => $tokens,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Token refresh error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Token refresh failed',
                'data' => null,
            ];
        }
    }

    /**
     * Get user profile
     */
    public function getProfile(User $user): array
    {
        try {
            // Load role with permissions
            $user->load(['role.permissions']);

            // Get permissions from role
            $permissions = $user->role && $user->role->permissions
                ? $user->role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'group' => $permission->group,
                    ];
                })
                : [];

            return [
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                        'description' => $user->role->description,
                    ] : null,
                    'permissions' => $permissions,  // ← Pake variable yang udah di-load
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get profile error', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'data' => null,
            ];
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): array
    {
        try {
            // Only allow updating specific fields
            $allowedFields = ['name', 'phone', 'avatar'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($updateData)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update',
                    'data' => null,
                ];
            }

            $user->update($updateData);
            $user->refresh();

            $this->logger->info('User profile updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return [
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'updated_at' => $user->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Update profile error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update profile',
                'data' => null,
            ];
        }
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        try {
            // Verify current password
            if (!password_verify($currentPassword, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'data' => null,
                ];
            }

            // Update password
            $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
            $user->save();

            // Invalidate all tokens to force re-login
            $this->jwtService->invalidateAllTokensForUser($user->id);
            $this->jwtService->revokeAllRefreshTokens($user->id);

            $this->logger->info('User password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'message' => 'Password changed successfully. Please login again.',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Change password error', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to change password',
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Send password reset email
     */
    public function forgotPassword(string $email): array
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Don't reveal if email exists or not for security
                return [
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.',
                    'data' => null,
                ];
            }

            // Check if there's already a token for this email (valid or expired)
            $existingReset = PasswordReset::where('email', $email)->first();
            
            if ($existingReset && !$existingReset->isExpired()) {
                // Return existing valid token info
                $this->logger->info('Password reset token already exists and is valid', [
                    'email' => $email,
                    'expires_at' => $existingReset->created_at->addHour()->toDateTimeString(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Password reset token already exists. Please check your email or use the existing token.',
                    'data' => [
                        'token' => $existingReset->token, // Remove this in production
                        'expires_at' => $existingReset->created_at->addHour()->toDateTimeString(),
                        'is_existing' => true,
                    ],
                ];
            }

            // Generate new reset token
            $token = bin2hex(random_bytes(32));
            $now = Carbon::now(); // ✅ Created at = sekarang!

            // Store reset token using updateOrCreate
            PasswordReset::updateOrCreate(
                ['email' => $email], // ✅ Kondisi pencarian (berdasarkan PK)
                [
                    'token' => $token,
                    'created_at' => $now, // ✅ Simpan waktu sekarang, bukan expires!
                ]
            );

            // Calculate expiration time (for response only)
            $expiresAt = $now->copy()->addHour();

            // TODO: Send email with reset link
            // For now, we'll just log the token (in production, send via email)
            $this->logger->info('Password reset token generated', [
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt->toDateTimeString(),
            ]);

            return [
                'success' => true,
                'message' => 'Password reset link has been sent.',
                'data' => [
                    'token' => $token, // Remove this in production
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'is_existing' => false,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Forgot password error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process password reset request',
                'data' => null,
            ];
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        try {
            $oneHourAgo = Carbon::now()->subHour()->format('Y-m-d H:i:s');
            
            $passwordReset = PasswordReset::where('token', $token)
                ->where('created_at', '>=', $oneHourAgo)
                ->first();

            if (!$passwordReset) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token',
                    'data' => null,
                ];
            }

            $user = User::where('email', $passwordReset->email)->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            // Update password
            $user->update([
                'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            ]);

            // ✅ FIX: Delete manual pake query, bukan model->delete()
            PasswordReset::where('email', $passwordReset->email)
                ->where('token', $token)
                ->delete();

            // Invalidate all tokens
            $this->jwtService->invalidateAllTokensForUser($user->id);
            $this->jwtService->revokeAllRefreshTokens($user->id);

            $this->logger->info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'message' => 'Password reset successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Reset password error', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reset password',
                'data' => null,
            ];
        }
    }

    /**
     * Check password reset token status
     */
    public function checkResetTokenStatus(string $token): array
    {
        try {
            $passwordReset = PasswordReset::where('token', $token)->first();

            if (!$passwordReset) {
                return [
                    'success' => false,
                    'message' => 'Invalid reset token',
                    'data' => null,
                ];
            }

            if ($passwordReset->isExpired()) {
                return [
                    'success' => false,
                    'message' => 'Reset token has expired',
                    'data' => [
                        'is_expired' => true,
                        'expires_at' => $passwordReset->created_at->addHour(),
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'Reset token is valid',
                'data' => [
                    'is_valid' => true,
                    'email' => $passwordReset->email,
                    'expires_at' => $passwordReset->created_at->addHour(),
                    'created_at' => $passwordReset->created_at,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Check reset token status error', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to check token status',
                'data' => null,
            ];
        }
    }
}
