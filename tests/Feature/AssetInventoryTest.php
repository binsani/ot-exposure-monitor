<?php

use App\Models\Asset;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function inventoryUser(string $role = 'admin'): array
{
    $organization = Organization::create(['name' => 'Riverbend Water']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'North Plant']);

    return compact('organization', 'user', 'site');
}

it('allows an operator to create and update an asset in their organization', function () {
    ['organization' => $organization, 'user' => $user, 'site' => $site] = inventoryUser('operator');

    $this->actingAs($user)->post(route('assets.store'), [
        'site_id' => $site->id,
        'name' => 'Intake PLC 01',
        'asset_type' => 'plc',
        'vendor' => 'Rockwell Automation',
        'model' => 'MicroLogix 1100',
        'internal_ip' => '10.0.0.12',
        'data_source_type' => 'manual',
        'criticality' => 'high',
    ])->assertRedirect();

    $asset = Asset::withoutGlobalScopes()->firstOrFail();
    expect($asset->organization_id)->toBe($organization->id);

    $this->actingAs($user)->put(route('assets.update', $asset), [
        'site_id' => $site->id,
        'name' => 'Intake PLC 01',
        'asset_type' => 'plc',
        'vendor' => 'Rockwell Automation',
        'model' => 'MicroLogix 1100',
        'firmware_version' => '21.007',
        'data_source_type' => 'manual',
        'criticality' => 'critical',
    ])->assertRedirect();

    expect($asset->fresh()->criticality)->toBe('critical');
});

it('prevents a viewer from changing inventory', function () {
    ['user' => $user, 'site' => $site] = inventoryUser('viewer');
    $this->actingAs($user)->post(route('assets.store'), [
        'site_id' => $site->id, 'name' => 'PLC', 'asset_type' => 'plc',
        'data_source_type' => 'manual', 'criticality' => 'medium',
    ])->assertForbidden();
});

it('returns a not found response for another organizations asset', function () {
    ['user' => $user] = inventoryUser();
    ['organization' => $other, 'site' => $otherSite] = inventoryUser();
    $asset = Asset::withoutGlobalScopes()->create([
        'organization_id' => $other->id, 'site_id' => $otherSite->id,
        'name' => 'Foreign PLC', 'asset_type' => 'plc',
    ]);
    $this->actingAs($user)->get(route('assets.show', $asset))->assertNotFound();
});

it('imports a mapped csv without assuming fixed column names', function () {
    Storage::fake('local');
    ['organization' => $organization, 'user' => $user] = inventoryUser();
    $file = UploadedFile::fake()->createWithContent('inventory.csv', "Device,Kind,Facility,Maker,Address\nIntake PLC,plc,North Plant,Rockwell Automation,10.1.2.3\n");

    $preview = $this->actingAs($user)->post(route('assets.import.preview'), ['file' => $file], ['X-Inertia' => 'true']);
    $preview->assertOk();
    $token = $preview->json('props.token');

    $this->actingAs($user)->post(route('assets.import.store'), [
        'token' => $token,
        'mapping' => ['name' => 'Device', 'asset_type' => 'Kind', 'site' => 'Facility', 'vendor' => 'Maker', 'internal_ip' => 'Address'],
    ])->assertRedirect(route('assets.index'));

    $this->assertDatabaseHas('assets', [
        'organization_id' => $organization->id,
        'name' => 'Intake PLC',
        'internal_ip' => '10.1.2.3',
    ]);
});

it('lets operators organize inventory into sites and process areas', function () {
    ['organization' => $organization, 'user' => $user] = inventoryUser('operator');

    $this->actingAs($user)->post(route('sites.store'), [
        'name' => 'South Plant',
        'address' => 'Reservoir Road',
        'criticality_tier' => 'critical',
    ])->assertRedirect();

    $site = Site::withoutGlobalScopes()->where('name', 'South Plant')->firstOrFail();
    $this->actingAs($user)->post(route('process-areas.store', $site), [
        'name' => 'Filtration',
    ])->assertRedirect();

    $this->assertDatabaseHas('process_areas', [
        'organization_id' => $organization->id,
        'site_id' => $site->id,
        'name' => 'Filtration',
    ]);
});
