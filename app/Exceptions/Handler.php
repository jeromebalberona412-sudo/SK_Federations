<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle database connection errors (offline/network issues)
        if ($e instanceof QueryException) {
            $errorMessage = $e->getMessage();
            
            // Check if it's a connection error
            if (
                str_contains($errorMessage, 'could not translate host name') ||
                str_contains($errorMessage, 'Connection refused') ||
                str_contains($errorMessage, 'Connection timed out') ||
                str_contains($errorMessage, 'No route to host') ||
                str_contains($errorMessage, 'Network is unreachable') ||
                str_contains($errorMessage, 'SQLSTATE[08006]') ||
                str_contains($errorMessage, 'SQLSTATE[HY000]')
            ) {
                return response()->view('errors.network-error', [], 503);
            }
        }

        return parent::render($request, $e);
    }
}
