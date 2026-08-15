<?php

namespace App\Http\Controllers;

use App\Models\Advisory;
use App\Models\AssetAdvisoryMatch;
use App\Services\Advisories\AdvisoryMatcher;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvisoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Advisories/Index', [
            'advisories' => Advisory::withCount('matches')->latest('published_at')->paginate(25),
            'openBySeverity' => AssetAdvisoryMatch::where('status', 'open')->join('advisories', 'advisories.id', '=', 'asset_advisory_matches.advisory_id')
                ->selectRaw('advisories.severity, count(*) as total')->groupBy('advisories.severity')->pluck('total', 'severity'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Advisories/Create');
    }

    public function store(Request $request, AdvisoryMatcher $matcher): RedirectResponse
    {
        $this->authorizeChange($request);
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:255', 'unique:advisories,external_id'],
            'title' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'severity' => ['required', 'in:unknown,low,medium,high,critical'],
            'vendor' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'versions' => ['nullable', 'string', 'max:2000'],
            'cve_ids' => ['nullable', 'string', 'max:2000'],
            'published_at' => ['nullable', 'date'],
        ]);
        $advisory = Advisory::create([
            ...collect($data)->except(['vendor', 'model', 'versions'])->all(),
            'source' => 'manual',
            'cve_ids' => array_values(array_filter(array_map('trim', explode(',', $data['cve_ids'] ?? '')))),
            'affected_vendors_models' => [[
                'vendor' => $data['vendor'], 'model' => $data['model'],
                'versions' => array_values(array_filter(array_map('trim', explode(',', $data['versions'] ?? '')))),
            ]],
        ]);
        $matcher->matchAdvisory($advisory);

        return to_route('advisories.show', $advisory)->with('success', 'Advisory added and inventory checked.');
    }

    public function show(Advisory $advisory): Response
    {
        return Inertia::render('Advisories/Show', [
            'advisory' => $advisory,
            'matches' => $advisory->matches()->with('asset.site:id,name')->orderBy('status')->get(),
        ]);
    }

    public function updateMatch(Request $request, AssetAdvisoryMatch $match): RedirectResponse
    {
        $this->authorizeChange($request);
        $data = $request->validate([
            'status' => ['required', 'in:open,acknowledged,patched,compensating_control,false_positive'],
            'notes' => ['nullable', 'string', 'max:5000', 'required_if:status,compensating_control,false_positive'],
        ]);
        $match->update($data);

        return back()->with('success', 'Advisory status updated.');
    }

    private function authorizeChange(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }
}
