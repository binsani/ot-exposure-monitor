<?php

namespace App\Services\Advisories;

use App\Models\Advisory;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetAdvisoryMatch;

class AdvisoryMatcher
{
    private const VENDOR_ALIASES = ['allen bradley' => 'rockwell automation', 'rockwell' => 'rockwell automation'];

    public function matchAdvisory(Advisory $advisory): int
    {
        $count = 0;
        Asset::withoutGlobalScopes()->whereNotNull('vendor')->whereNotNull('model')->each(function (Asset $asset) use ($advisory, &$count): void {
            $matchedOn = $this->matches($asset, $advisory);
            if (! $matchedOn) {
                return;
            }
            $count += $this->matchAdvisoryForAsset($asset, $advisory, $matchedOn);
        });

        return $count;
    }

    public function matchAsset(Asset $asset): int
    {
        $count = 0;
        Advisory::query()->each(function (Advisory $advisory) use ($asset, &$count): void {
            if ($matchedOn = $this->matches($asset, $advisory)) {
                $count += $this->matchAdvisoryForAsset($asset, $advisory, $matchedOn);
            }
        });

        return $count;
    }

    public function matches(Asset $asset, Advisory $advisory): ?string
    {
        $assetVendor = $this->normalizeVendor($asset->vendor);
        $assetModel = $this->normalize($asset->model);
        foreach ($advisory->affected_vendors_models ?? [] as $affected) {
            if ($this->normalizeVendor($affected['vendor'] ?? '') !== $assetVendor) {
                continue;
            }
            $affectedModel = $this->normalize($affected['model'] ?? '');
            if (! str_contains($assetModel, $affectedModel) && ! str_contains($affectedModel, $assetModel)) {
                continue;
            }
            $versions = array_filter($affected['versions'] ?? []);
            if ($versions !== [] && $asset->firmware_version && ! in_array($this->normalize($asset->firmware_version), array_map([$this, 'normalize'], $versions), true)) {
                continue;
            }

            return $versions === [] ? 'model' : 'model+firmware';
        }

        return null;
    }

    private function matchAdvisoryForAsset(Asset $asset, Advisory $advisory, string $matchedOn): int
    {
        $match = AssetAdvisoryMatch::withoutGlobalScopes()->firstOrCreate(
            ['asset_id' => $asset->id, 'advisory_id' => $advisory->id],
            ['organization_id' => $asset->organization_id, 'matched_on' => $matchedOn, 'status' => 'open']
        );
        if ($match->wasRecentlyCreated) {
            Alert::withoutGlobalScopes()->create([
                'organization_id' => $asset->organization_id, 'source_type' => AssetAdvisoryMatch::class,
                'source_id' => $match->id, 'title' => 'New advisory affects '.$asset->name,
                'message' => $advisory->title, 'severity' => $asset->is_internet_facing ? 'high' : 'medium',
                'status' => 'open', 'channels_sent' => [],
            ]);
        }

        return $match->wasRecentlyCreated ? 1 : 0;
    }

    private function normalizeVendor(?string $value): string
    {
        $normalized = $this->normalize($value);

        return self::VENDOR_ALIASES[$normalized] ?? $normalized;
    }

    private function normalize(?string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $value)));
    }
}
