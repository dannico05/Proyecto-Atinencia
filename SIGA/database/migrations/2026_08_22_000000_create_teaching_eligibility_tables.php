<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 180)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 180);
            $table->timestamps();
            $table->index(['career_id', 'name']);
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('term_number');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();
            $table->unique(['year', 'term_number']);
        });

        Schema::create('teaching_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('group_number');
            $table->timestamps();
            $table->unique(['course_id', 'academic_term_id', 'group_number']);
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('national_id', 20)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('second_last_name', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['last_name', 'second_last_name']);
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('degree_level', 30);
            $table->string('institution', 180);
            $table->unsignedSmallInteger('graduation_year');
            $table->string('specialization', 220);
            $table->timestamps();
            $table->unique(['teacher_id', 'degree_level', 'specialization'], 'credentials_teacher_degree_specialization_unique');
        });

        Schema::create('eligibility_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('university_council_agreement', 160);
            $table->string('gazette_number', 80);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'version']);
            $table->index(['course_id', 'valid_from', 'valid_until']);
        });

        Schema::create('eligible_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligibility_catalog_id')->constrained()->cascadeOnDelete();
            $table->string('name', 220);
            $table->timestamps();
            $table->unique(['eligibility_catalog_id', 'name'], 'eligible_specializations_catalog_name_unique');
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('proposed');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->unique(['teaching_group_id', 'teacher_id']);
            $table->index('status');
        });

        Schema::create('eligibility_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eligibility_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 30);
            $table->boolean('provisional')->default(false);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['result', 'created_at']);
        });

        Schema::create('technical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path');
            $table->date('ratification_deadline');
            $table->string('status', 30)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'ratification_deadline']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 60);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('technical_notes');
        Schema::dropIfExists('eligibility_checks');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('eligible_specializations');
        Schema::dropIfExists('eligibility_catalogs');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('teaching_groups');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('careers');
    }
};
