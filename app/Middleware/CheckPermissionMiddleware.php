<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helper\StandardJsonResponse;
use App\Model\User;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Check Permission Middleware
 * 
 * Checks if the authenticated user has permission to access the route
 */
class CheckPermissionMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected RequestInterface $request;

    /**
     * Process an incoming server request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): PsrResponse
    {
        try {
            // Get authenticated user
            $user = $request->getAttribute('user');
            
            if (!$user instanceof User) {
                return StandardJsonResponse::unauthorized(
                    $this->getResponse($request),
                    'User not authenticated'
                );
            }

            // Get route name from request
            $routeName = $this->getRouteName($request);
            
            if (!$routeName) {
                // If no route name, allow access (for routes without permission check)
                return $handler->handle($request);
            }

            // Check if user has permission
            if (!$user->hasPermission($routeName)) {
                return StandardJsonResponse::forbidden(
                    $this->getResponse($request),
                    "You don't have permission to access this resource"
                );
            }

            return $handler->handle($request);
        } catch (\Exception $e) {
            return StandardJsonResponse::serverError(
                $this->getResponse($request),
                'Permission check failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get route name from request
     */
    private function getRouteName(ServerRequestInterface $request): ?string
    {
        try {
            $dispatched = $request->getAttribute(Dispatched::class);
            
            if (!$dispatched || !$dispatched->handler) {
                return null;
            }

            $handler = $dispatched->handler;
            
            // Get route name from handler options
            if (isset($handler->options['name'])) {
                return $handler->options['name'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get response instance from request
     */
    private function getResponse(ServerRequestInterface $request): HttpResponse
    {
        return \Hyperf\Context\ApplicationContext::getContainer()->get(HttpResponse::class);
    }
}
