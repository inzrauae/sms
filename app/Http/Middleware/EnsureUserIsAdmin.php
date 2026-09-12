<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Sign in to continue.');
        }

        if (!$user->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'Admins only.');
            }

            return redirect('/dashboard');
        }

        return $next($request);
    }
}
