<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->string('google_drive_file_id')->nullable()->after('file_path')->index();
            $table->string('original_file_name')->nullable()->after('google_drive_file_id');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropIndex(['google_drive_file_id']);
            $table->dropColumn(['google_drive_file_id', 'original_file_name', 'file_size']);
        });
    }
};
