<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix the foreign key on survey_responses.person_id.
 *
 * Root cause: a rogue migration (2026_03_09_095248_create_people_table) created
 * an empty "people" table.  When the survey_responses table was originally
 * built with `foreignId('person_id')->constrained()` (no explicit table name),
 * Laravel's auto-pluralisation resolved "person" → "people", creating a FK that
 * points to the wrong table.  The actual person records live in "persons".
 *
 * This migration:
 *   1. Drops the stale FK on person_id (whichever table it currently points to).
 *   2. Re-creates the FK pointing to the correct "persons" table.
 *   3. Drops the now-unused "people" table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1 – drop any existing FK on person_id (safe regardless of target table)
        try {
            Schema::table('survey_responses', function (Blueprint $table) {
                $table->dropForeign(['person_id']);
            });
        } catch (\Throwable) {
            // FK may already have been dropped or may not exist in this DB instance
        }

        // Step 2 – add the correct FK pointing to "persons"
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreign('person_id')
                  ->references('id')
                  ->on('persons')
                  ->nullOnDelete();
        });

        // Step 3 – drop the rogue "people" table (created by the stale migration)
        Schema::dropIfExists('people');
    }

    public function down(): void
    {
        // NOTE: Rolling back this migration only restores the database structure.
        // The original 'people' table was an empty artefact (no meaningful data);
        // no data is lost by dropping or recreating it.

        // Restore the "people" table and revert the FK back to it
        if (! Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        try {
            Schema::table('survey_responses', function (Blueprint $table) {
                $table->dropForeign(['person_id']);
            });
        } catch (\Throwable) {
            // Ignore if already gone
        }

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreign('person_id')
                  ->references('id')
                  ->on('people')
                  ->nullOnDelete();
        });
    }
};
