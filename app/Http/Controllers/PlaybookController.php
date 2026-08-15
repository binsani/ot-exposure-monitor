<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\IncidentPlaybook;
use App\Models\PlaybookVersion;
use App\Models\Site;
use App\Services\Incidents\StarterPlaybooks;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlaybookController extends Controller
{
    public function index(Request $request, StarterPlaybooks $starters): Response
    {
        $starters->ensure(app(TenantContext::class)->id());

        return Inertia::render('Playbooks/Index', [
            'playbooks' => IncidentPlaybook::with('current')->orderBy('title')->get(),
            'sites' => Site::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request): void {
            $playbook = IncidentPlaybook::create([...$data['playbook'], 'current_version' => 1]);
            $version = PlaybookVersion::create([...$data['version'], 'incident_playbook_id' => $playbook->id, 'version' => 1, 'created_by' => $request->user()->id, 'created_at' => now()]);
            $this->audit($request, 'playbook.created', $playbook, ['version_id' => $version->id]);
        });

        return back()->with('success', 'Playbook created.');
    }

    public function update(Request $request, IncidentPlaybook $playbook): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request, $playbook): void {
            $next = $playbook->current_version + 1;
            $playbook->update([...$data['playbook'], 'current_version' => $next, 'is_template' => false]);
            $version = PlaybookVersion::create([...$data['version'], 'incident_playbook_id' => $playbook->id, 'version' => $next, 'created_by' => $request->user()->id, 'created_at' => now()]);
            $this->audit($request, 'playbook.version_created', $playbook, ['version_id' => $version->id, 'version' => $next]);
        });

        return back()->with('success', 'A new playbook version was saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'scope_type' => ['required', 'in:organization,site,asset_type'],
            'site_id' => ['nullable', 'exists:sites,id'], 'asset_type' => ['nullable', 'string', 'max:100'],
            'steps' => ['required', 'array', 'min:1'], 'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['required', 'string', 'max:3000'], 'steps.*.role' => ['required', 'in:admin,operator,viewer,auditor'],
            'authorized_roles' => ['required', 'array', 'min:1'], 'authorized_roles.*' => ['in:admin,operator,viewer,auditor'],
        ]);
        abort_if($data['scope_type'] === 'site' && empty($data['site_id']), 422);
        abort_if($data['scope_type'] === 'asset_type' && empty($data['asset_type']), 422);
        if (! empty($data['site_id'])) {
            Site::findOrFail($data['site_id']);
        }

        return ['playbook' => collect($data)->only(['title', 'scope_type', 'site_id', 'asset_type'])->all(), 'version' => collect($data)->only(['steps', 'authorized_roles'])->all()];
    }

    private function authorizeEditor(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }

    private function audit(Request $request, string $action, IncidentPlaybook $subject, array $metadata): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => IncidentPlaybook::class, 'subject_id' => $subject->id, 'occurred_at' => now(), 'metadata' => $metadata]);
    }
}
