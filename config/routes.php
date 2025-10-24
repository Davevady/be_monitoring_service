<?php

use App\Controller\{
    AppRuleController, 
    AuthenticationController,
    DashboardController, 
    MessageRuleController, 
    MonitorController,
    UserController,
    PermissionController,
    RoleController
};
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::get('/favicon.ico', function () { return ''; });

// ============================================================================
// AUTHENTICATION ROUTES (Public - No Auth Required)
// ============================================================================
Router::addGroup('/auth', function () {
    Router::post('/login', [AuthenticationController::class, 'login'], ['name' => 'auth.login']);
    Router::post('/refresh', [AuthenticationController::class, 'refresh'], ['name' => 'auth.refresh']);
    Router::post('/forgot-password', [AuthenticationController::class, 'forgotPassword'], ['name' => 'auth.forgot-password']);
    Router::post('/reset-password', [AuthenticationController::class, 'resetPassword'], ['name' => 'auth.reset-password']);
    Router::post('/check-reset-token', [AuthenticationController::class, 'checkResetToken'], ['name' => 'auth.check-reset-token']);
});

// ============================================================================
// AUTHENTICATED AUTH ROUTES (Protected)
// ============================================================================
Router::addGroup('/auth', function () {
    Router::post('/logout', [AuthenticationController::class, 'logout'], ['name' => 'auth.logout']);
    Router::put('/change-password', [AuthenticationController::class, 'changePassword'], ['name' => 'auth.change-password']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// PROFILE ROUTES (Protected)
// ============================================================================
Router::addGroup('/profile', function () {
    Router::get('', [AuthenticationController::class, 'profile'], ['name' => 'profile.index']);
    Router::put('', [AuthenticationController::class, 'updateProfile'], ['name' => 'profile.update']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// DASHBOARD ROUTES (Protected)
// ============================================================================
Router::addGroup('/dashboard', function () {
    Router::get('/overview', [DashboardController::class, 'overview'], ['name' => 'dashboard.index']);
    Router::get('/log-trends', [DashboardController::class, 'logTrends'], ['name' => 'dashboard.log-trends']);
    Router::get('/app-performance', [DashboardController::class, 'appPerformance'], ['name' => 'dashboard.app-performance']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// MONITOR ROUTES (Protected)
// ============================================================================
Router::addGroup('/monitor', function () {
    Router::get('/server', [MonitorController::class, 'server'], ['name' => 'monitor.index']);
    Router::get('/trace', [MonitorController::class, 'traceByCorrelation'], ['name' => 'trace.index']); // Query param: ?correlation_id=xxx
    Router::get('/violations/by-app', [MonitorController::class, 'violationsByApp'], ['name' => 'violations-by-app.index']);
    Router::get('/violations/by-message', [MonitorController::class, 'violationsByMessage'], ['name' => 'violations-by-message.index']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// RULES ROUTES (Protected)
// ============================================================================
Router::addGroup('/message', function () {
    // Message Rules - Rules berdasarkan message/log
    Router::addGroup('/rules', function () {
        Router::get('', [MessageRuleController::class, 'index'], ['name' => 'message-rules.index']);
        Router::post('', [MessageRuleController::class, 'store'], ['name' => 'message-rules.store']);
        Router::get('/{id:\d+}', [MessageRuleController::class, 'show'], ['name' => 'message-rules.show']);
        Router::put('/{id:\d+}', [MessageRuleController::class, 'update'], ['name' => 'message-rules.update']);
        Router::delete('/{id:\d+}', [MessageRuleController::class, 'destroy'], ['name' => 'message-rules.destroy']);
    });
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);
Router::addGroup('/app', function () {
    // App Rules - Rules berdasarkan aplikasi
    Router::addGroup('/rules', function () {
        Router::get('', [AppRuleController::class, 'index'], ['name' => 'app-rules.index']);
        Router::post('', [AppRuleController::class, 'store'], ['name' => 'app-rules.store']);
        Router::get('/{id:\d+}', [AppRuleController::class, 'show'], ['name' => 'app-rules.show']);
        Router::put('/{id:\d+}', [AppRuleController::class, 'update'], ['name' => 'app-rules.update']);
        Router::delete('/{id:\d+}', [AppRuleController::class, 'destroy'], ['name' => 'app-rules.destroy']);
    });
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// ROLE MANAGEMENT ROUTES (Protected)
// ============================================================================
Router::addGroup('/roles', function () {
    // Metadata routes first (literal paths)
    Router::get('/{id:\d+}/permissions', [RoleController::class, 'permissions'], ['name' => 'roles.permissions']);
    Router::put('/{id:\d+}/permissions', [RoleController::class, 'syncPermissions'], ['name' => 'roles.sync-permissions']);
    
    // Standard CRUD routes
    Router::get('', [RoleController::class, 'index'], ['name' => 'roles.index']);
    Router::post('', [RoleController::class, 'store'], ['name' => 'roles.store']);
    Router::get('/{id:\d+}', [RoleController::class, 'show'], ['name' => 'roles.show']);
    Router::put('/{id:\d+}', [RoleController::class, 'update'], ['name' => 'roles.update']);
    Router::delete('/{id:\d+}', [RoleController::class, 'destroy'], ['name' => 'roles.destroy']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// PERMISSION MANAGEMENT ROUTES (Protected)
// ============================================================================
Router::addGroup('/permissions', function () {
    // Metadata routes first (literal paths)
    Router::get('/groups', [PermissionController::class, 'groups'], ['name' => 'permissions.groups']);
    Router::get('/grouped', [PermissionController::class, 'grouped'], ['name' => 'permissions.grouped']);
    Router::get('/structured', [PermissionController::class, 'structured'], ['name' => 'permissions.structured']);
    Router::get('/by-resource', [PermissionController::class, 'byResource'], ['name' => 'permissions.by-resource']);
    
    // Standard CRUD routes
    Router::get('', [PermissionController::class, 'index'], ['name' => 'permissions.index']);
    Router::post('', [PermissionController::class, 'store'], ['name' => 'permissions.store']);
    Router::get('/{id:\d+}', [PermissionController::class, 'show'], ['name' => 'permissions.show']);
    Router::put('/{id:\d+}', [PermissionController::class, 'update'], ['name' => 'permissions.update']);
    Router::delete('/{id:\d+}', [PermissionController::class, 'destroy'], ['name' => 'permissions.destroy']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);

// ============================================================================
// USER MANAGEMENT ROUTES (Protected)
// ============================================================================
Router::addGroup('/users', function () {
    // Metadata/helper routes first (literal paths)
    Router::get('/create', [UserController::class, 'create'], ['name' => 'users.create']);
    Router::get('/{id:\d+}/edit', [UserController::class, 'edit'], ['name' => 'users.edit']);
    Router::get('/{id:\d+}/permissions', [UserController::class, 'permissions'], ['name' => 'users.permissions']);
    
    // Standard CRUD routes
    Router::get('', [UserController::class, 'index'], ['name' => 'users.index']);
    Router::post('', [UserController::class, 'store'], ['name' => 'users.store']);
    Router::get('/{id:\d+}', [UserController::class, 'show'], ['name' => 'users.show']);
    Router::put('/{id:\d+}', [UserController::class, 'update'], ['name' => 'users.update']);
    Router::delete('/{id:\d+}', [UserController::class, 'destroy'], ['name' => 'users.destroy']);
}, ['middleware' => [\App\Middleware\AuthMiddleware::class]]);
