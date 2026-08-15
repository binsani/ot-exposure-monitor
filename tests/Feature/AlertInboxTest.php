<?php

use App\Jobs\SendDueAdvisoryDigests;
use App\Models\Advisory;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetAdvisoryMatch;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\WeeklyAdvisoryDigestNotification;
use Illuminate\Support\Facades\Notification;

function alertContext(string $role = 'admin'): array
{
    $organization = Organization::create(['name' => 'Alert Test Utility', 'advisory_digest_day' => strtolower(now()->format('l'))]);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->users()->attach($user, ['role' => $role]);
    $site = Site::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'East Plant']);
    $asset = Asset::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'site_id' => $site->id, 'name' => 'Filter PLC', 'asset_type' => 'plc']);

    return compact('organization', 'user', 'asset');
}

it('acknowledges and resolves alerts with timestamped audit evidence', function () {
    ['organization' => $organization, 'user' => $user, 'asset' => $asset] = alertContext();
    $alert = Alert::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'source_type' => Asset::class, 'source_id' => $asset->id, 'title' => 'Review device', 'message' => 'A change needs review.', 'severity' => 'high', 'status' => 'open']);

    $this->actingAs($user)->patch(route('alerts.acknowledge', $alert))->assertRedirect();
    expect($alert->fresh()->status)->toBe('acknowledged')->and($alert->fresh()->acknowledged_by)->toBe($user->id)
        ->and(AuditLog::where('action', 'alert.acknowledged')->count())->toBe(1);

    $this->actingAs($user)->patch(route('alerts.resolve', $alert), ['note' => 'Verified approved maintenance'])->assertRedirect();
    expect($alert->fresh()->status)->toBe('resolved')->and(AuditLog::where('action', 'alert.resolved')->count())->toBe(1);
});

it('prevents viewers and other tenants from changing alerts', function () {
    ['organization' => $viewerOrganization, 'user' => $viewer, 'asset' => $viewerAsset] = alertContext('viewer');
    $own = Alert::withoutGlobalScopes()->create(['organization_id' => $viewerOrganization->id, 'source_type' => Asset::class, 'source_id' => $viewerAsset->id, 'title' => 'Own alert', 'message' => 'Needs operator', 'severity' => 'high', 'status' => 'open']);
    ['organization' => $otherOrganization, 'asset' => $otherAsset] = alertContext();
    $foreign = Alert::withoutGlobalScopes()->create(['organization_id' => $otherOrganization->id, 'source_type' => Asset::class, 'source_id' => $otherAsset->id, 'title' => 'Foreign alert', 'message' => 'Not visible', 'severity' => 'high', 'status' => 'open']);

    $this->actingAs($viewer)->patch(route('alerts.acknowledge', $foreign))->assertNotFound();
    $this->actingAs($viewer)->patch(route('alerts.acknowledge', $own))->assertForbidden();
});

it('sends one weekly digest containing only new relevant matches', function () {
    Notification::fake();
    ['organization' => $organization, 'user' => $user, 'asset' => $asset] = alertContext();
    $advisory = Advisory::create(['source' => 'manual', 'external_id' => 'TEST-1', 'title' => 'Relevant PLC issue', 'severity' => 'high', 'affected_vendors_models' => []]);
    AssetAdvisoryMatch::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'asset_id' => $asset->id, 'advisory_id' => $advisory->id, 'matched_on' => 'manual', 'status' => 'open']);

    app(SendDueAdvisoryDigests::class)->handle();
    Notification::assertSentTo($user, WeeklyAdvisoryDigestNotification::class, fn ($notification) => count($notification->matches) === 1);
    app(SendDueAdvisoryDigests::class)->handle();
    Notification::assertSentToTimes($user, WeeklyAdvisoryDigestNotification::class, 1);
});

it('does not send an empty weekly digest', function () {
    Notification::fake();
    alertContext();
    app(SendDueAdvisoryDigests::class)->handle();
    Notification::assertNothingSent();
});
