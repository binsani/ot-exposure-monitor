<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'incident_playbook_id', 'version', 'steps', 'authorized_roles', 'created_by', 'created_at'])]
class PlaybookVersion extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['steps' => 'array', 'authorized_roles' => 'array', 'created_at' => 'datetime'];
    }
}
