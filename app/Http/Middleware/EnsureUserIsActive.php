<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $status = $user->status instanceof UserStatus ? $user->status->value : (string) ($user->status ?? 'active');
        $isActive = $user->is_active ?? ($status === UserStatus::Active->value);

        if (! $isActive || in_array($status, [UserStatus::Inactive->value, UserStatus::Archived->value], true)) {
            return response()->json([
                'message' => 'Your account is inactive or archived. Please contact an administrator.',
            ], 403);
        }

        return $next($request);
    }
}
