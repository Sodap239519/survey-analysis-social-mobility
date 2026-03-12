<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->json('sheet_results')->nullable()->comment('Detailed results per sheet');
            $table->decimal('file_size_mb', 8, 2)->nullable()->comment('File size in MB');
            $table->decimal('processing_time', 8, 2)->nullable()->comment('Processing time in seconds');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['sheet_results', 'file_size_mb', 'processing_time']);
        });
    }
};
