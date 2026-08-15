<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exposure_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('scanned_at');
            $table->string('method');
            $table->string('result');
            $table->text('note')->nullable();
            $table->timestampTz('recheck_at')->nullable();
            $table->jsonb('raw_result')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'scanned_at']);
            $table->index(['asset_id', 'scanned_at']);
        });

        Schema::create('integrity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->timestampTz('detected_at');
            $table->string('detected_via');
            $table->string('severity');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'detected_at']);
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('title');
            $table->text('message');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->jsonb('channels_sent')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'severity']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('integrity_events');
        Schema::dropIfExists('exposure_scans');
    }
};
