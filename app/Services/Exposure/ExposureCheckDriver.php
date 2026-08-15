<?php

namespace App\Services\Exposure;

use App\Models\Asset;

interface ExposureCheckDriver
{
    public function check(Asset $asset): ExposureCheckResult;
}
