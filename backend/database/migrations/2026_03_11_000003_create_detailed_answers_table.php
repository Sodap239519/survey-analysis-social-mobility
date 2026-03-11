<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the detailed_answers table for storing rich/complex question data
 * that doesn't fit in the simplified choices of the answers table.
 * Examples: household expense details (11 categories), debt source details (12 sources),
 * savings types (6 types), income sources, satisfaction ratings, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detailed_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')
                  ->constrained('survey_responses')
                  ->onDelete('cascade');
            $table->string('question_code', 20)->comment('e.g. Q10_expenses, Q11_savings, Q12_debts, Q18_satisfaction');
            $table->text('answer_value')->nullable()->comment('Primary answer value or JSON');
            $table->json('sub_answers')->nullable()->comment('Structured sub-answer data as JSON');
            $table->timestamps();

            $table->index(['survey_response_id', 'question_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detailed_answers');
    }
};
