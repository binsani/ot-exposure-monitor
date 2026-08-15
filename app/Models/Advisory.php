<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'external_id', 'title', 'summary', 'severity', 'cve_ids', 'affected_vendors_models', 'published_at', 'updated_at_source', 'raw_payload'])]
class Advisory extends Model
{
    protected function casts(): array
    {
        return ['cve_ids' => 'array', 'affected_vendors_models' => 'array', 'published_at' => 'datetime', 'updated_at_source' => 'datetime', 'raw_payload' => 'array'];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AssetAdvisoryMatch::class);
    }
}
