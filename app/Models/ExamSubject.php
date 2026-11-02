<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ExamSubjects don't soft-delete: ExamSubjectController::destroy() blocks
 * deletion outright whenever marks already reference it, so by the time
 * delete() runs it's meant to be a real, permanent removal - see the
 * drop_soft_deletes_from_exam_subjects_table migration for why a trashed
 * row was actively harmful here (StoreExamSubjectRequest's Rule::unique
 * check on exam_id+subject_id sees every row in the table, trashed ones
 * included, so re-adding a subject after removing it from an exam kept
 * failing with "this subject has already been added to this exam" even
 * though it no longer showed anywhere in the UI) - the same root cause,
 * and the same fix, as Section/ClassRoom earlier.
 */
class ExamSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'exam_date',
        'full_marks',
        'pass_marks',
    ];

    /**
     * exam_date is a plain Shamsi "YYYY-MM-DD" string, not cast to 'date'
     * - see Student::$date_of_birth's comment (App\Support\JalaliDate).
     */
    protected function casts(): array
    {
        return [
            'full_marks' => 'integer',
            'pass_marks' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }
}
