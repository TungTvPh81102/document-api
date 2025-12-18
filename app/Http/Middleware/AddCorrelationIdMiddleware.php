<?php 

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddCorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::orderedUuid();
        
        $request->headers->set('X-Correlation-ID', $correlationId);
        
        $response = $next($request);
        
        if (method_exists($response, 'header')) {
            $response->header('X-Correlation-ID', $correlationId);
        }
        
        return $response;
    }
}