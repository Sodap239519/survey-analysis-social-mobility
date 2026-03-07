<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->string('choice_key', 20)->comment('0, 1, 2, 1.1, 1.2 ...')->nullable();
            $table->text('text_th')->comment('ข้อความตัวเลือก');
            $table->decimal('weight', 8, 2)->default(0)->comment('น้ำหนักคะแนน (TODO: กำหนดตามผู้เชี่ยวชาญ)');
            $table->boolean('is_exclusive')->default(false)
                ->comment('เลือกแล้ว clear ตัวเลือกอื่น (เช่น "0) ไม่มี")');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choices');
    }
};
