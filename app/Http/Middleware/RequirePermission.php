<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                ApiResponse::error('UNAUTHORIZED', 'Authentication required.'),
                401
            );
        }

        if (!$user->hasPermission($permission)) {
            return response()->json(
                ApiResponse::error('FORBIDDEN', 'Permission denied.'),
                403
            );
        }

        return $next($request);
    }
}