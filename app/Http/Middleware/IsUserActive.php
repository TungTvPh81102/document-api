<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->enable) {
            auth()->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been disabled',
            ], 403);
        }

        if ($user && $user->locked_at && $user->locked_at > now()) {
            auth()->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account is locked',
            ], 403);
        }

        return $next($request);
    }
}
