<?php

use App\Http\Middleware\EnsurePasswordIsPersonalized;
use App\Http\Middleware\EnsureRole;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'password.personalized' => EnsurePasswordIsPersonalized::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $handleExpiredSession = function (Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expiree. Veuillez vous reconnecter.',
                ], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('status', 'Votre session a expire apres inactivite. Veuillez vous reconnecter.');
        };

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($handleExpiredSession) {
            return $handleExpiredSession($request);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($handleExpiredSession) {
            if ((int) $exception->getStatusCode() !== 419) {
                return null;
            }

            return $handleExpiredSession($request);
        });

        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = 'Fichier trop volumineux pour la configuration serveur actuelle. Utilisez le lanceur "serve-unlimited-upload".';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['justificatifs' => $message])->withInput();
        });
    })->create();
