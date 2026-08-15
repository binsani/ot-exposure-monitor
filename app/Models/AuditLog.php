<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'occurred_at', 'metadata'])]
class AuditLog extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }
}
