<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

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
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
     * Register error view paths.
     * Override to prevent errors when View service is not yet available.
     *
     * @return void
     */
    protected function registerErrorViewPaths()
    {
        // Only register error view paths if the View service is available
        // This prevents "Target class [view] does not exist" errors during early bootstrapping
        try {
            if ($this->container->bound('view')) {
                parent::registerErrorViewPaths();
            }
        } catch (\Exception $e) {
            // Silently fail if view service is not available
            // This is expected during early bootstrapping
        }
    }
}
