<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds baseline_income_monthly column to the persons table.
 *
 * This column stores the "before" average monthly income imported from the
 * legacy XLSX file (sheet "ทุนมนุษย์", column AQ header "รายได้เฉลี่ย (บาท/เดือน)").
 *
 * Nullable integer (บาท/เดือน). NULL means no value was present in the source file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->unsignedInteger('baseline_income_monthly')->nullable()->comment('รายได้เฉลี่ย (บาท/เดือน) จากชีท ทุนมนุษย์');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('baseline_income_monthly');
        });
    }
};
