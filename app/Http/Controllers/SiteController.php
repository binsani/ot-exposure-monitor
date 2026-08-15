<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Sites/Index', [
            'sites' => Site::with(['processAreas:id,site_id,name,description'])->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeChange($request);
        Site::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'criticality_tier' => ['required', 'in:standard,important,critical'],
        ]));

        return back()->with('success', 'Site added.');
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeChange($request);
        abort_if($site->assets()->exists(), 422, 'Move or remove this site’s assets first.');
        $site->delete();

        return back()->with('success', 'Site removed.');
    }

    private function authorizeChange(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }
}
