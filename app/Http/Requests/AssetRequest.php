<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->organizations()
            ->whereKey(app(TenantContext::class)->id())->first()?->pivot->role, ['admin', 'operator'], true);
    }

    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'site_id' => ['required', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
            'process_area_id' => ['nullable', Rule::exists('process_areas', 'id')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:255'],
            'asset_type' => ['required', Rule::in(['plc', 'rtu', 'hmi', 'historian', 'switch', 'other'])],
            'vendor' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'internal_ip' => ['nullable', 'ip'],
            'external_ip' => ['nullable', 'ip'],
            'data_source_type' => ['required', Rule::in(['agent', 'cloud_scan', 'manual'])],
            'criticality' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
