<?php

namespace App\Jobs;

use App\Services\Advisories\CisaCsafIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollCisaAdvisories implements ShouldQueue
{
    use Queueable;

    public function handle(CisaCsafIngestor $ingestor): void
    {
        $ingestor->poll();
    }
}
