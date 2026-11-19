<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\ControlSearchIndex;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            if (file_exists(base_path('routes/api.php'))) {
                Route::prefix('api')
                    ->group(base_path('routes/api.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            CanonicalHost::class,
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
            ControlSearchIndex::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'checkout/paystack/webhook',
        ]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'api.key' => ApiKeyMiddleware::class,
        ]);
        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('checkout', 'checkout/*', 'cart', 'cart/*')) {
                return redirect()->route('cart.index')
                    ->with('error', 'Your session expired before we could place the order. Your cart is still saved — please go to checkout and try again.');
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || ! $request->is('chat', 'chat/*')) {
                return null;
            }
            if ($e instanceof ValidationException || $e instanceof TokenMismatchException) {
                return null;
            }
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return null;
            }

            $email = (string) config('business.email', 'sales@urbanfocus.co.za');
            $phone = (string) config('business.phone', '087 550 1813');

            return response()->json([
                'message' => 'Sorry, chat is having trouble. Email '.$email.' or call '.$phone.'.',
            ], 500);
        });
    })->create();
