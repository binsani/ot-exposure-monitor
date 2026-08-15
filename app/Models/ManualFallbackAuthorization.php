<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'site_id', 'user_id', 'asset_id', 'granted_at', 'last_drilled_at', 'notes'])]
class ManualFallbackAuthorization extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['granted_at' => 'date', 'last_drilled_at' => 'date'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function drills(): HasMany
    {
        return $this->hasMany(FallbackDrill::class);
    }
}
