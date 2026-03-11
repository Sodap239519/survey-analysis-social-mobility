<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds baseline capital score columns to the households table.
 *
 * These columns store the "before" scores imported from the legacy XLSX file
 * (sheet "ข้อมูลพื้นฐาน", columns ทุนมนุษย์/ทุนกายภาพ/ทุนการเงิน/ทุนธรรมชาติ/ทุนทางสังคม).
 *
 * Values are on the X scale [1.0, 4.0] as stored in the source file.
 * CompareHouseholdSurveyLogic converts them to 0–100 for comparison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->decimal('baseline_score_human',    5, 4)->nullable()->comment('ทุนมนุษย์ (X scale 1.0000–4.0000)');
            $table->decimal('baseline_score_physical', 5, 4)->nullable()->comment('ทุนกายภาพ (X scale 1.0000–4.0000)');
            $table->decimal('baseline_score_financial',5, 4)->nullable()->comment('ทุนการเงิน (X scale 1.0000–4.0000)');
            $table->decimal('baseline_score_natural',  5, 4)->nullable()->comment('ทุนธรรมชาติ (X scale 1.0000–4.0000)');
            $table->decimal('baseline_score_social',   5, 4)->nullable()->comment('ทุนทางสังคม (X scale 1.0000–4.0000)');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'baseline_score_human',
                'baseline_score_physical',
                'baseline_score_financial',
                'baseline_score_natural',
                'baseline_score_social',
            ]);
        });
    }
};
