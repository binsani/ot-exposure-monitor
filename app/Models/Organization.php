<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sector', 'plan_tier', 'scan_cadence_hours', 'fallback_drill_stale_months', 'advisory_digest_enabled', 'advisory_digest_day', 'last_advisory_digest_at'])]
class Organization extends Model
{
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    protected function casts(): array
    {
        return ['advisory_digest_enabled' => 'boolean', 'last_advisory_digest_at' => 'datetime'];
    }
}
