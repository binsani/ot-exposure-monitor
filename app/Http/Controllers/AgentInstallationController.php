<?php

namespace App\Http\Controllers;

use App\Models\AgentInstallation;
use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentInstallationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Agents/Index', ['agents' => AgentInstallation::latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $plainToken = 'oteim_'.Str::random(64);
        $agent = AgentInstallation::create([...$data, 'token_hash' => hash('sha256', $plainToken), 'created_by' => $request->user()->id]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'agent.enrolled', 'subject_type' => AgentInstallation::class, 'subject_id' => $agent->id, 'occurred_at' => now(), 'metadata' => ['name' => $agent->name]]);

        return back()->with('agent_token', $plainToken)->with('success', 'Agent enrolled. Copy its token now.');
    }

    public function destroy(Request $request, AgentInstallation $agent): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $agent->update(['is_active' => false]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'agent.revoked', 'subject_type' => AgentInstallation::class, 'subject_id' => $agent->id, 'occurred_at' => now()]);

        return back()->with('success', 'Agent access revoked.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless($role === 'admin', 403);
    }
}
