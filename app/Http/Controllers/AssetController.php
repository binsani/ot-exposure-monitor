<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $assets = Asset::query()
            ->with(['site:id,name', 'processArea:id,name'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('vendor', 'ilike', "%{$search}%")
                    ->orWhere('model', 'ilike', "%{$search}%")))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('asset_type', $type))
            ->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Assets/Form', $this->formData());
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = Asset::create($request->validated());

        return to_route('assets.show', $asset)->with('success', 'Asset added.');
    }

    public function show(Asset $asset): Response
    {
        return Inertia::render('Assets/Show', ['asset' => $asset->load([
            'site:id,name', 'processArea:id,name',
            'exposureScans' => fn ($query) => $query->latest('scanned_at')->limit(50),
            'baseline',
            'integrityChecks' => fn ($query) => $query->latest('checked_at')->limit(20),
            'integrityEvents' => fn ($query) => $query->where('event_type', '!=', 'exposure_change')->latest('detected_at')->limit(50),
            'advisoryMatches' => fn ($query) => $query->with('advisory')->latest(),
        ])]);
    }

    public function edit(Asset $asset): Response
    {
        return Inertia::render('Assets/Form', [...$this->formData(), 'asset' => $asset]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return to_route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(in_array($request->user()->organizations()
            ->whereKey($asset->organization_id)->first()?->pivot->role, ['admin', 'operator'], true), 403);
        $asset->delete();

        return to_route('assets.index')->with('success', 'Asset removed.');
    }

    private function formData(): array
    {
        return ['sites' => Site::with('processAreas:id,site_id,name')->orderBy('name')->get(['id', 'name'])];
    }
}
