<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AssetAdvisoryMatch;
use App\Models\AuditLog;
use App\Models\IntegrityEvent;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', 'in:open,acknowledged,resolved'], 'severity' => ['nullable', 'in:critical,high,medium,low'], 'source' => ['nullable', 'in:exposure,integrity,advisory']]);

        return Inertia::render('Alerts/Index', [
            'alerts' => Alert::query()->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
                ->when($filters['severity'] ?? null, fn ($q, $value) => $q->where('severity', $value))
                ->when($filters['source'] ?? null, function ($q, $value) {
                    if ($value === 'advisory') {
                        return $q->where('source_type', AssetAdvisoryMatch::class);
                    }

                    return $q->where('source_type', IntegrityEvent::class)->whereHasMorph('source', IntegrityEvent::class,
                        fn ($source) => $value === 'exposure' ? $source->where('event_type', 'exposure_change') : $source->where('event_type', '!=', 'exposure_change'));
                })
                ->latest()->paginate(30)->withQueryString(),
            'filters' => $filters,
            'counts' => Alert::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'digest' => Organization::findOrFail(app(TenantContext::class)->id())->only(['advisory_digest_enabled', 'advisory_digest_day']),
        ]);
    }

    public function updateDigest(Request $request): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $request->validate(['advisory_digest_enabled' => ['required', 'boolean'], 'advisory_digest_day' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday']]);
        Organization::findOrFail(app(TenantContext::class)->id())->update($data);

        return back()->with('success', 'Digest schedule updated.');
    }

    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorizeOperator($request);
        if ($alert->status === 'open') {
            $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
            $this->audit($request, $alert, 'alert.acknowledged');
        }

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        $alert->update(['status' => 'resolved', 'acknowledged_by' => $alert->acknowledged_by ?: $request->user()->id, 'acknowledged_at' => $alert->acknowledged_at ?: now()]);
        $this->audit($request, $alert, 'alert.resolved', $data);

        return back()->with('success', 'Alert resolved.');
    }

    private function authorizeOperator(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }

    private function audit(Request $request, Alert $alert, string $action, array $metadata = []): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => Alert::class, 'subject_id' => $alert->id, 'occurred_at' => now(), 'metadata' => $metadata]);
    }
}
