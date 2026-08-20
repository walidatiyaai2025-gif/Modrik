<?php

use App\Exceptions\ApiProblemException;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthenticateModrikSession;
use App\Http\Middleware\AuthenticateProductionSession;
use App\Http\Middleware\CaptureRuntimeDiagnostics;
use App\Http\Middleware\FixtureBearerAuthentication;
use App\Http\Middleware\RequireContentRole;
use App\Http\Middleware\RequireRecentAuthentication;
use App\Http\Middleware\RequireVerifiedEmailForPasswordAccount;
use App\Support\ApiResponse;
use App\Support\CorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->append(CaptureRuntimeDiagnostics::class);
        $middleware->alias([
            'auth.fixture' => FixtureBearerAuthentication::class,
            'auth.modrik' => AuthenticateModrikSession::class,
            'auth.production' => AuthenticateProductionSession::class,
            'auth.recent' => RequireRecentAuthentication::class,
            'auth.verified-password' => RequireVerifiedEmailForPasswordAccount::class,
            'auth.content' => RequireContentRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(
            fn (ApiProblemException $exception, Request $request) => ApiResponse::problem($request, $exception),
        );
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response): Response {
            $request = request();
            if ($request instanceof Request) {
                $response->headers->set(CorrelationId::HEADER, CorrelationId::assign($request));
            }

            return $response;
        });
    })->create();
