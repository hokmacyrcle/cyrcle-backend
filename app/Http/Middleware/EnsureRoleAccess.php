<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default access control.
 *
 *  - employer: full access (unchanged behaviour).
 *  - employee: only the self-service endpoints listed below.
 *  - anything else: no access.
 *
 * Applied to the whole authenticated API group, so new routes are employer-only
 * unless they are added to EMPLOYEE_ALLOWED.
 */
class EnsureRoleAccess
{
    /** Paths (relative to /api) an employee may call. '*' matches any suffix. */
    private const EMPLOYEE_ALLOWED = [
        'user',
        'logout',
        'refresh',
        'me',
        'me/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user();

        if (!$user) {
            return $this->deny(401, 'Unauthenticated');
        }

        $role = $user->role ?? 'employer';

        if ($role === 'employer') {
            return $next($request);
        }

        if ($role === 'employee' && $request->is(...$this->employeePatterns())) {
            return $next($request);
        }

        return $this->deny(403, 'You do not have access to this resource');
    }

    private function employeePatterns(): array
    {
        return array_map(fn ($p) => 'api/' . $p, self::EMPLOYEE_ALLOWED);
    }

    private function deny(int $code, string $message): Response
    {
        return response()->json([
            'status' => false,
            'status_code' => $code,
            'message' => $message,
        ], $code);
    }
}
