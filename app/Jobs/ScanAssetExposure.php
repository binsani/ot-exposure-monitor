<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Services\Exposure\ExposureRecorder;
use App\Services\Exposure\ShodanExposureDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanAssetExposure implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $assetId) {}

    public function handle(ShodanExposureDriver $driver, ExposureRecorder $recorder): void
    {
        $asset = Asset::withoutGlobalScopes()->findOrFail($this->assetId);
        $result = $driver->check($asset);
        $recorder->record($asset, $result->result, 'shodan', $result->raw);
    }
}
