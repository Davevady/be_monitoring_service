<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Permission;
use App\Service\PermissionService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class PermissionSyncCommand extends HyperfCommand
{
    #[Inject]
    protected DispatcherFactory $dispatcherFactory;
    
    #[Inject]
    protected PermissionService $permissionService;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('permission:sync');
    }

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Sync permissions from route names to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->line('Starting permission sync...');

        try {
            $routes = $this->getRoutesWithNames();
            
            if (empty($routes)) {
                $this->warn('No routes with names found.');
                return 0;
            }

            $this->info('Found ' . count($routes) . ' routes with names.');

            $permissions = $this->convertRoutesToPermissions($routes);

            $result = $this->permissionService->syncPermissionsFromRoutes($permissions);

            if ($result['success']) {
                $this->info('Permission sync completed successfully!');
                $this->line('Synced: ' . $result['data']['synced'] . ' permissions');
                $this->line('Updated: ' . $result['data']['updated'] . ' permissions');
                $this->line('Total processed: ' . $result['data']['total'] . ' permissions');
            } else {
                $this->error('Permission sync failed: ' . $result['message']);
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Permission sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function getRoutesWithNames(): array
    {
        try {
            $router = $this->dispatcherFactory->getRouter('http');
            $routes = $router->getData();

            $namedRoutesByName = [];

            foreach ($routes as $method => $routeList) {
                if (!is_array($routeList)) {
                    continue;
                }
                foreach ($routeList as $entry) {
                    if (is_array($entry)) {
                        foreach ($entry as $path => $handler) {
                            if (is_array($handler)) {
                                $options = $handler['options'] ?? [];
                                $name = $options['name'] ?? null;
                                if ($name) {
                                    $namedRoutesByName[$name] = [
                                        'method' => $this->getMethodName((int) $method),
                                        'path' => $handler['route'] ?? $path,
                                        'name' => $name,
                                        'handler' => $handler['callback'] ?? null,
                                    ];
                                }
                            } elseif (is_object($handler)) {
                                $options = $handler->options ?? [];
                                $name = $options['name'] ?? null;
                                if ($name) {
                                    $namedRoutesByName[$name] = [
                                        'method' => $this->getMethodName((int) $method),
                                        'path' => $handler->route ?? $path,
                                        'name' => $name,
                                        'handler' => $handler->callback ?? null,
                                    ];
                                }
                            }
                        }
                        continue;
                    }

                    if (is_object($entry) && isset($entry->routeMap) && is_array($entry->routeMap)) {
                        foreach ($entry->routeMap as $map) {
                            if (is_array($map)) {
                                foreach ($map as $maybeRouteData) {
                                    if (is_array($maybeRouteData)) {
                                        $options = $maybeRouteData['options'] ?? [];
                                        $name = $options['name'] ?? null;
                                        if ($name) {
                                            $namedRoutesByName[$name] = [
                                                'method' => $this->getMethodName((int) $method),
                                                'path' => $maybeRouteData['route'] ?? null,
                                                'name' => $name,
                                                'handler' => $maybeRouteData['callback'] ?? null,
                                            ];
                                        }
                                    } elseif (is_object($maybeRouteData)) {
                                        $options = $maybeRouteData->options ?? [];
                                        $name = $options['name'] ?? null;
                                        if ($name) {
                                            $namedRoutesByName[$name] = [
                                                'method' => $this->getMethodName((int) $method),
                                                'path' => $maybeRouteData->route ?? null,
                                                'name' => $name,
                                                'handler' => $maybeRouteData->callback ?? null,
                                            ];
                                        }
                                    }
                                }
                            } elseif (is_object($map)) {
                                $options = $map->options ?? [];
                                $name = $options['name'] ?? null;
                                if ($name) {
                                    $namedRoutesByName[$name] = [
                                        'method' => $this->getMethodName((int) $method),
                                        'path' => $map->route ?? null,
                                        'name' => $name,
                                        'handler' => $map->callback ?? null,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            return array_values($namedRoutesByName);
        } catch (\Throwable $e) {
            $this->error('Failed to get routes: ' . $e->getMessage());
            return [];
        }
    }

    private function getMethodName(int $method): string
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];
        return $methods[$method] ?? 'UNKNOWN';
    }

    private function convertRoutesToPermissions(array $routes): array
    {
        $permissions = [];
        $groupedByResource = [];

        // Group routes by resource (first part of route name)
        foreach ($routes as $route) {
            $name = $route['name'];
            $parts = explode('.', $name);
            
            $resource = $parts[0]; // e.g., 'users', 'roles', 'dashboard'
            $action = $parts[1] ?? 'index'; // e.g., 'index', 'store', 'update'

            if (!isset($groupedByResource[$resource])) {
                $groupedByResource[$resource] = [];
            }

            $groupedByResource[$resource][] = [
                'name' => $name,
                'resource' => $resource,
                'action' => $action,
                'method' => $route['method'],
                'path' => $route['path'],
            ];
        }

        // Resources yang tidak perlu menu permission
        $skipMenuFor = ['auth', 'profile'];

        // Convert to permission format with proper types
        foreach ($groupedByResource as $resource => $resourceRoutes) {
            $group = $this->determineGroup($resource);
            
            // ✅ 1. Add MENU permission (skip untuk auth & profile)
            if (!in_array($resource, $skipMenuFor)) {
                $permissions[] = [
                    'name' => "{$resource}.menu",
                    'display_name' => $this->generateResourceDisplayName($resource),
                    'description' => "Access to {$resource} menu",
                    'resource' => $resource,
                    'permission_type' => Permission::TYPE_MENU,
                    'group' => $group,
                ];
            }

            // ✅ 2. Add ACTION permissions dari semua routes
            foreach ($resourceRoutes as $route) {
                $permissions[] = [
                    'name' => $route['name'],
                    'display_name' => $this->generateDisplayName($resource, $route['action']),
                    'description' => $this->generateDescription($route['method'], $route['path']),
                    'resource' => $resource,
                    'permission_type' => Permission::TYPE_ACTION,
                    'group' => $group,
                ];
            }
        }

        return $permissions;
    }

    private function determineGroup(string $resource): string
    {
        // Map resources to logical groups
        $groupMap = [
            'users' => 'user-management',
            'roles' => 'user-management',
            'permissions' => 'user-management',
            'dashboard' => 'dashboard',
            'monitor' => 'monitoring',
            'rules' => 'rules-management',
            'profile' => 'account',
            'auth' => 'authentication',
        ];

        return $groupMap[$resource] ?? 'general';
    }

    private function generateResourceDisplayName(string $resource): string
    {
        $displayNames = [
            'users' => 'Users',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'dashboard' => 'Dashboard',
            'monitor' => 'Monitoring',
            'rules' => 'Rules',
            'profile' => 'Profile',
            'auth' => 'Authentication',
        ];

        return $displayNames[$resource] ?? ucfirst(str_replace('-', ' ', $resource));
    }

    private function generateDisplayName(string $resource, string $action): string
    {
        $actionMap = [
            // View actions
            'index' => 'View List',
            'show' => 'View Detail',
            
            // Form actions
            'create' => 'Create Form',
            'edit' => 'Edit Form',
            
            // Data manipulation actions
            'store' => 'Store Data',
            'update' => 'Update Data',
            'destroy' => 'Delete Data',
            
            // Auth actions
            'login' => 'Login',
            'logout' => 'Logout',
            'refresh' => 'Refresh Token',
            'forgot-password' => 'Forgot Password',
            'reset-password' => 'Reset Password',
            'check-reset-token' => 'Check Reset Token',
            'change-password' => 'Change Password',
            
            // Permission actions
            'permissions' => 'View Permissions',
            'sync-permissions' => 'Sync Permissions',
            'groups' => 'View Groups',
            'grouped' => 'View Grouped',
            'structured' => 'View Structured',
            'by-resource' => 'View By Resource',
            
            // Dashboard actions
            'overview' => 'View Overview',
            'log-trends' => 'View Log Trends',
            'app-performance' => 'View App Performance',
            
            // Monitor actions
            'server' => 'View Server',
            'trace' => 'View Trace',
            'violations-by-app' => 'View Violations by App',
            'violations-by-message' => 'View Violations by Message',
        ];

        $displayAction = $actionMap[$action] ?? ucfirst(str_replace('-', ' ', $action));
        $resourceName = $this->generateResourceDisplayName($resource);
        
        return "{$displayAction} {$resourceName}";
    }

    private function generateDescription(string $method, string $path): string
    {
        return "{$method} {$path}";
    }
}