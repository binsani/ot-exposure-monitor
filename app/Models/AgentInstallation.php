<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'name', 'token_hash', 'is_active', 'last_seen_at', 'last_ip', 'version', 'created_by'])]
#[Hidden(['token_hash'])]
class AgentInstallation extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }
}
