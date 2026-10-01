<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    /**
     * Handle incoming request and bind a correlation ID across the lifecycle.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerValue = $request->header('X-Correlation-ID') ?? $request->header('X-Request-ID');

        $correlationId = is_string($headerValue) && Str::isUuid($headerValue)
            ? $headerValue
            : (string) Str::uuid();

        // Attach to request headers and attributes
        $request->headers->set('X-Correlation-ID', $correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        // Record in Laravel context for log attribution
        Context::add('correlation_id', $correlationId);

        // Sync to AuditService instance
        if (app()->bound(AuditService::class)) {
            app(AuditService::class)->setCorrelationId($correlationId);
        }

        $response = $next($request);

        // Append to response headers
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
