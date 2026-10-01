<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->string('storage_disk')->nullable();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('attachment_storage_disk')->nullable();
            $table->string('attachment_file_name')->nullable();
            $table->string('attachment_file_type')->nullable();
            $table->unsignedBigInteger('attachment_file_size')->nullable();
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->string('storage_disk')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image_storage_disk')->nullable();
            $table->unsignedBigInteger('profile_image_file_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_image_storage_disk', 'profile_image_file_size']);
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn(['storage_disk', 'file_name', 'file_type', 'file_size']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_storage_disk',
                'attachment_file_name',
                'attachment_file_type',
                'attachment_file_size',
            ]);
        });

        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
