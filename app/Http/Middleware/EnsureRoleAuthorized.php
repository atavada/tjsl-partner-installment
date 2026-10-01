<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAuthorized
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isSystemAdmin()) {
            return $next($request);
        }

        $allowedRoles = array_map(
            fn (string $role) => Role::tryFrom($role)?->value ?? $role,
            $roles
        );

        if (! in_array($user->role->value, $allowedRoles, true)) {
            abort(403, 'Unauthorized. Role not authorized for this resource.');
        }

        return $next($request);
    }
}
