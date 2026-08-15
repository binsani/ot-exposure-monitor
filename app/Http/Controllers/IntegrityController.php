<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\IntegrityEvent;
use App\Services\Integrity\IntegrityMonitor;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IntegrityController extends Controller
{
    public function store(Request $request, Asset $asset, IntegrityMonitor $monitor): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $request->validate([
            'internal_ip' => ['nullable', 'ip'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
            'auth_success' => ['nullable', 'boolean'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'config_checksum' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $snapshot = collect($data)->except('note')->filter(fn ($value) => $value !== null && $value !== '')->all();
        if ($snapshot === []) {
            throw ValidationException::withMessages(['snapshot' => 'Enter at least one integrity reading.']);
        }
        $monitor->record($asset, $snapshot, 'manual', ['note' => $data['note'] ?? null], $request->user()->id);

        return back()->with('success', 'Integrity check recorded.');
    }

    public function accept(Request $request, IntegrityEvent $event, IntegrityMonitor $monitor): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $monitor->accept($event, $request->user()->id, $data['note']);

        return back()->with('success', 'Change accepted into the baseline.');
    }

    private function authorizeOperator(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }
}
