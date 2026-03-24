<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccessManage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(
                ApiResponse::error('UNAUTHORIZED', 'Authentication required.'),
                401
            );
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->hasPermission('access.manage')) {
            return response()->json(
                ApiResponse::error('FORBIDDEN', 'Access management permission required.'),
                403
            );
        }

        return $next($request);
    }
}