<?php

namespace App\Services\Exposure;

final readonly class ExposureCheckResult
{
    public function __construct(public string $result, public array $raw = []) {}
}
