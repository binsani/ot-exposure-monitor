<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'asset_id', 'event_type', 'detected_at', 'detected_via', 'severity', 'acknowledged_by', 'acknowledged_at', 'notes', 'metadata'])]
class IntegrityEvent extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'acknowledged_at' => 'datetime', 'metadata' => 'array'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
