<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['organization_id', 'source_type', 'source_id', 'title', 'message', 'severity', 'status', 'channels_sent'])]
class Alert extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['channels_sent' => 'array'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
