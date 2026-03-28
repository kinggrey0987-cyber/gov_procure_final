<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomainAccess
{
    /**
     * Restrict access by domain-level gate to enforce route partitioning.
     */
    public function handle(Request $request, Closure $next, string $domain): Response
    {
        abort_unless($request->user(), 401);

        $ability = 'domain.'.$domain.'.access';

        abort_unless($request->user()?->can($ability), 403);

        return $next($request);
    }
}
