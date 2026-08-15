<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $user = $request->user();

        if ($user && $user->current_organization_id && $user->organizations()
            ->whereKey($user->current_organization_id)->exists()) {
            $context->set((int) $user->current_organization_id);
        }

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
