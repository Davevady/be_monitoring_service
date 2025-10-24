<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\JwtService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as HandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected JwtService $jwtService;

    #[Inject]
    protected HttpResponse $response;

    public function process(RequestInterface $request, HandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!$authorization) {
            return $this->unauthorized('Authorization header missing');
        }

        $token = str_replace('Bearer ', '', $authorization);

        $payload = $this->jwtService->validateToken($token);
        if (!$payload) {
            return $this->unauthorized('Invalid or expired token');
        }

        $user = $this->jwtService->getUserFromToken($token);
        if (!$user) {
            return $this->unauthorized('User not found');
        }

        // ✅ tambahkan atribut user
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return $this->response->json([
            'success' => false,
            'message' => $message,
        ])->withStatus(401);
    }
}
