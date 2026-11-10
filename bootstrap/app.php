<?php

use App\Exceptions\InvalidTransition;
use App\Models\Inspection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Laravel's own message for a missing model names the model class.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $missing = $e->getPrevious();

            if ($missing instanceof ModelNotFoundException && $missing->getModel() === Inspection::class) {
                return response()->json(['message' => 'Inspection not found.'], 404);
            }
        });

        // A refused step is an expected outcome, not an error to log.
        $exceptions->dontReport(InvalidTransition::class);
        $exceptions->render(fn (InvalidTransition $e) => response()->json([
            'message' => $e->getMessage(),
            'current_status' => $e->currentStatus->value,
            'allowed_action' => $e->allowedAction,
        ], 409));
    })->create();
