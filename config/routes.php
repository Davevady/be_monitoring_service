<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use App\Controller\{AppRuleController, DashboardController, MessageRuleController, MonitorController};
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

Router::addGroup('/dashboard', function () {
    Router::get('/overview', [DashboardController::class, 'overview']);
});

Router::addGroup('/monitor', function () {
    Router::get('/server', [MonitorController::class, 'server']);
    Router::get('/trace', [MonitorController::class, 'traceByCorrelation']);
    Router::get('/violation/by-app', [MonitorController::class, 'violationsByApp']);
    Router::get('/violation/by-message', [MonitorController::class, 'violationsByMessage']);
});

Router::addGroup('/rules/app', function () {
    Router::get('', [AppRuleController::class, 'index']);
    Router::post('', [AppRuleController::class, 'store']);
    Router::get('/{id:\d+}', [AppRuleController::class, 'show']);
    Router::put('/{id:\d+}', [AppRuleController::class, 'update']);
    Router::delete('/{id:\d+}', [AppRuleController::class, 'destroy']);
});

Router::addGroup('/rules/message', function () {
    Router::get('', [MessageRuleController::class, 'index']);
    Router::post('', [MessageRuleController::class, 'store']);
    Router::get('/{id:\d+}', [MessageRuleController::class, 'show']);
    Router::put('/{id:\d+}', [MessageRuleController::class, 'update']);
    Router::delete('/{id:\d+}', [MessageRuleController::class, 'destroy']);
});
