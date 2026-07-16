<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ViewerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'viewer') {
            abort(403);
        }
        return $next($request);
    }
}
