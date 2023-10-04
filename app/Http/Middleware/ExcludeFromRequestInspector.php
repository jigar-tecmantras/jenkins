<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcludeFromRequestInspector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Exclude the specified URL from being logged in the request inspector
        if ($request->is('api/upload_schedule_post')) {
            return $next($request)->withoutMiddleware(\Inspector\Laravel\Middleware\RecordVisits::class);
        }
    
        return $next($request);

        return $next($request);
    }
}
