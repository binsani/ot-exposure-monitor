<?php

namespace App\Services\Advisories;

use App\Models\Advisory;
use Illuminate\Support\Facades\Http;

class CisaCsafIngestor
{
    public function __construct(private CsafParser $parser, private AdvisoryMatcher $matcher) {}

    public function poll(): int
    {
        $since = Advisory::where('source', 'cisa_ics')->max('updated_at_source') ?? now()->subDays(14)->toIso8601String();
        $commits = Http::acceptJson()->withUserAgent('OTEIM/1.0')->timeout(30)
            ->get(config('services.cisa.csaf_commits_url'), [
                'path' => 'csaf_files/OT/white', 'since' => $since, 'per_page' => 30,
            ])->throw()->json();
        $urls = collect($commits)->pluck('url')->filter()->flatMap(function ($url) {
            return collect(Http::acceptJson()->withUserAgent('OTEIM/1.0')->timeout(30)->get($url)->throw()->json('files', []))
                ->filter(fn ($file) => str_starts_with($file['filename'] ?? '', 'csaf_files/OT/white/') && str_ends_with($file['filename'] ?? '', '.json'))
                ->pluck('raw_url');
        })->filter()->unique();

        $count = 0;
        foreach ($urls as $url) {
            $payload = Http::acceptJson()->withUserAgent('OTEIM/1.0')->timeout(30)->get($url)->throw()->json();
            if ($this->ingestPayload($payload)->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    public function ingestPayload(array $payload): Advisory
    {
        $data = $this->parser->parse($payload);
        $advisory = Advisory::updateOrCreate(
            ['source' => $data['source'], 'external_id' => $data['external_id']],
            $data
        );
        $this->matcher->matchAdvisory($advisory);

        return $advisory;
    }
}
