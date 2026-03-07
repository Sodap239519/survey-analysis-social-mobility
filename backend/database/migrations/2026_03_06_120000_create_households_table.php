<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('house_code', 20)->unique()->comment('รหัสบ้าน 11 หลัก');
            $table->string('village_name')->nullable()->comment('ชื่อหมู่บ้าน');
            $table->string('village_no')->nullable()->comment('หมู่ที่');
            $table->string('subdistrict_code', 10)->nullable()->comment('รหัสตำบล');
            $table->string('subdistrict_name')->nullable()->comment('ตำบล');
            $table->string('district_code', 10)->nullable()->comment('รหัสอำเภอ');
            $table->string('district_name')->nullable()->comment('อำเภอ');
            $table->string('province_code', 10)->nullable()->comment('รหัสจังหวัด');
            $table->string('province_name')->nullable()->comment('จังหวัด');
            $table->string('postal_code', 10)->nullable()->comment('รหัสไปรษณีย์');
            $table->string('house_no')->nullable()->comment('บ้านเลขที่');
            $table->string('road')->nullable()->comment('ถนน');
            $table->string('alley')->nullable()->comment('ซอย');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('survey_year')->nullable()->comment('ปีที่สำรวจ');
            $table->integer('survey_round')->nullable()->comment('ครั้งที่สำรวจ');
            $table->json('raw_data')->nullable()->comment('ข้อมูลดิบจาก CSV/XLSX');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
