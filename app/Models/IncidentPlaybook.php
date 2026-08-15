<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'title', 'scope_type', 'site_id', 'asset_type', 'current_version', 'is_template'])]
class IncidentPlaybook extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['is_template' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlaybookVersion::class);
    }

    public function current(): HasOne
    {
        return $this->hasOne(PlaybookVersion::class)->ofMany('version', 'max');
    }
}
