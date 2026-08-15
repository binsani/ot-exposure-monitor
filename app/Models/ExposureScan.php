<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'asset_id', 'scanned_at', 'method', 'result', 'note', 'recheck_at', 'raw_result'])]
class ExposureScan extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime', 'recheck_at' => 'datetime', 'raw_result' => 'array'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
