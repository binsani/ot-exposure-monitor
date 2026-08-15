<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sector')->default('water');
            $table->string('plan_tier')->default('trial');
            $table->unsignedSmallInteger('scan_cadence_hours')->default(24);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('current_organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('criticality_tier')->default('standard');
            $table->timestamps();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('process_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('asset_type');
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('internal_ip', 45)->nullable();
            $table->string('external_ip', 45)->nullable();
            $table->boolean('is_internet_facing')->default(false);
            $table->boolean('exposure_override')->nullable();
            $table->string('data_source_type')->default('manual');
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('criticality')->default('medium');
            $table->text('notes')->nullable();
            $table->jsonb('raw_metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'is_internet_facing']);
            $table->index(['organization_id', 'vendor', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('process_areas');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('organization_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_organization_id'));
        Schema::dropIfExists('organizations');
    }
};
