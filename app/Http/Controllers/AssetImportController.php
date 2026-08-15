<?php

namespace App\Http\Controllers;

use App\Services\CsvAssetImporter;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetImportController extends Controller
{
    public function create(): Response
    {
        $this->authorizeImport(request());

        return Inertia::render('Assets/Import');
    }

    public function preview(Request $request, CsvAssetImporter $importer): Response
    {
        $this->authorizeImport($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('imports', "{$token}.csv", 'local');
        $request->session()->put("asset_imports.{$token}", true);

        return Inertia::render('Assets/Import', [
            'token' => $token,
            ...$importer->inspect(Storage::disk('local')->path($path)),
        ]);
    }

    public function store(Request $request, CsvAssetImporter $importer): RedirectResponse
    {
        $this->authorizeImport($request);
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'string'],
            'mapping.asset_type' => ['required', 'string'],
            'mapping.site' => ['required', 'string'],
            'mapping.vendor' => ['nullable', 'string'],
            'mapping.model' => ['nullable', 'string'],
            'mapping.firmware_version' => ['nullable', 'string'],
            'mapping.internal_ip' => ['nullable', 'string'],
            'mapping.external_ip' => ['nullable', 'string'],
            'mapping.criticality' => ['nullable', 'string'],
            'mapping.data_source_type' => ['nullable', 'string'],
        ]);
        abort_unless($request->session()->pull("asset_imports.{$validated['token']}"), 403);
        $path = "imports/{$validated['token']}.csv";
        abort_unless(Storage::disk('local')->exists($path), 404);
        try {
            $count = $importer->import(Storage::disk('local')->path($path), $validated['mapping']);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return to_route('assets.index')->with('success', "Imported {$count} assets.");
    }

    private function authorizeImport(Request $request): void
    {
        $role = $request->user()?->organizations()
            ->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless(in_array($role, ['admin', 'operator'], true), 403);
    }
}
