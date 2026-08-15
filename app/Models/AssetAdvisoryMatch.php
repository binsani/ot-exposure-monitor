<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'asset_id', 'advisory_id', 'matched_on', 'status', 'notes'])]
class AssetAdvisoryMatch extends Model
{
    use BelongsToOrganization;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }
}
