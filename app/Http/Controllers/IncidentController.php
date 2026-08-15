<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\IncidentPlaybook;
use App\Models\PlaybookVersion;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Incidents/Index', [
            'incidents' => Incident::with(['site:id,name', 'asset:id,name'])->withCount(['alerts', 'actions'])->latest('started_at')->get(),
            'sites' => Site::with('assets:id,site_id,name')->orderBy('name')->get(['id', 'name']),
            'playbooks' => IncidentPlaybook::with('current')->orderBy('title')->get(),
            'alerts' => Alert::whereIn('status', ['open', 'acknowledged'])->latest()->get(['id', 'title', 'severity']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'site_id' => ['nullable', 'exists:sites,id'], 'asset_id' => ['nullable', 'exists:assets,id'], 'playbook_version_id' => ['nullable', 'exists:playbook_versions,id'], 'started_at' => ['required', 'date'], 'alert_ids' => ['nullable', 'array'], 'alert_ids.*' => ['integer'], 'improvised_notes' => ['nullable', 'required_without:playbook_version_id', 'string', 'max:5000']]);
        $incident = DB::transaction(function () use ($request, $data): Incident {
            $alertIds = Alert::whereIn('id', $data['alert_ids'] ?? [])->pluck('id');
            abort_if($alertIds->count() !== count($data['alert_ids'] ?? []), 422);
            if (! empty($data['site_id'])) {
                Site::findOrFail($data['site_id']);
            }
            if (! empty($data['asset_id'])) {
                abort_if(Asset::findOrFail($data['asset_id'])->site_id !== (int) $data['site_id'], 422);
            }
            if (! empty($data['playbook_version_id'])) {
                PlaybookVersion::findOrFail($data['playbook_version_id']);
            }
            $incident = Incident::create(array_merge(collect($data)->except('alert_ids')->all(), ['status' => 'open', 'created_by' => $request->user()->id]));
            $incident->alerts()->sync($alertIds);
            $this->audit($request, 'incident.opened', $incident->id, ['alert_ids' => $alertIds]);

            return $incident;
        });

        return to_route('incidents.show', $incident)->with('success', 'Incident opened.');
    }

    public function show(Incident $incident): Response
    {
        return Inertia::render('Incidents/Show', ['incident' => $incident->load(['site:id,name', 'asset:id,name', 'alerts', 'playbookVersion', 'actions' => fn ($q) => $q->latest('occurred_at')])]);
    }

    public function action(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeEditor($request);
        abort_if($incident->status === 'resolved', 422);
        $data = $request->validate(['occurred_at' => ['required', 'date'], 'description' => ['required', 'string', 'max:5000']]);
        $action = IncidentAction::create(array_merge($data, ['incident_id' => $incident->id, 'actor_id' => $request->user()->id]));
        $this->audit($request, 'incident.action_logged', $incident->id, ['action_id' => $action->id]);

        return back()->with('success', 'Incident action added.');
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:10000'],
        ]);
        $incident->update(array_merge($data, [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]));
        $this->audit($request, 'incident.resolved', $incident->id, $data);

        return back()->with('success', 'Incident resolved.');
    }

    public function print(Incident $incident): Response
    {
        return Inertia::render('Incidents/Print', ['incident' => $incident->load(['site:id,name', 'asset:id,name', 'alerts', 'playbookVersion', 'actions' => fn ($q) => $q->oldest('occurred_at')])]);
    }

    private function authorizeEditor(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }

    private function audit(Request $request, string $action, int $id, array $metadata): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => Incident::class, 'subject_id' => $id, 'occurred_at' => now(), 'metadata' => $metadata]);
    }
}
