<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes to speed up the /api/v1/dashboard endpoint on MySQL.
 *
 * These indexes were designed based on the query patterns in DashboardController:
 *
 *  survey_responses – filtered by period, survey_year, model_name; joined on
 *                     household_id and person_id; pluck('id') is also very common.
 *
 *  answers          – joined on survey_response_id + question_id for income/insight
 *                     lookups.
 *
 *  households       – filtered by district_name/code, subdistrict_name/code,
 *                     and survey_year.
 *
 *  persons          – looked up by household_id (fallback income) and filtered by
 *                     baseline_income_monthly IS NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── survey_responses ──────────────────────────────────────────────────
        Schema::table('survey_responses', function (Blueprint $table) {
            // Dashboard primary filter
            $table->index(['period', 'survey_year'], 'idx_sr_period_year');
            // model_name filter used across many dashboard methods
            $table->index('model_name', 'idx_sr_model_name');
            // person_id – used for distinct respondent count and income lookup
            $table->index('person_id', 'idx_sr_person_id');
            // household_id – used for geographic totals and fallback income lookup
            $table->index('household_id', 'idx_sr_household_id');
        });

        // ── answers ───────────────────────────────────────────────────────────
        Schema::table('answers', function (Blueprint $table) {
            // Income (Q4) and Insight queries join on both columns
            $table->index(['survey_response_id', 'question_id'], 'idx_ans_response_question');
        });

        // ── households ────────────────────────────────────────────────────────
        Schema::table('households', function (Blueprint $table) {
            $table->index('district_name',    'idx_hh_district_name');
            $table->index('district_code',    'idx_hh_district_code');
            $table->index('subdistrict_name', 'idx_hh_subdistrict_name');
            $table->index('subdistrict_code', 'idx_hh_subdistrict_code');
            $table->index('survey_year',      'idx_hh_survey_year');
        });

        // ── persons ───────────────────────────────────────────────────────────
        Schema::table('persons', function (Blueprint $table) {
            // Household-based fallback income lookup
            $table->index('household_id', 'idx_persons_household_id');
            // Partial-index equivalent: speeds up WHERE baseline_income_monthly IS NOT NULL
            $table->index('baseline_income_monthly', 'idx_persons_baseline_income');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropIndex('idx_sr_period_year');
            $table->dropIndex('idx_sr_model_name');
            $table->dropIndex('idx_sr_person_id');
            $table->dropIndex('idx_sr_household_id');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('idx_ans_response_question');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('idx_hh_district_name');
            $table->dropIndex('idx_hh_district_code');
            $table->dropIndex('idx_hh_subdistrict_name');
            $table->dropIndex('idx_hh_subdistrict_code');
            $table->dropIndex('idx_hh_survey_year');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->dropIndex('idx_persons_household_id');
            $table->dropIndex('idx_persons_baseline_income');
        });
    }
};
