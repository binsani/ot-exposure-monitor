<?php

use App\Models\AgentInstallation;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetBaseline;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;

function agentContext(string $role = 'admin'): array
{
    $organization = Organization::create(['name' => 'Agent Test Utility']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'Agent Site']);
    $asset = Asset::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'site_id' => $site->id, 'name' => 'Agent PLC', 'asset_type' => 'plc', 'data_source_type' => 'agent']);

    return compact('organization', 'user', 'asset');
}

it('shows an enrollment token once and stores only its hash', function () {
    ['user' => $user] = agentContext();
    $response = $this->actingAs($user)->post(route('agents.store'), ['name' => 'North collector']);
    $response->assertRedirect()->assertSessionHas('agent_token');
    $plain = session('agent_token');
    $agent = AgentInstallation::sole();
    expect($plain)->toStartWith('oteim_')->and($agent->token_hash)->toBe(hash('sha256', $plain))->and($agent->toArray())->not->toHaveKey('token_hash');
});

it('accepts tenant-bound agent readings and detects drift', function () {
    ['organization' => $organization, 'asset' => $asset] = agentContext();
    $token = 'oteim_'.str_repeat('a', 64);
    AgentInstallation::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'Collector', 'token_hash' => hash('sha256', $token)]);
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->withHeaders($headers)->getJson('/api/agent/v1/assets')->assertOk()->assertJsonPath('assets.0.id', $asset->id);
    $this->withHeaders($headers)->postJson('/api/agent/v1/reports', ['agent_version' => '1.0.0', 'readings' => [['asset_id' => $asset->id, 'snapshot' => ['internal_ip' => '10.10.0.4', 'firmware_version' => '1.0']]]])->assertOk()->assertJson(['processed' => 1]);
    expect(AssetBaseline::withoutGlobalScopes()->count())->toBe(1)->and(Alert::withoutGlobalScopes()->count())->toBe(0);

    $this->withHeaders($headers)->postJson('/api/agent/v1/reports', ['readings' => [['asset_id' => $asset->id, 'snapshot' => ['internal_ip' => '10.10.0.5', 'firmware_version' => '1.0']]]])->assertOk();
    expect(IntegrityEvent::withoutGlobalScopes()->sole()->event_type)->toBe('ip_change')->and(Alert::withoutGlobalScopes()->count())->toBe(1);
});

it('rejects revoked tokens and cross-tenant asset reports', function () {
    ['organization' => $organization] = agentContext();
    ['asset' => $foreignAsset] = agentContext();
    $token = 'oteim_'.str_repeat('b', 64);
    $agent = AgentInstallation::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'Collector', 'token_hash' => hash('sha256', $token)]);

    $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/agent/v1/reports', ['readings' => [['asset_id' => $foreignAsset->id, 'snapshot' => ['internal_ip' => '10.0.0.1']]]])->assertNotFound();
    $agent->update(['is_active' => false]);
    $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/agent/v1/assets')->assertNotFound();
});

it('allows only administrators to enroll or revoke agents', function () {
    ['user' => $operator] = agentContext('operator');
    $this->actingAs($operator)->post(route('agents.store'), ['name' => 'Denied'])->assertForbidden();
});

it('adds security headers to web and agent responses', function () {
    $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    $this->getJson('/api/agent/v1/assets')->assertHeader('X-Content-Type-Options', 'nosniff');
});
