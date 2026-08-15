<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CsvAssetImporter
{
    public function inspect(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = array_map(fn ($value) => trim((string) $value), fgetcsv($handle) ?: []);
        $rows = [];
        while (count($rows) < 5 && ($row = fgetcsv($handle)) !== false) {
            $rows[] = array_pad($row, count($headers), null);
        }
        fclose($handle);

        if ($headers === []) {
            throw ValidationException::withMessages(['file' => 'The CSV must contain a header row.']);
        }

        return compact('headers', 'rows');
    }

    public function import(string $path, array $mapping): int
    {
        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle) ?: [];
        $indexes = [];
        foreach ($mapping as $field => $header) {
            $index = array_search($header, $headers, true);
            if ($index !== false) {
                $indexes[$field] = $index;
            }
        }

        if (! isset($indexes['name'], $indexes['asset_type'], $indexes['site'])) {
            throw ValidationException::withMessages(['mapping' => 'Name, asset type, and site columns are required.']);
        }

        $count = 0;
        DB::transaction(function () use ($handle, $indexes, &$count): void {
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $siteName = trim((string) ($row[$indexes['site']] ?? ''));
                $site = Site::whereRaw('lower(name) = lower(?)', [$siteName])->first();
                if (! $site) {
                    throw ValidationException::withMessages(['mapping' => "No site named '{$siteName}' exists in this organization."]);
                }

                $attributes = ['site_id' => $site->id];
                foreach ($indexes as $field => $index) {
                    if ($field !== 'site') {
                        $attributes[$field] = trim((string) ($row[$index] ?? '')) ?: null;
                    }
                }
                $attributes['data_source_type'] = $attributes['data_source_type'] ?? 'manual';
                $attributes['criticality'] = $attributes['criticality'] ?? 'medium';
                validator($attributes, [
                    'name' => ['required', 'string', 'max:255'],
                    'asset_type' => ['required', 'in:plc,rtu,hmi,historian,switch,other'],
                    'internal_ip' => ['nullable', 'ip'],
                    'external_ip' => ['nullable', 'ip'],
                    'data_source_type' => ['in:agent,cloud_scan,manual'],
                    'criticality' => ['in:low,medium,high,critical'],
                ])->validate();
                Asset::create($attributes);
                $count++;
            }
        });
        fclose($handle);

        return $count;
    }
}
