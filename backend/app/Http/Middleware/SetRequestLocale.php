<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = str_starts_with(strtolower($request->header('Accept-Language', 'en')), 'my') ? 'my' : 'en';
        app()->setLocale($locale);

        return $next($request);
    }
}
