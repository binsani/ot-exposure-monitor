<?php

use App\Models\Asset;
use App\Models\Organization;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

it('only returns records for the active organization', function () {
    $first = Organization::create(['name' => 'North Water']);
    $second = Organization::create(['name' => 'South Water']);

    $firstSite = Site::withoutGlobalScopes()->create([
        'organization_id' => $first->id,
        'name' => 'North Plant',
    ]);
    $secondSite = Site::withoutGlobalScopes()->create([
        'organization_id' => $second->id,
        'name' => 'South Plant',
    ]);

    app(TenantContext::class)->set($first->id);

    expect(Site::pluck('name')->all())->toBe(['North Plant']);
    expect(Site::find($secondSite->id))->toBeNull();

    Asset::create([
        'site_id' => $firstSite->id,
        'name' => 'Intake PLC',
        'asset_type' => 'plc',
    ]);

    expect(Asset::first()->organization_id)->toBe($first->id);
});

it('does not apply an untrusted organization id without tenant context', function () {
    Organization::create(['name' => 'North Water']);

    expect(fn () => Site::create(['name' => 'Unscoped Plant']))
        ->toThrow(QueryException::class);
});
