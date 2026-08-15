<?php

use App\Models\Advisory;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetAdvisoryMatch;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\Advisories\AdvisoryMatcher;
use App\Services\Advisories\CisaCsafIngestor;

function advisoryInventory(): array
{
    $organization = Organization::create(['name' => 'Riverbend Water']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => 'admin']);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'North Plant']);
    $microLogix = Asset::withoutGlobalScopes()->create([
        'organization_id' => $organization->id, 'site_id' => $site->id, 'name' => 'Intake PLC',
        'asset_type' => 'plc', 'vendor' => 'Allen-Bradley', 'model' => 'MicroLogix 1100',
        'firmware_version' => '21.007', 'is_internet_facing' => true,
    ]);
    $siemens = Asset::withoutGlobalScopes()->create([
        'organization_id' => $organization->id, 'site_id' => $site->id, 'name' => 'Filter PLC',
        'asset_type' => 'plc', 'vendor' => 'Siemens', 'model' => 'S7-1200',
    ]);

    return compact('organization', 'user', 'site', 'microLogix', 'siemens');
}

it('matches MicroLogix while excluding unrelated Siemens assets', function () {
    ['microLogix' => $microLogix, 'siemens' => $siemens] = advisoryInventory();
    $advisory = Advisory::create([
        'source' => 'cisa_ics', 'external_id' => 'ICSA-26-999-01',
        'title' => 'Rockwell Automation MicroLogix 1100 and 1400', 'severity' => 'critical',
        'affected_vendors_models' => [
            ['vendor' => 'Rockwell Automation', 'model' => 'MicroLogix 1100', 'versions' => []],
            ['vendor' => 'Rockwell Automation', 'model' => 'MicroLogix 1400', 'versions' => []],
        ],
    ]);

    expect(app(AdvisoryMatcher::class)->matchAdvisory($advisory))->toBe(1);
    $this->assertDatabaseHas('asset_advisory_matches', ['asset_id' => $microLogix->id, 'advisory_id' => $advisory->id]);
    $this->assertDatabaseMissing('asset_advisory_matches', ['asset_id' => $siemens->id, 'advisory_id' => $advisory->id]);
    expect(Alert::withoutGlobalScopes()->first()->severity)->toBe('high');
});

it('honors an advisory exact firmware list when firmware is known', function () {
    ['microLogix' => $microLogix] = advisoryInventory();
    $advisory = Advisory::create([
        'source' => 'manual', 'external_id' => 'TEST-FIRMWARE', 'title' => 'Firmware-specific notice', 'severity' => 'high',
        'affected_vendors_models' => [['vendor' => 'Rockwell Automation', 'model' => 'MicroLogix 1100', 'versions' => ['22.001']]],
    ]);
    expect(app(AdvisoryMatcher::class)->matches($microLogix, $advisory))->toBeNull();
});

it('parses affected products from a CISA CSAF document and stores the raw source', function () {
    advisoryInventory();
    $payload = [
        'document' => [
            'title' => 'Rockwell Automation MicroLogix',
            'tracking' => ['id' => 'ICSA-26-999-02', 'initial_release_date' => '2026-08-01T00:00:00Z', 'current_release_date' => '2026-08-02T00:00:00Z'],
            'notes' => [['category' => 'summary', 'text' => 'An authentication weakness affects these controllers.']],
        ],
        'product_tree' => ['branches' => [[
            'category' => 'vendor', 'name' => 'Rockwell Automation', 'branches' => [[
                'category' => 'product_name', 'name' => 'MicroLogix 1100', 'product' => ['product_id' => 'CSAFPID-1'],
            ]],
        ]]],
        'vulnerabilities' => [[
            'cve' => 'CVE-2026-10001', 'product_status' => ['known_affected' => ['CSAFPID-1']],
            'scores' => [['cvss_v3' => ['baseScore' => 9.8]]],
        ]],
    ];

    $advisory = app(CisaCsafIngestor::class)->ingestPayload($payload);
    expect($advisory->severity)->toBe('critical')
        ->and($advisory->cve_ids)->toBe(['CVE-2026-10001'])
        ->and($advisory->affected_vendors_models[0]['model'])->toBe('MicroLogix 1100')
        ->and($advisory->raw_payload['document']['tracking']['id'])->toBe('ICSA-26-999-02');
});

it('requires justification for compensating controls and false positives', function () {
    ['user' => $user, 'microLogix' => $asset] = advisoryInventory();
    $advisory = Advisory::create([
        'source' => 'manual', 'external_id' => 'TEST-STATUS', 'title' => 'Test', 'severity' => 'medium',
        'affected_vendors_models' => [['vendor' => 'Rockwell Automation', 'model' => 'MicroLogix 1100', 'versions' => []]],
    ]);
    app(AdvisoryMatcher::class)->matchAdvisory($advisory);
    $match = AssetAdvisoryMatch::withoutGlobalScopes()->where('asset_id', $asset->id)->firstOrFail();

    $this->actingAs($user)->patch(route('advisory-matches.update', $match), [
        'status' => 'compensating_control', 'notes' => '',
    ])->assertSessionHasErrors('notes');
    $this->actingAs($user)->patch(route('advisory-matches.update', $match), [
        'status' => 'compensating_control', 'notes' => 'PLC isolated behind managed firewall.',
    ])->assertRedirect();
});
