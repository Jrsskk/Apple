<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectByRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->routeIs('dashboard')) {
            return $next($request);
        }

        return $next($request);
    }
}
