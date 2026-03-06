<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitals', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique()->comment('human|physical|financial|natural|social');
            $table->string('name_th')->comment('ชื่อภาษาไทย');
            $table->string('name_en')->comment('ชื่อภาษาอังกฤษ');
            $table->integer('max_score')->default(100)->comment('คะแนนสูงสุดรวมของทุน');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitals');
    }
};
