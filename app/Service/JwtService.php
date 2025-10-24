<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Hyperf\Config\Annotation\Value;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;

/**
 * Simple JWT Token Service
 * 
 * Handles JWT token generation, validation, and management without external dependencies
 */
class JwtService
{
    #[Inject]
    protected Redis $redis;

    #[Value('jwt.secret')]
    protected string $secret;

    #[Value('jwt.expire')]
    protected int $expire;

    #[Value('jwt.refresh_expire')]
    protected int $refreshExpire;

    #[Value('jwt.issuer')]
    protected string $issuer;

    #[Value('jwt.audience')]
    protected string $audience;

    #[Inject]
    protected LoggerInterface $logger;

    /**
     * Generate JWT token for user
     */
    public function generateToken(User $user): array
    {
        $now = time();
        $expireTime = $now + $this->expire;
        $refreshExpireTime = $now + $this->refreshExpire;

        // Log configuration values for debugging
        $this->logger->debug('JWT Configuration', [
            'expire_seconds' => $this->expire,
            'refresh_expire_seconds' => $this->refreshExpire,
            'expire_time' => $expireTime,
            'refresh_expire_time' => $refreshExpireTime,
            'current_time' => $now
        ]);

        // Generate token version for tracking
        $tokenVersion = $now . '_' . bin2hex(random_bytes(8));

        // Access token payload
        $accessPayload = [
            'iss' => $this->issuer,           // Issuer
            'aud' => $this->audience,         // Audience
            'iat' => $now,                   // Issued at
            'exp' => $expireTime,             // Expiration time
            'sub' => (string) $user->id,      // Subject (user ID)
            'type' => 'access',               // Token type
            'version' => $tokenVersion,        // Token version for tracking
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $user->role?->name,
            ],
        ];

        // Refresh token payload
        $refreshPayload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'exp' => $refreshExpireTime,
            'sub' => (string) $user->id,
            'type' => 'refresh',
        ];

        // Generate tokens using simple base64 encoding (for demo purposes)
        $accessToken = $this->encodeToken($accessPayload);
        $refreshToken = $this->encodeToken($refreshPayload);

        // Store refresh token in Redis for validation
        $this->storeRefreshToken($user->id, $refreshToken, $refreshExpireTime);

        // Store current token version for user
        $this->storeCurrentTokenVersion($user->id, $tokenVersion, $expireTime);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expire,
            'refresh_expires_in' => $this->refreshExpire,
        ];
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): ?array
    {
        try {
            $payload = $this->decodeToken($token);

            if (!$payload) {
                $this->logger->debug('Token decode failed');
                return null;
            }

            // Check if token is expired
            $currentTime = time();
            $tokenExp = $payload['exp'];
            
            if ($tokenExp < $currentTime) {
                $this->logger->debug('Token expired', [
                    'token_exp' => $tokenExp,
                    'current_time' => $currentTime,
                    'expired_seconds_ago' => $currentTime - $tokenExp
                ]);
                return null;
            }

            // Check issuer and audience
            if ($payload['iss'] !== $this->issuer || $payload['aud'] !== $this->audience) {
                $this->logger->debug('Token issuer/audience mismatch', [
                    'token_iss' => $payload['iss'],
                    'expected_iss' => $this->issuer,
                    'token_aud' => $payload['aud'],
                    'expected_aud' => $this->audience
                ]);
                return null;
            }

            // For access tokens, check if token version is still valid
            if (isset($payload['type']) && $payload['type'] === 'access' && isset($payload['version'])) {
                $userId = (int) $payload['sub'];
                if (!$this->isTokenVersionValid($userId, $payload['version'])) {
                    $this->logger->debug('Token version invalid', [
                        'user_id' => $userId,
                        'token_version' => $payload['version']
                    ]);
                    return null;
                }
            }

            $this->logger->debug('Token validation successful', [
                'user_id' => $payload['sub'] ?? null,
                'token_type' => $payload['type'] ?? null,
                'expires_in' => $tokenExp - $currentTime
            ]);

            return $payload;
        } catch (\Exception $e) {
            $this->logger->error('Token validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): ?array
    {
        try {
            $payload = $this->validateToken($refreshToken);

            if (!$payload || $payload['type'] !== 'refresh') {
                return null;
            }

            // Check if refresh token exists in Redis
            $userId = (int) $payload['sub'];
            if (!$this->isRefreshTokenValid($userId, $refreshToken)) {
                return null;
            }

            // Get user and generate new tokens
            $user = User::find($userId);
            if (!$user || !$user->isActive()) {
                return null;
            }

            // Revoke old refresh token
            $this->revokeRefreshToken($userId, $refreshToken);

            // Generate new tokens
            $newTokens = $this->generateToken($user);

            // Store the old access token for blacklisting (if we had access to it)
            // Note: We can't blacklist the old access token here because we don't have it
            // The old access token will remain valid until it expires naturally
            // This is a limitation of stateless JWT tokens

            return $newTokens;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Revoke refresh token
     */
    public function revokeRefreshToken(int $userId, string $refreshToken): void
    {
        $key = "refresh_token:{$userId}";
        $this->redis->hDel($key, $refreshToken);
    }

    /**
     * Revoke all refresh tokens for user
     */
    public function revokeAllRefreshTokens(int $userId): void
    {
        $key = "refresh_token:{$userId}";
        $this->redis->del($key);
    }

    /**
     * Store refresh token in Redis
     */
    private function storeRefreshToken(int $userId, string $refreshToken, int $expireTime): void
    {
        $key = "refresh_token:{$userId}";
        $this->redis->hSet($key, $refreshToken, $expireTime);
        $this->redis->expireAt($key, $expireTime);
    }

    /**
     * Check if refresh token is valid
     */
    private function isRefreshTokenValid(int $userId, string $refreshToken): bool
    {
        $key = "refresh_token:{$userId}";
        $expireTime = $this->redis->hGet($key, $refreshToken);

        if (!$expireTime) {
            return false;
        }

        return (int) $expireTime > time();
    }

    /**
     * Extract token from Authorization header
     */
    public function extractTokenFromHeader(string $authorization): ?string
    {
        if (!preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->validateToken($token);
        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        return User::query()->find($payload['sub']);
    }

    /**
     * Blacklist token (for logout)
     */
    public function blacklistToken(string $token): void
    {
        $payload = $this->validateToken($token);
        if ($payload) {
            $expireTime = $payload['exp'] - time();
            if ($expireTime > 0) {
                $this->redis->setex("blacklist:{$token}", $expireTime, '1');
            }
        }
    }

    /**
     * Check if token is blacklisted
     */
    public function isTokenBlacklisted(string $token): bool
    {
        return $this->redis->exists("blacklist:{$token}") > 0;
    }

    /**
     * Simple token encoding (base64 + secret)
     */
    private function encodeToken(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payloadJson = json_encode($payload);

        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Simple token decoding
     */
    private function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secret, true);
        $expectedSignature = $this->base64UrlEncode($signature);

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        return $payload ?: null;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Store current token version for user
     */
    private function storeCurrentTokenVersion(int $userId, string $tokenVersion, int $expireTime): void
    {
        $key = "token_version:{$userId}";
        $this->redis->setex($key, $expireTime - time(), $tokenVersion);
    }

    /**
     * Check if token version is valid
     */
    private function isTokenVersionValid(int $userId, string $tokenVersion): bool
    {
        $key = "token_version:{$userId}";
        $currentVersion = $this->redis->get($key);

        return $currentVersion === $tokenVersion;
    }

    /**
     * Invalidate all tokens for user (for logout)
     */
    public function invalidateAllTokensForUser(int $userId): void
    {
        $key = "token_version:{$userId}";
        $this->redis->del($key);
    }
}
