<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'manual_fallback_authorization_id', 'drilled_at', 'duration_minutes', 'participants', 'notes', 'logged_by'])]
class FallbackDrill extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['drilled_at' => 'date', 'participants' => 'array'];
    }
}
