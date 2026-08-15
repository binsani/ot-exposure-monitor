<?php

namespace App\Services\Exposure;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\ExposureScan;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Notifications\ExposureChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExposureRecorder
{
    public function record(Asset $asset, string $result, string $method, array $raw = [], ?string $note = null, $recheckAt = null): ExposureScan
    {
        [$scan, $alert] = DB::transaction(function () use ($asset, $result, $method, $raw, $note, $recheckAt): array {
            $asset = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);
            $previous = $asset->exposureScans()->latest('scanned_at')->latest('id')->first()?->result;
            $scan = ExposureScan::withoutGlobalScopes()->create([
                'organization_id' => $asset->organization_id, 'asset_id' => $asset->id,
                'scanned_at' => now(), 'method' => $method, 'result' => $result,
                'raw_result' => $raw, 'note' => $note, 'recheck_at' => $recheckAt,
            ]);
            if ($result !== 'unknown') {
                $asset->update(['is_internet_facing' => $result === 'exposed', 'last_seen_at' => now()]);
            }

            $changed = ($previous !== null && $previous !== $result && $result !== 'unknown')
                || ($previous === null && $result === 'exposed');
            $alert = null;
            if ($changed) {
                $event = IntegrityEvent::withoutGlobalScopes()->create([
                    'organization_id' => $asset->organization_id, 'asset_id' => $asset->id,
                    'event_type' => 'exposure_change', 'detected_at' => now(),
                    'detected_via' => $method, 'severity' => 'critical',
                    'notes' => 'Exposure changed from '.($previous ?? 'not_exposed')." to {$result}.",
                    'metadata' => ['previous' => $previous, 'current' => $result, 'exposure_scan_id' => $scan->id],
                ]);
                $alert = Alert::withoutGlobalScopes()->create([
                    'organization_id' => $asset->organization_id,
                    'source_type' => IntegrityEvent::class, 'source_id' => $event->id,
                    'title' => $result === 'exposed' ? 'Device is reachable from the internet' : 'Device is no longer internet-reachable',
                    'message' => "{$asset->name} changed from ".($previous ?? 'not exposed').' to '.str_replace('_', ' ', $result).'.',
                    'severity' => 'critical', 'status' => 'open', 'channels_sent' => ['email'],
                ]);
            }

            return [$scan, $alert];
        });

        if ($alert) {
            $recipients = Organization::find($alert->organization_id)?->users()->wherePivot('role', 'admin')->get() ?? collect();
            Notification::send($recipients, new ExposureChangedNotification($alert));
        }

        return $scan;
    }
}
