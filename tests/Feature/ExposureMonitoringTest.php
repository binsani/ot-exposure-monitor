<?php

use App\Models\Alert;
use App\Models\Asset;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\ExposureChangedNotification;
use App\Services\Exposure\ExposureRecorder;
use App\Services\Exposure\ShodanExposureDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function exposureAsset(string $role = 'admin'): array
{
    $organization = Organization::create(['name' => 'Riverbend Water']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'North Plant']);
    $asset = Asset::withoutGlobalScopes()->create([
        'organization_id' => $organization->id, 'site_id' => $site->id,
        'name' => 'Intake PLC', 'asset_type' => 'plc',
        'external_ip' => '203.0.113.10', 'data_source_type' => 'cloud_scan',
    ]);

    return compact('organization', 'user', 'site', 'asset');
}

it('alerts on exposure transitions but not unchanged checks', function () {
    Notification::fake();
    ['user' => $user, 'asset' => $asset] = exposureAsset();
    $recorder = app(ExposureRecorder::class);

    $recorder->record($asset, 'not_exposed', 'manual', [], 'Firewall reviewed');
    $recorder->record($asset, 'not_exposed', 'shodan');
    expect(IntegrityEvent::withoutGlobalScopes()->count())->toBe(0)
        ->and(Alert::withoutGlobalScopes()->count())->toBe(0);
    Notification::assertNothingSent();

    $recorder->record($asset, 'exposed', 'shodan', ['matched_ports' => [502]]);
    expect(IntegrityEvent::withoutGlobalScopes()->count())->toBe(1)
        ->and(Alert::withoutGlobalScopes()->count())->toBe(1)
        ->and($asset->fresh()->is_internet_facing)->toBeTrue();
    Notification::assertSentTo($user, ExposureChangedNotification::class);

    $recorder->record($asset, 'exposed', 'manual', [], 'Confirmed again');
    expect(Alert::withoutGlobalScopes()->count())->toBe(1);
    Notification::assertSentToTimes($user, ExposureChangedNotification::class, 1);

    $recorder->record($asset, 'not_exposed', 'shodan');
    expect(Alert::withoutGlobalScopes()->count())->toBe(2)
        ->and($asset->fresh()->is_internet_facing)->toBeFalse()
        ->and($asset->exposureScans()->count())->toBe(5);
});

it('treats a first exposed result as an urgent discovery', function () {
    ['asset' => $asset] = exposureAsset();
    app(ExposureRecorder::class)->record($asset, 'exposed', 'manual', [], 'Public NAT rule verified');

    $alert = Alert::withoutGlobalScopes()->firstOrFail();
    expect($alert->severity)->toBe('critical')
        ->and($alert->title)->toBe('Device is reachable from the internet');
});

it('recognizes known industrial services in shodan results', function () {
    ['asset' => $asset] = exposureAsset();
    config(['services.shodan.key' => 'test-key']);
    Http::fake(['api.shodan.io/*' => Http::response([
        'ports' => [80, 502], 'last_update' => '2026-08-15T00:00:00',
        'data' => [['port' => 502, 'data' => 'Modbus TCP']],
    ])]);

    $result = app(ShodanExposureDriver::class)->check($asset);
    expect($result->result)->toBe('exposed')
        ->and($result->raw['matched_ports'])->toBe([502]);
});

it('does not label an unrelated public web service as an industrial exposure', function () {
    ['asset' => $asset] = exposureAsset();
    config(['services.shodan.key' => 'test-key']);
    Http::fake(['api.shodan.io/*' => Http::response([
        'ports' => [80, 443], 'data' => [['port' => 443, 'data' => 'generic web server']],
    ])]);

    expect(app(ShodanExposureDriver::class)->check($asset)->result)->toBe('not_exposed');
});

it('prevents viewers from submitting manual attestations', function () {
    ['user' => $user, 'asset' => $asset] = exposureAsset('viewer');
    $this->actingAs($user)->post(route('assets.exposure.manual', $asset), [
        'result' => 'not_exposed', 'note' => 'Checked firewall',
    ])->assertForbidden();
});
