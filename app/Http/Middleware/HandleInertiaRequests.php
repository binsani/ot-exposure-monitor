<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Tenancy\TenantContext;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role,
            ],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'agent_token' => fn () => $request->session()->get('agent_token')],
        ];
    }
}
