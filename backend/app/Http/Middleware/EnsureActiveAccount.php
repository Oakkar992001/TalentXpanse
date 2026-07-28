<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->status === 'suspended', 403, 'This account is not currently allowed to access TalentXpanse.');

        return $next($request);
    }
}
