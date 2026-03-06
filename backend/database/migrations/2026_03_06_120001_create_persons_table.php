<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable()->comment('คำนำหน้า');
            $table->string('first_name')->nullable()->comment('ชื่อ');
            $table->string('last_name')->nullable()->comment('สกุล');
            $table->string('citizen_id', 20)->nullable()->comment('หมายเลขบัตรประชาชน');
            $table->string('phone', 20)->nullable()->comment('เบอร์โทรศัพท์');
            $table->boolean('is_head')->default(false)->comment('เป็นหัวหน้าครัวเรือน');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
