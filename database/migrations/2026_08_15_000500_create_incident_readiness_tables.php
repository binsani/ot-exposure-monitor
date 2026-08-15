<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('fallback_drill_stale_months')->default(12);
        });

        Schema::create('incident_playbooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('scope_type')->default('organization');
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_type')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->boolean('is_template')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'scope_type']);
        });

        Schema::create('playbook_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_playbook_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('steps');
            $table->jsonb('authorized_roles');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['incident_playbook_id', 'version']);
        });

        Schema::create('manual_fallback_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('granted_at');
            $table->date('last_drilled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'last_drilled_at']);
        });

        Schema::create('fallback_drills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_fallback_authorization_id')->constrained()->cascadeOnDelete();
            $table->date('drilled_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->jsonb('participants')->nullable();
            $table->text('notes');
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('playbook_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('open');
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->text('improvised_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('alert_incident', function (Blueprint $table): void {
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->primary(['incident_id', 'alert_id']);
        });

        Schema::create('incident_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['incident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_actions');
        Schema::dropIfExists('alert_incident');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('fallback_drills');
        Schema::dropIfExists('manual_fallback_authorizations');
        Schema::dropIfExists('playbook_versions');
        Schema::dropIfExists('incident_playbooks');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('fallback_drill_stale_months'));
    }
};
