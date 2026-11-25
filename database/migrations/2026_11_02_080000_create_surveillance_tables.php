<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('iso_code', 6)->unique();   // ISO 3166-2:TZ, e.g. TZ-08
            $table->string('name');
            $table->unsignedInteger('population_2022');
            $table->unsignedTinyInteger('tile_col');
            $table->unsignedTinyInteger('tile_row');
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('level', 20); // hospital | health_centre | dispensary
            $table->timestamps();
        });

        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icd10_code', 8)->unique();
            $table->string('snomed_code', 18)->nullable();
            $table->string('case_basis', 20); // suspected | confirmed
            $table->timestamps();
        });

        // One row per facility, condition and ISO week. A facility that sent its weekly
        // report has a row for every condition (zero reporting); a missing report has none.
        Schema::create('weekly_case_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->unsignedInteger('cases')->default(0);
            $table->unsignedInteger('deaths')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'condition_id', 'week_start']);
            $table->index(['condition_id', 'week_start']);
        });

        Schema::create('immunization_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('vaccine', 10); // MR1 | Penta3
            $table->unsignedInteger('doses');
            $table->timestamps();

            $table->unique(['facility_id', 'month', 'vaccine']);
        });

        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('platform', 30);
            $table->unsignedInteger('impressions');
            $table->unsignedInteger('reach');
            $table->unsignedInteger('engagements');
            $table->unsignedInteger('link_clicks');
            $table->unsignedInteger('shares');
            $table->timestamps();

            $table->unique(['metric_date', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('immunization_reports');
        Schema::dropIfExists('weekly_case_reports');
        Schema::dropIfExists('conditions');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('regions');
    }
};
