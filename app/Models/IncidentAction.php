<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'incident_id', 'actor_id', 'occurred_at', 'description', 'metadata'])]
class IncidentAction extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }
}
