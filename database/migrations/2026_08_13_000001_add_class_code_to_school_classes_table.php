<?php

use App\Models\SchoolClass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('class_code', 8)->nullable()->unique()->after('status');
        });

        SchoolClass::withTrashed()->whereNull('class_code')->each(function (SchoolClass $class) {
            $class->update(['class_code' => SchoolClass::generateUniqueClassCode()]);
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('class_code');
        });
    }
};
