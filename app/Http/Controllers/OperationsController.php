<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $failed = DB::table('failed_jobs')->latest('failed_at')->limit(50)->get()->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $firstLine = strtok($job->exception, "\n") ?: 'Background job failed';

            return ['uuid' => $job->uuid, 'queue' => $job->queue, 'name' => $payload['displayName'] ?? 'Background job', 'failed_at' => $job->failed_at, 'error' => str($firstLine)->limit(300)];
        });

        return Inertia::render('Operations/Index', ['failedJobs' => $failed]);
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'queue.job_retried', 'subject_type' => 'failed_job', 'subject_id' => 0, 'occurred_at' => now(), 'metadata' => ['uuid' => $uuid]]);

        return back()->with('success', 'The background job was queued for another attempt.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $role = $request->user()->organizations()->whereKey(app(TenantContext::class)->id())->first()?->pivot->role;
        abort_unless($role === 'admin', 403);
    }
}
