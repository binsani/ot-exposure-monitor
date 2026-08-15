<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'site_id', 'asset_id', 'playbook_version_id', 'title', 'status', 'started_at', 'resolved_at', 'improvised_notes', 'resolution_notes', 'created_by'])]
class Incident extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function playbookVersion(): BelongsTo
    {
        return $this->belongsTo(PlaybookVersion::class);
    }

    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(IncidentAction::class);
    }
}
