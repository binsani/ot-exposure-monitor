<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\FallbackDrill;
use App\Models\ManualFallbackAuthorization;
use App\Models\Organization;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReadinessController extends Controller
{
    public function index(): Response
    {
        $organization = Organization::findOrFail(app(TenantContext::class)->id());

        return Inertia::render('Readiness/Index', [
            'authorizations' => ManualFallbackAuthorization::with(['user:id,name', 'site:id,name', 'asset:id,name', 'drills' => fn ($q) => $q->latest('drilled_at')])->latest()->get()->map(function ($authorization) {
                $authorization->is_stale = ! $authorization->last_drilled_at || $authorization->last_drilled_at->lt(now()->subMonths($organization->fallback_drill_stale_months));

                return $authorization;
            }),
            'sites' => Site::with('assets:id,site_id,name')->orderBy('name')->get(['id', 'name']),
            'users' => $organization->users()->orderBy('name')->get(['users.id', 'name']),
            'stale_months' => $organization->fallback_drill_stale_months,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['fallback_drill_stale_months' => ['required', 'integer', 'min:1', 'max:60']]);
        Organization::findOrFail(app(TenantContext::class)->id())->update($data);

        return back()->with('success', 'Drill reminder period updated.');
    }

    public function authorizeOperator(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['site_id' => ['required', 'exists:sites,id'], 'asset_id' => ['nullable', 'exists:assets,id'], 'user_id' => ['required', 'exists:users,id'], 'granted_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:3000']]);
        Site::findOrFail($data['site_id']);
        $member = Organization::find(app(TenantContext::class)->id())->users()->whereKey($data['user_id'])->exists();
        $asset = isset($data['asset_id']) ? Asset::find($data['asset_id']) : null;
        if (! $member || ($asset && $asset->site_id !== (int) $data['site_id'])) {
            throw ValidationException::withMessages(['user_id' => 'The person and asset must belong to this organization and site.']);
        }
        $authorization = ManualFallbackAuthorization::create($data);
        $this->audit($request, 'fallback.authorization_granted', $authorization->id, $data);

        return back()->with('success', 'Manual-operation authorization recorded.');
    }

    public function drill(Request $request, ManualFallbackAuthorization $authorization): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['drilled_at' => ['required', 'date', 'before_or_equal:today'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'participants' => ['nullable', 'array'], 'participants.*' => ['string', 'max:255'], 'notes' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $authorization, $data): void {
            $drill = FallbackDrill::create([...$data, 'manual_fallback_authorization_id' => $authorization->id, 'logged_by' => $request->user()->id]);
            if (! $authorization->last_drilled_at || $authorization->last_drilled_at->lt($data['drilled_at'])) {
                $authorization->update(['last_drilled_at' => $data['drilled_at']]);
            }
            $this->audit($request, 'fallback.drill_logged', $authorization->id, ['drill_id' => $drill->id, ...$data]);
        });

        return back()->with('success', 'Readiness drill logged.');
    }

    private function authorizeEditor(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }

    private function audit(Request $request, string $action, int $id, array $metadata): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => ManualFallbackAuthorization::class, 'subject_id' => $id, 'occurred_at' => now(), 'metadata' => $metadata]);
    }
}
