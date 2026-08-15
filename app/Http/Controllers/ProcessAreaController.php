<?php

namespace App\Http\Controllers;

use App\Models\ProcessArea;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessAreaController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeChange($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']]);
        $site->processAreas()->create([...$data, 'organization_id' => $site->organization_id]);

        return back()->with('success', 'Process area added.');
    }

    public function destroy(Request $request, ProcessArea $processArea): RedirectResponse
    {
        $this->authorizeChange($request);
        abort_if($processArea->assets()->exists(), 422, 'Reassign assets in this process area first.');
        $processArea->delete();

        return back()->with('success', 'Process area removed.');
    }

    private function authorizeChange(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }
}
