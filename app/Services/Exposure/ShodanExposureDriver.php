<?php

namespace App\Services\Exposure;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShodanExposureDriver implements ExposureCheckDriver
{
    private const ICS_PORTS = [502, 2222, 44818, 20000, 47808];

    private const ICS_TERMS = ['micrologix', 'allen-bradley', 'rockwell', 'modbus', 'ethernet/ip', 'dnp3', 'bacnet', 'siemens', 'schneider', 'wonderware'];

    public function check(Asset $asset): ExposureCheckResult
    {
        if (! $asset->external_ip) {
            return new ExposureCheckResult('unknown', ['reason' => 'missing_external_ip']);
        }
        $key = config('services.shodan.key');
        if (! $key) {
            throw new RuntimeException('SHODAN_API_KEY is not configured.');
        }

        $response = Http::baseUrl('https://api.shodan.io')->timeout(20)->retry(2, 250)
            ->get('/shodan/host/'.$asset->external_ip, ['key' => $key]);
        if ($response->status() === 404) {
            return new ExposureCheckResult('not_exposed', ['status' => 404]);
        }
        $response->throw();
        $payload = $response->json();
        $ports = array_map('intval', $payload['ports'] ?? []);
        $banner = strtolower(json_encode($payload['data'] ?? []));
        $matchedPorts = array_values(array_intersect(self::ICS_PORTS, $ports));
        $matchedTerms = array_values(array_filter(self::ICS_TERMS, fn ($term) => str_contains($banner, $term)));
        $exposed = $matchedPorts !== [] || $matchedTerms !== [];

        return new ExposureCheckResult($exposed ? 'exposed' : 'not_exposed', [
            'ip' => $asset->external_ip,
            'last_update' => $payload['last_update'] ?? null,
            'matched_ports' => $matchedPorts,
            'matched_terms' => $matchedTerms,
            'ports' => $ports,
        ]);
    }
}
