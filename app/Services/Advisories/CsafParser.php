<?php

namespace App\Services\Advisories;

class CsafParser
{
    public function parse(array $payload): array
    {
        $paths = [];
        $this->walk($payload['product_tree']['branches'] ?? [], [], $paths);
        $affectedIds = collect($payload['vulnerabilities'] ?? [])->flatMap(
            fn ($vulnerability) => $vulnerability['product_status']['known_affected'] ?? []
        )->unique()->values();
        $products = $affectedIds->map(fn ($id) => $this->productFromPath($paths[$id] ?? []))->filter()->unique(fn ($item) => json_encode($item))->values()->all();
        $scores = collect($payload['vulnerabilities'] ?? [])->flatMap(fn ($v) => $v['scores'] ?? [])->pluck('cvss_v3.baseScore')->filter()->map(fn ($v) => (float) $v);
        $maxScore = $scores->max();

        return [
            'source' => 'cisa_ics',
            'external_id' => data_get($payload, 'document.tracking.id'),
            'title' => data_get($payload, 'document.title', 'Untitled CISA advisory'),
            'summary' => collect(data_get($payload, 'document.notes', []))->firstWhere('category', 'summary')['text'] ?? null,
            'severity' => $this->severity($maxScore),
            'cve_ids' => collect($payload['vulnerabilities'] ?? [])->pluck('cve')->filter()->values()->all(),
            'affected_vendors_models' => $products,
            'published_at' => data_get($payload, 'document.tracking.initial_release_date'),
            'updated_at_source' => data_get($payload, 'document.tracking.current_release_date'),
            'raw_payload' => $payload,
        ];
    }

    private function walk(array $branches, array $path, array &$paths): void
    {
        foreach ($branches as $branch) {
            $next = [...$path, ['category' => $branch['category'] ?? null, 'name' => $branch['name'] ?? null]];
            if (isset($branch['product']['product_id'])) {
                $paths[$branch['product']['product_id']] = $next;
            }
            $this->walk($branch['branches'] ?? [], $next, $paths);
        }
    }

    private function productFromPath(array $path): ?array
    {
        if ($path === []) {
            return null;
        }
        $byCategory = collect($path)->keyBy('category');
        $vendor = $byCategory->get('vendor')['name'] ?? $path[0]['name'] ?? null;
        $model = $byCategory->get('product_name')['name'] ?? $byCategory->get('product_family')['name'] ?? $path[1]['name'] ?? null;
        $version = $byCategory->get('product_version')['name'] ?? null;

        return $vendor && $model ? ['vendor' => $vendor, 'model' => $model, 'versions' => $version ? [$version] : []] : null;
    }

    private function severity(?float $score): string
    {
        if ($score === null) {
            return 'unknown';
        }

        return match (true) {
            $score >= 9 => 'critical', $score >= 7 => 'high', $score >= 4 => 'medium', default => 'low'
        };
    }
}
