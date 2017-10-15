<?php

namespace App\Http\Middleware;

use Closure;

class TokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (empty(env('API_TOKEN'))) abort(500);

        if ($request->header('X-WhatCeleb-Auth') != env('API_TOKEN')) {
            abort(403);
        }

        return $next($request);
    }
}
