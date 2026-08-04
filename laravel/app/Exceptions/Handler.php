<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Render an expired CSRF token as a recoverable web flow.
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof TokenMismatchException) {
            return $this->expiredSessionResponse($request);
        }

        return parent::render($request, $e);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    private function expiredSessionResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'La sesión expiró. Recargá la página e intentá nuevamente.',
            ], 419);
        }

        $target = $request->routeIs('login.post')
            ? route('login')
            : url()->previous();

        return redirect($target)->withErrors([
            'session' => 'La sesión expiró. Por seguridad, volvé a ingresar tus datos.',
        ]);
    }
}
