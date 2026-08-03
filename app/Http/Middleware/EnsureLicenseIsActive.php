<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasActiveLicense()) {
            return redirect()->route('license.expired');
        }

        return $next($request);
    }
}
