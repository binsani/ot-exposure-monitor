<?php

use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetBaseline;
use App\Models\AuditLog;
use App\Models\IntegrityCheck;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\IntegrityChangedNotification;
use App\Services\Integrity\IntegrityMonitor;
use Illuminate\Support\Facades\Notification;

function integrityAsset(string $role = 'admin', bool $exposed = false): array
{
    $organization = Organization::create(['name' => 'Integrity Test Utility']);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'Pump Station']);
    $asset = Asset::withoutGlobalScopes()->create([
        'organization_id' => $organization->id, 'site_id' => $site->id,
        'name' => 'Control PLC', 'asset_type' => 'plc', 'is_internet_facing' => $exposed,
    ]);

    return compact('organization', 'user', 'asset');
}

it('establishes a baseline and ignores unchanged readings', function () {
    Notification::fake();
    ['user' => $user, 'asset' => $asset] = integrityAsset();
    $monitor = app(IntegrityMonitor::class);
    $snapshot = ['internal_ip' => '10.0.0.4', 'auth_success' => true, 'firmware_version' => '1.0'];

    $monitor->record($asset, $snapshot, 'agent', [], $user->id);
    $monitor->record($asset, $snapshot, 'agent');

    expect(AssetBaseline::withoutGlobalScopes()->count())->toBe(1)
        ->and(IntegrityCheck::withoutGlobalScopes()->count())->toBe(2)
        ->and(IntegrityEvent::withoutGlobalScopes()->count())->toBe(0)
        ->and(Alert::withoutGlobalScopes()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('raises one critical alert for repeated identical network drift', function () {
    Notification::fake();
    ['user' => $user, 'asset' => $asset] = integrityAsset();
    $monitor = app(IntegrityMonitor::class);
    $monitor->record($asset, ['internal_ip' => '10.0.0.4'], 'agent');
    $monitor->record($asset, ['internal_ip' => '10.0.0.5'], 'agent');
    $monitor->record($asset, ['internal_ip' => '10.0.0.5'], 'agent');

    $event = IntegrityEvent::withoutGlobalScopes()->sole();
    expect($event->event_type)->toBe('ip_change')->and($event->severity)->toBe('critical')
        ->and(Alert::withoutGlobalScopes()->count())->toBe(1);
    Notification::assertSentToTimes($user, IntegrityChangedNotification::class, 1);
});

it('describes failed authentication as a symptom and escalates exposed devices', function () {
    ['asset' => $asset] = integrityAsset(exposed: true);
    $monitor = app(IntegrityMonitor::class);
    $monitor->record($asset, ['auth_success' => true], 'agent');
    $monitor->record($asset, ['auth_success' => false], 'agent');

    $event = IntegrityEvent::withoutGlobalScopes()->sole();
    expect($event->event_type)->toBe('credential_change')->and($event->severity)->toBe('critical')
        ->and($event->notes)->toContain('not proof that credentials changed');
});

it('raises high alerts for firmware and configuration changes only', function () {
    ['asset' => $asset] = integrityAsset();
    $monitor = app(IntegrityMonitor::class);
    $monitor->record($asset, ['firmware_version' => '1.0', 'config_checksum' => 'abc'], 'agent');
    $monitor->record($asset, ['firmware_version' => '1.1', 'config_checksum' => 'def'], 'agent');

    expect(IntegrityEvent::withoutGlobalScopes()->pluck('event_type')->all())->toBe(['firmware_change', 'config_upload'])
        ->and(IntegrityEvent::withoutGlobalScopes()->pluck('severity')->unique()->all())->toBe(['high']);
});

it('accepts a legitimate change into the baseline with an audit trail', function () {
    ['user' => $user, 'asset' => $asset] = integrityAsset();
    $monitor = app(IntegrityMonitor::class);
    $monitor->record($asset, ['firmware_version' => '1.0'], 'manual', [], $user->id);
    $monitor->record($asset, ['firmware_version' => '1.1'], 'agent');
    $event = IntegrityEvent::withoutGlobalScopes()->sole();

    $this->actingAs($user)->patch(route('integrity-events.accept', $event), ['note' => 'Approved maintenance window'])->assertRedirect();

    expect(AssetBaseline::withoutGlobalScopes()->sole()->snapshot['firmware_version'])->toBe('1.1')
        ->and($event->fresh()->acknowledged_by)->toBe($user->id)
        ->and(Alert::withoutGlobalScopes()->sole()->status)->toBe('resolved')
        ->and(AuditLog::withoutGlobalScopes()->sole()->action)->toBe('baseline.change_accepted');
});

it('prevents viewers from reporting or accepting integrity changes', function () {
    ['user' => $user, 'asset' => $asset] = integrityAsset('viewer');
    $this->actingAs($user)->post(route('assets.integrity.manual', $asset), ['internal_ip' => '10.0.0.4'])->assertForbidden();
});

it('keeps integrity event actions inside the active tenant', function () {
    ['user' => $user] = integrityAsset();
    ['asset' => $foreignAsset] = integrityAsset();
    app(IntegrityMonitor::class)->record($foreignAsset, ['internal_ip' => '10.0.0.4'], 'agent');
    app(IntegrityMonitor::class)->record($foreignAsset, ['internal_ip' => '10.0.0.5'], 'agent');
    $foreignEvent = IntegrityEvent::withoutGlobalScopes()->sole();

    $this->actingAs($user)->patch(route('integrity-events.accept', $foreignEvent), ['note' => 'Should fail'])->assertNotFound();
});
