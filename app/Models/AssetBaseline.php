<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'asset_id', 'snapshot', 'accepted_by', 'accepted_at'])]
class AssetBaseline extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'accepted_at' => 'datetime'];
    }
}
