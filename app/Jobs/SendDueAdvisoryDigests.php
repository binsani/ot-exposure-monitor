<?php

namespace App\Jobs;

use App\Models\AssetAdvisoryMatch;
use App\Models\Organization;
use App\Notifications\WeeklyAdvisoryDigestNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendDueAdvisoryDigests implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Organization::where('advisory_digest_enabled', true)->where('advisory_digest_day', strtolower(now()->format('l')))
            ->each(function (Organization $organization): void {
                if ($organization->last_advisory_digest_at?->isToday()) {
                    return;
                }
                $since = $organization->last_advisory_digest_at ?? now()->subWeek();
                $matches = AssetAdvisoryMatch::withoutGlobalScopes()->where('organization_id', $organization->id)
                    ->where('created_at', '>', $since)->with(['asset:id,name', 'advisory:id,title,severity'])->get()
                    ->map(fn ($match) => ['asset' => $match->asset->name, 'advisory' => $match->advisory->title, 'severity' => $match->advisory->severity])->all();
                if ($matches !== []) {
                    Notification::send($organization->users()->wherePivotIn('role', ['admin', 'operator'])->get(), new WeeklyAdvisoryDigestNotification($matches));
                }
                $organization->update(['last_advisory_digest_at' => now()]);
            });
    }
}
