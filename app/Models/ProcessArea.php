<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'site_id', 'name', 'description'])]
class ProcessArea extends Model
{
    use BelongsToOrganization;

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
