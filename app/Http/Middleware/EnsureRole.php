<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $user = $request->user();

        // Check Spatie role OR fallback to user->role column
        $hasRole = $user->hasAnyRole($roles)
            || in_array($user->role, $roles);

        if (! $hasRole) {
            return response()->json([
                'message' => 'Unauthorized. Insufficient role.',
            ], 403);
        }

        return $next($request);
    }
}