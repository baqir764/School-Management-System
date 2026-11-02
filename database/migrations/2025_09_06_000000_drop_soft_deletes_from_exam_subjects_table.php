<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same fix, same reason, as 2025_09_05_000000_drop_soft_deletes_from_classes_and_sections_table:
 * StoreExamSubjectRequest's Rule::unique('exam_subjects', 'subject_id')
 * check (scoped to exam_id) reads the raw table and does not know to skip
 * trashed rows, so removing a subject from an exam and then re-adding it
 * kept failing with "this subject has already been added to this exam" -
 * the subject disappeared from the exam's subject list (Eloquent's normal
 * queries do exclude trashed rows) but its old, still-unique-constrained
 * row silently blocked re-registering the same subject_id+exam_id pair.
 *
 * Any exam_subject already trashed by the time this runs is purged first
 * (mirroring the classes/sections migration) - safe because
 * ExamSubjectController::destroy() already refuses to delete an
 * exam_subject that still has marks recorded against it, so a trashed row
 * can never have marks depending on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('exam_subjects')->whereNotNull('deleted_at')->delete();

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
