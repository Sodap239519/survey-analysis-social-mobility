<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->foreignId('person_id')->nullable()->constrained()->onDelete('set null');
            $table->string('period', 10)->default('after')->comment('before|after');
            $table->integer('survey_year')->nullable();
            $table->integer('survey_round')->nullable();
            $table->date('surveyed_at')->nullable();
            $table->string('surveyor_name')->nullable();
            // Computed scores (cached)
            $table->decimal('score_human', 8, 4)->nullable();
            $table->decimal('score_physical', 8, 4)->nullable();
            $table->decimal('score_financial', 8, 4)->nullable();
            $table->decimal('score_natural', 8, 4)->nullable();
            $table->decimal('score_social', 8, 4)->nullable();
            $table->decimal('score_aggregate', 8, 4)->nullable()->comment('X in [1.0,4.0]');
            $table->tinyInteger('poverty_level')->nullable()->comment('1-4');
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
