<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acknowledged_at')->nullable();
        });
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('advisory_digest_enabled')->default(true);
            $table->string('advisory_digest_day')->default('monday');
            $table->timestampTz('last_advisory_digest_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn('acknowledged_at');
        });
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['advisory_digest_enabled', 'advisory_digest_day', 'last_advisory_digest_at']);
        });
    }
};
