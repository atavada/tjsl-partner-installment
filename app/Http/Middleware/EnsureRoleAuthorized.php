<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Services\AuditService;
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
            if (app()->bound(AuditService::class)) {
                app(AuditService::class)->logAuthFailure(
                    action: 'authorization_failure',
                    target: null,
                    delta: [
                        'path' => $request->path(),
                        'method' => $request->method(),
                        'required_roles' => $allowedRoles,
                        'user_role' => $user->role->value,
                    ],
                    reason: 'Unauthorized. Role not authorized for this resource.',
                    actor: $user,
                );
            }

            abort(403, 'Unauthorized. Role not authorized for this resource.');
        }

        return $next($request);
    }
}
