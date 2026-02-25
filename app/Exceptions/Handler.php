<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Services\LoggerService;
use App\Traits\ApiResponseTrait;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;
    
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
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions
     */
    protected function handleApiException($request, Throwable $e)
    {
        $correlationId = $request->header('X-Correlation-ID');
        if ($correlationId) {
            $this->setCorrelationId($correlationId);
        }

        // Validation exceptions
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse(
                $e->errors(),
                'Validation failed'
            );
        }

        // Authentication exceptions
        if ($e instanceof AuthenticationException) {
            return $this->unauthorizedResponse($e->getMessage());
        }

        // Authorization exceptions
        if ($e instanceof AuthorizationException) {
            return $this->forbiddenResponse($e->getMessage());
        }

        // Model not found
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return $this->notFoundResponse("{$model} not found");
        }

        // Route not found
        if ($e instanceof NotFoundHttpException) {
            return $this->notFoundResponse('Endpoint not found');
        }

        // Method not allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse(
                'Method not allowed',
                405
            );
        }

        // Generic server error
        return $this->serverErrorResponse(
            app()->isProduction() ? 'Internal server error' : $e->getMessage(),
            $e
        );
    }
}
