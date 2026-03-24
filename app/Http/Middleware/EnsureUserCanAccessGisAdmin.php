<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessGisAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('gis-admin.login');
        }

        if ($user->is_super_admin || $user->hasPermission('access.manage')) {
            return $next($request);
        }

        abort(403, 'У вас немає доступу до GIS admin.');
    }
}