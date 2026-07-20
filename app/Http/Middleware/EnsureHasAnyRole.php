<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasAnyRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user() && ! $request->user()->hasAnyRole()) {
            return inertia('Auth/NoPermission');
        }

        return $next($request);
    }
}
