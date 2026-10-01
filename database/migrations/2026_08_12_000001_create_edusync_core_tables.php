<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('is_active');
            $table->index('status');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('grade_level');
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'grade_level']);
            $table->index('status');
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('section');
            $table->string('grade_level');
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('schedule')->nullable();
            $table->string('room')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'grade_level']);
            $table->index('status');
        });

        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->string('status')->default('enrolled');
            $table->timestamps();

            $table->unique(['school_class_id', 'student_id']);
            $table->index('status');
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('total_points', 8, 2)->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->decimal('passing_score', 5, 2)->nullable();
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_choices')->default(false);
            $table->boolean('show_results')->default(true);
            $table->boolean('allow_review')->default(true);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_id', 'status']);
            $table->index('deadline');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->enum('type', [
                'multiple_choice',
                'true_false',
                'identification',
                'matching',
                'sequencing',
                'image_based',
                'multiple_selection',
                'drag_drop',
            ]);
            $table->text('question_text');
            $table->string('image_path')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'order']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->text('option_text')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->string('match_key')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['quiz_question_id', 'order']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->uuid('sync_uuid')->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('device_id')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->decimal('total_points', 8, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'graded'])->default('in_progress');
            $table->json('integrity_log')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'student_id']);
            $table->index('status');
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->json('selected_options')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('attachment_path')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->boolean('allow_resubmit')->default(false);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_id', 'status']);
            $table->index('deadline');
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('sync_uuid')->unique();
            $table->text('text_response')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['not_started', 'draft', 'submitted', 'late', 'graded', 'returned'])->default('not_started');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('device_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assignment_id', 'student_id']);
            $table->index('status');
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->morphs('gradeable');
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'gradeable_type', 'gradeable_id']);
        });

        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_id', 'subject_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('target_audience')->default('all');
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('published_at');
            $table->index('target_audience');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->string('checksum')->nullable();
            $table->enum('status', ['pending', 'syncing', 'synced', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sync_queue_id')->nullable()->constrained('sync_queue')->nullOnDelete();
            $table->string('action');
            $table->text('message')->nullable();
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('module');
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('integrity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['quiz_attempt_id', 'event_type']);
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('type');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('integrity_events');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('class_students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('academic_years');
    }
};
