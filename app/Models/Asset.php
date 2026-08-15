<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'site_id', 'process_area_id', 'name', 'asset_type', 'vendor', 'model', 'firmware_version', 'internal_ip', 'external_ip', 'is_internet_facing', 'exposure_override', 'data_source_type', 'last_seen_at', 'criticality', 'notes', 'raw_metadata'])]
class Asset extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'is_internet_facing' => 'boolean',
            'exposure_override' => 'boolean',
            'last_seen_at' => 'datetime',
            'raw_metadata' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function processArea(): BelongsTo
    {
        return $this->belongsTo(ProcessArea::class);
    }

    public function exposureScans(): HasMany
    {
        return $this->hasMany(ExposureScan::class);
    }

    public function integrityEvents(): HasMany
    {
        return $this->hasMany(IntegrityEvent::class);
    }
}
