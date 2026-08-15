<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'asset_id', 'checked_at', 'detected_via', 'snapshot', 'raw_result'])]
class IntegrityCheck extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'snapshot' => 'array', 'raw_result' => 'array'];
    }
}
