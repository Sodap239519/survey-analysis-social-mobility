<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            // For multi-select: store all selected choice IDs as JSON
            $table->json('selected_choice_ids')->nullable();
            // For numeric/text answers
            $table->text('value_text')->nullable();
            $table->decimal('value_numeric', 12, 2)->nullable();
            // Computed score for this question
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['survey_response_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
