<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\Exposure\ExposureRecorder;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExposureController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Exposure/Index', [
            'assets' => Asset::with(['site:id,name', 'exposureScans' => fn ($query) => $query->latest('scanned_at')->limit(1)])
                ->when($request->boolean('exposed'), fn ($query) => $query->where('is_internet_facing', true))
                ->orderByDesc('is_internet_facing')->orderBy('name')->paginate(25)->withQueryString(),
            'filters' => ['exposed' => $request->boolean('exposed')],
        ]);
    }

    public function storeManual(Request $request, Asset $asset, ExposureRecorder $recorder): RedirectResponse
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
        $data = $request->validate([
            'result' => ['required', 'in:exposed,not_exposed,unknown'],
            'note' => ['required', 'string', 'max:2000'],
            'recheck_at' => ['nullable', 'date', 'after:today'],
        ]);
        $recorder->record($asset, $data['result'], 'manual', ['attested_by' => $request->user()->id], $data['note'], $data['recheck_at'] ?? null);

        return back()->with('success', 'Exposure status recorded.');
    }
}
