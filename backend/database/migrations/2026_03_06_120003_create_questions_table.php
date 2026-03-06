<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_id')->constrained()->onDelete('cascade');
            $table->string('question_key', 20)->unique()->comment('Q2, Q3, Q3.1, Q3.2, Q4, Q5, Q6...');
            $table->text('text_th')->comment('ข้อความคำถาม');
            $table->string('type', 20)->default('single_select')
                ->comment('single_select|multi_select|numeric|table|special_q6');
            $table->integer('max_score')->default(0)->comment('คะแนนสูงสุดของข้อนี้');
            $table->boolean('has_exclusive_option')->default(false)
                ->comment('มีตัวเลือก 0 ที่ clear ตัวอื่น');
            $table->json('meta')->nullable()->comment('ข้อมูลเพิ่มเติม เช่น penalty config สำหรับ Q6');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
