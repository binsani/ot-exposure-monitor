<?php

namespace App\Observers;

use App\Models\Asset;
use App\Services\Advisories\AdvisoryMatcher;

class AssetObserver
{
    public function saved(Asset $asset): void
    {
        if ($asset->wasRecentlyCreated || $asset->wasChanged(['vendor', 'model', 'firmware_version'])) {
            app(AdvisoryMatcher::class)->matchAsset($asset);
        }
    }
}
