<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mark extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'exam_subject_id',
        'student_id',
        'marks_obtained',
        'is_absent',
        'remarks',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Percentage scored, or null when absent or not yet graded. Requires
     * the examSubject relation to already be loaded (for full_marks) -
     * every controller that calls this eager-loads it, since accessing an
     * un-loaded relation throws under this app's strict Eloquent mode.
     */
    public function percentage(): ?float
    {
        if ($this->is_absent || is_null($this->marks_obtained) || (float) $this->examSubject->full_marks <= 0) {
            return null;
        }

        return round(((float) $this->marks_obtained / (float) $this->examSubject->full_marks) * 100, 2);
    }

    public function isPassing(): ?bool
    {
        if ($this->is_absent || is_null($this->marks_obtained)) {
            return null;
        }

        return (float) $this->marks_obtained >= (float) $this->examSubject->pass_marks;
    }
}
