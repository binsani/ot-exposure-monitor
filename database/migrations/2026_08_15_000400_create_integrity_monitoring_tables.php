<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('snapshot');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('accepted_at');
            $table->timestamps();
            $table->index(['organization_id', 'accepted_at']);
        });

        Schema::create('integrity_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('checked_at');
            $table->string('detected_via');
            $table->jsonb('snapshot');
            $table->jsonb('raw_result')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'checked_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestampTz('occurred_at');
            $table->jsonb('metadata')->nullable();
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('integrity_checks');
        Schema::dropIfExists('asset_baselines');
    }
};
