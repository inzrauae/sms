<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request) => '/login?next=' . urlencode($request->getPathInfo())
        );

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every portal route family responds with {status, message} JSON,
        // mirroring the reference app's single error-handling middleware.
        $exceptions->render(function (Throwable $e, Request $request) {
            $isPortalRoute = $request->is('api/*', 'app/*', 'admin/*', 'auth/*');
            if (!$isPortalRoute) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                $message = $request->is('api/*') ? 'Invalid or missing API token.' : 'Sign in to continue.';

                return response()->json(['status' => 'error', 'message' => $message], 401);
            }

            if ($e instanceof ValidationException) {
                return response()->json(['status' => 'error', 'message' => $e->validator->errors()->first()], $e->status);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: ($status === 404 ? 'No such endpoint.' : 'That request did not work.'),
                ], $status);
            }

            if (!config('app.debug')) {
                return response()->json(['status' => 'error', 'message' => 'Something went wrong on our side.'], 500);
            }

            return null;
        });
    })->create();
