<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
            // fixed: cases >= threshold
            // statistical: cases > baseline mean + threshold * SD
            $table->string('rule_type', 12);
            $table->decimal('threshold', 8, 2);
            $table->unsignedInteger('min_cases')->default(1);
            $table->unsignedTinyInteger('baseline_weeks')->default(8);
            $table->string('severity', 10)->default('warning'); // warning | critical
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedInteger('observed');
            $table->decimal('expected', 10, 2)->nullable();
            $table->decimal('limit_value', 10, 2);
            $table->string('severity', 10);
            $table->string('status', 15)->default('open'); // open | acknowledged
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('email_skipped_reason')->nullable();
            $table->timestamps();

            $table->unique(['condition_id', 'region_id', 'week_start']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
    }
};
