<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Services\LoggerService;

class Handler extends ExceptionHandler
{
 /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];


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
            // Defer to default logging; LoggerService is used in render for API
        });
    }

    protected function shouldReturnJson($request, Throwable $e)
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldReturnJson($request, $e)) {
            // Log with structured LoggerService
            try {
                LoggerService::logApiError($e, $request);
            } catch (Throwable $logError) {
                // swallow logging issues to not mask original error
            }

            $status = 500;
            $message = 'Internal server error';

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: $message;
            }

            // ValidationException handled by parent to include errors, but normalize payload
            if (method_exists($e, 'errors')) {
                $status = 422;
                $errors = $e->errors();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                    'timestamp' => now()->toISOString(),
                ], $status);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'timestamp' => now()->toISOString(),
            ], $status);
        }

        return parent::render($request, $e);
    }
}
