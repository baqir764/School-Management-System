<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classes and sections used to be soft-deleted, but that let a
     * deleted row keep occupying its unique slot forever (classes.
     * numeric_level, sections.[class_id, name]) - re-registering "Grade 6"
     * or section "A" after deleting the original failed with a false
     * "already taken" error, because the trashed row was still sitting in
     * the table. ClassRoomController/SectionController::destroy() now
     * block deletion outright whenever dependent records exist (sections,
     * enrollments, fee structures, timetable entries, attendance records)
     * instead of hiding the row behind deleted_at, so deleting one of
     * these from here on is meant to be a real, permanent delete.
     *
     * Any rows already sitting in the trash need to be purged before the
     * column that flags them is dropped - otherwise dropping deleted_at
     * would silently "undelete" them by making them indistinguishable from
     * live rows. Sections first, then classes, since a class's own
     * cascadeOnDelete would otherwise remove its sections out from under
     * us mid-migration.
     */
    public function up(): void
    {
        DB::table('sections')->whereNotNull('deleted_at')->delete();
        DB::table('classes')->whereNotNull('deleted_at')->delete();

        Schema::table('sections', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
