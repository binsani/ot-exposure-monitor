<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentInstallation;
use App\Models\Asset;
use App\Services\Exposure\ExposureRecorder;
use App\Services\Integrity\IntegrityMonitor;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentReportController extends Controller
{
    public function assets(Request $request): JsonResponse
    {
        return $this->withAgent($request, function (AgentInstallation $agent): JsonResponse {
            $assets = Asset::withoutGlobalScopes()->where('organization_id', $agent->organization_id)
                ->where('data_source_type', 'agent')->orderBy('id')->get()
                ->map(fn (Asset $asset) => ['id' => $asset->id, 'name' => $asset->name, 'internal_ip' => $asset->internal_ip, 'firmware_version' => $asset->firmware_version, 'probe_port' => $asset->raw_metadata['agent_probe_port'] ?? null]);

            return response()->json(['assets' => $assets]);
        });
    }

    public function store(Request $request, IntegrityMonitor $integrity, ExposureRecorder $exposure): JsonResponse
    {
        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:50'], 'readings' => ['required', 'array', 'max:100'],
            'readings.*.asset_id' => ['required', 'integer'], 'readings.*.snapshot' => ['required', 'array'],
            'readings.*.snapshot.internal_ip' => ['nullable', 'ip'], 'readings.*.snapshot.fingerprint' => ['nullable', 'string', 'max:255'],
            'readings.*.snapshot.auth_success' => ['nullable', 'boolean'], 'readings.*.snapshot.firmware_version' => ['nullable', 'string', 'max:255'],
            'readings.*.snapshot.config_checksum' => ['nullable', 'string', 'max:255'],
            'readings.*.exposure_result' => ['nullable', 'in:exposed,not_exposed,unknown'], 'readings.*.raw' => ['nullable', 'array'],
        ]);

        return $this->withAgent($request, function (AgentInstallation $agent) use ($data, $integrity, $exposure): JsonResponse {
            $processed = 0;
            foreach ($data['readings'] as $reading) {
                $asset = Asset::withoutGlobalScopes()->where('organization_id', $agent->organization_id)->where('data_source_type', 'agent')->findOrFail($reading['asset_id']);
                $integrity->record($asset, $reading['snapshot'], 'agent', $reading['raw'] ?? []);
                if (isset($reading['exposure_result'])) $exposure->record($asset, $reading['exposure_result'], 'agent_probe', $reading['raw'] ?? []);
                $processed++;
            }
            $agent->update(['last_seen_at' => now(), 'last_ip' => request()->ip(), 'version' => $data['agent_version'] ?? null]);

            return response()->json(['processed' => $processed]);
        });
    }

    private function withAgent(Request $request, callable $callback): JsonResponse
    {
        $token = $request->bearerToken();
        abort_unless($token && str_starts_with($token, 'oteim_'), 401);
        $agent = AgentInstallation::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->where('is_active', true)->firstOrFail();
        $context = app(TenantContext::class);
        $context->set($agent->organization_id);
        try {
            return $callback($agent);
        } finally {
            $context->clear();
        }
    }
}
