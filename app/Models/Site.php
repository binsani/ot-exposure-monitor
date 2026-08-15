<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'address', 'latitude', 'longitude', 'criticality_tier'])]
class Site extends Model
{
    use BelongsToOrganization;

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function processAreas(): HasMany { return $this->hasMany(ProcessArea::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
