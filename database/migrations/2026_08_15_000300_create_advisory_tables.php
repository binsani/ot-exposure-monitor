<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisories', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('severity')->default('unknown');
            $table->jsonb('cve_ids')->nullable();
            $table->jsonb('affected_vendors_models');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('updated_at_source')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
        });

        Schema::create('asset_advisory_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisory_id')->constrained()->cascadeOnDelete();
            $table->string('matched_on');
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['asset_id', 'advisory_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_advisory_matches');
        Schema::dropIfExists('advisories');
    }
};
