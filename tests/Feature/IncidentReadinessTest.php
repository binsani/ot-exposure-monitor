<?php

use App\Models\Alert;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\IncidentPlaybook;
use App\Models\ManualFallbackAuthorization;
use App\Models\Organization;
use App\Models\PlaybookVersion;
use App\Models\Site;
use App\Models\User;
use App\Services\Incidents\StarterPlaybooks;

function readinessContext(string $role = 'admin'): array
{
    $organization = Organization::create(['name' => 'Readiness Water']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'West Plant']);
    $asset = Asset::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'site_id' => $site->id, 'name' => 'Pump PLC', 'asset_type' => 'plc']);

    return compact('organization', 'user', 'site', 'asset');
}

it('installs three useful starter playbooks for a new organization', function () {
    ['organization' => $organization] = readinessContext();
    app(StarterPlaybooks::class)->ensure($organization->id);
    expect(IncidentPlaybook::withoutGlobalScopes()->count())->toBe(3)
        ->and(PlaybookVersion::withoutGlobalScopes()->count())->toBe(3)
        ->and(PlaybookVersion::withoutGlobalScopes()->first()->steps)->not->toBeEmpty();
});

it('preserves old playbook content when publishing a new version', function () {
    ['user' => $user] = readinessContext();
    $payload = ['title' => 'Isolation', 'scope_type' => 'organization', 'steps' => [['title' => 'Disconnect', 'description' => 'Remove public access safely.', 'role' => 'admin']], 'authorized_roles' => ['admin']];
    $this->actingAs($user)->post(route('playbooks.store'), $payload)->assertRedirect();
    $playbook = IncidentPlaybook::sole();
    $payload['steps'][0]['description'] = 'Remove public access and verify containment.';
    $this->actingAs($user)->patch(route('playbooks.update', $playbook), $payload)->assertRedirect();

    expect($playbook->fresh()->current_version)->toBe(2)
        ->and($playbook->versions()->orderBy('version')->pluck('version')->all())->toBe([1, 2])
        ->and($playbook->versions()->first()->steps[0]['description'])->toBe('Remove public access safely.');
});

it('tracks authorization staleness and drill evidence', function () {
    ['user' => $user, 'site' => $site, 'asset' => $asset] = readinessContext();
    $this->actingAs($user)->post(route('readiness.authorizations.store'), ['site_id' => $site->id, 'asset_id' => $asset->id, 'user_id' => $user->id, 'granted_at' => now()->subYear()->toDateString()])->assertRedirect();
    $authorization = ManualFallbackAuthorization::sole();
    $this->actingAs($user)->post(route('readiness.drills.store', $authorization), ['drilled_at' => today()->toDateString(), 'duration_minutes' => 45, 'participants' => [$user->name], 'notes' => 'Successful manual pump run'])->assertRedirect();

    expect($authorization->fresh()->last_drilled_at->isToday())->toBeTrue()
        ->and($authorization->drills()->count())->toBe(1)
        ->and(AuditLog::where('action', 'fallback.drill_logged')->exists())->toBeTrue();
});

it('creates a complete incident record with frozen playbook and alert links', function () {
    ['organization' => $organization, 'user' => $user, 'site' => $site, 'asset' => $asset] = readinessContext();
    app(StarterPlaybooks::class)->ensure($organization->id);
    $version = PlaybookVersion::first();
    $alert = Alert::create(['organization_id' => $organization->id, 'source_type' => Asset::class, 'source_id' => $asset->id, 'title' => 'PLC exposed', 'message' => 'Publicly reachable', 'severity' => 'critical', 'status' => 'open']);
    $response = $this->actingAs($user)->post(route('incidents.store'), ['title' => 'Pump station response', 'site_id' => $site->id, 'asset_id' => $asset->id, 'playbook_version_id' => $version->id, 'started_at' => now()->toDateTimeString(), 'alert_ids' => [$alert->id]]);
    $incident = Incident::sole();
    $response->assertRedirect(route('incidents.show', $incident));
    $this->actingAs($user)->post(route('incidents.actions.store', $incident), ['occurred_at' => now()->toDateTimeString(), 'description' => 'Disabled public NAT rule'])->assertRedirect();
    $this->actingAs($user)->patch(route('incidents.resolve', $incident), ['resolution_notes' => 'Exposure removed and baseline verified.'])->assertRedirect();

    expect($incident->fresh()->status)->toBe('resolved')->and($incident->alerts()->sole()->id)->toBe($alert->id)
        ->and(IncidentAction::sole()->description)->toContain('NAT');
});

it('blocks viewers and cross-tenant readiness records', function () {
    ['user' => $viewer] = readinessContext('viewer');
    ['site' => $foreignSite] = readinessContext();
    $this->actingAs($viewer)->post(route('playbooks.store'), [])->assertForbidden();

    ['user' => $admin] = readinessContext();
    $this->actingAs($admin)->post(route('readiness.authorizations.store'), ['site_id' => $foreignSite->id, 'user_id' => $admin->id, 'granted_at' => today()->toDateString()])->assertNotFound();
});
