<?php

namespace App\Services\Integrity;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetBaseline;
use App\Models\AuditLog;
use App\Models\IntegrityCheck;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Notifications\IntegrityChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class IntegrityMonitor
{
    private const FIELDS = ['internal_ip', 'fingerprint', 'auth_success', 'firmware_version', 'config_checksum'];

    public function record(Asset $asset, array $snapshot, string $method, array $raw = [], ?int $acceptedBy = null): IntegrityCheck
    {
        $snapshot = collect($snapshot)->only(self::FIELDS)->all();

        [$check, $alerts] = DB::transaction(function () use ($asset, $snapshot, $method, $raw, $acceptedBy): array {
            $asset = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);
            $check = IntegrityCheck::withoutGlobalScopes()->create([
                'organization_id' => $asset->organization_id,
                'asset_id' => $asset->id,
                'checked_at' => now(),
                'detected_via' => $method,
                'snapshot' => $snapshot,
                'raw_result' => $raw,
            ]);
            $baseline = AssetBaseline::withoutGlobalScopes()->where('asset_id', $asset->id)->first();
            if (! $baseline) {
                AssetBaseline::withoutGlobalScopes()->create([
                    'organization_id' => $asset->organization_id,
                    'asset_id' => $asset->id,
                    'snapshot' => $snapshot,
                    'accepted_by' => $acceptedBy,
                    'accepted_at' => now(),
                ]);

                return [$check, collect()];
            }

            $alerts = collect();
            foreach ($snapshot as $field => $current) {
                $previous = $baseline->snapshot[$field] ?? null;
                if ($previous === null || $previous === $current) {
                    continue;
                }

                $openDuplicate = IntegrityEvent::withoutGlobalScopes()
                    ->where('asset_id', $asset->id)->where('acknowledged_at', null)->get()
                    ->contains(fn (IntegrityEvent $event) => ($event->metadata['field'] ?? null) === $field
                        && ($event->metadata['current'] ?? null) === $current);
                if ($openDuplicate) {
                    continue;
                }

                [$type, $severity, $title, $message] = $this->describe($asset, $field, $previous, $current);
                $event = IntegrityEvent::withoutGlobalScopes()->create([
                    'organization_id' => $asset->organization_id,
                    'asset_id' => $asset->id,
                    'event_type' => $type,
                    'detected_at' => now(),
                    'detected_via' => $method,
                    'severity' => $severity,
                    'notes' => $message,
                    'metadata' => compact('field', 'previous', 'current') + ['integrity_check_id' => $check->id],
                ]);
                $alerts->push(Alert::withoutGlobalScopes()->create([
                    'organization_id' => $asset->organization_id,
                    'source_type' => IntegrityEvent::class,
                    'source_id' => $event->id,
                    'title' => $title,
                    'message' => $message,
                    'severity' => $severity,
                    'status' => 'open',
                    'channels_sent' => ['email'],
                ]));
            }

            return [$check, $alerts];
        });

        if ($alerts->isNotEmpty()) {
            $recipients = Organization::find($asset->organization_id)?->users()->wherePivot('role', 'admin')->get() ?? collect();
            $alerts->each(fn (Alert $alert) => Notification::send($recipients, new IntegrityChangedNotification($alert)));
        }

        return $check;
    }

    public function accept(IntegrityEvent $event, int $actorId, string $note): void
    {
        DB::transaction(function () use ($event, $actorId, $note): void {
            $event = IntegrityEvent::withoutGlobalScopes()->lockForUpdate()->findOrFail($event->id);
            $field = $event->metadata['field'] ?? null;
            if (! in_array($field, self::FIELDS, true) || $event->acknowledged_at) {
                throw ValidationException::withMessages(['event' => 'This event cannot be accepted into the baseline.']);
            }

            $baseline = AssetBaseline::withoutGlobalScopes()->where('asset_id', $event->asset_id)->lockForUpdate()->firstOrFail();
            $snapshot = $baseline->snapshot;
            $previous = $snapshot[$field] ?? null;
            $snapshot[$field] = $event->metadata['current'];
            $baseline->update(['snapshot' => $snapshot, 'accepted_by' => $actorId, 'accepted_at' => now()]);
            $event->update(['acknowledged_by' => $actorId, 'acknowledged_at' => now(), 'notes' => $note]);
            Alert::withoutGlobalScopes()->where('source_type', IntegrityEvent::class)
                ->where('source_id', $event->id)->update(['status' => 'resolved']);
            AuditLog::withoutGlobalScopes()->create([
                'organization_id' => $event->organization_id,
                'actor_id' => $actorId,
                'action' => 'baseline.change_accepted',
                'subject_type' => AssetBaseline::class,
                'subject_id' => $baseline->id,
                'occurred_at' => now(),
                'metadata' => ['event_id' => $event->id, 'field' => $field, 'previous' => $previous, 'current' => $snapshot[$field], 'note' => $note],
            ]);
        });
    }

    private function describe(Asset $asset, string $field, mixed $previous, mixed $current): array
    {
        return match ($field) {
            'internal_ip' => ['ip_change', 'critical', 'Device network identity changed', "{$asset->name} internal IP changed from {$previous} to {$current}."],
            'fingerprint' => ['ip_change', 'critical', 'Device identity fingerprint changed', "{$asset->name} reported a different device fingerprint."],
            'auth_success' => ['credential_change', $asset->is_internet_facing ? 'critical' : 'high', 'Monitoring authentication anomaly', "Monitoring login for {$asset->name} no longer succeeds. This is an authentication symptom, not proof that credentials changed."],
            'firmware_version' => ['firmware_change', 'high', 'Device firmware changed', "{$asset->name} firmware changed from {$previous} to {$current}."],
            'config_checksum' => ['config_upload', 'high', 'Device configuration changed', "{$asset->name} reported a different configuration checksum."],
        };
    }
}
