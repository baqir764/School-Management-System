<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Teacher;
use Illuminate\View\View;

/**
 * A two-sided, print-ready teacher ID card: front side is the teacher's
 * photo + details, back side is a scannable QR code carrying
 * {"t":"teacher","a":code,"n":name,"c":specialization}. The attendance
 * scan page recognises the "t" type and identifies the teacher instead of
 * treating the card as a student number.
 */
class TeacherIdCardController extends Controller
{
    /**
     * A single teacher's ID card, printed straight from their profile page.
     */
    public function show(Teacher $teacher): View
    {
        return view('teachers.id-card', [
            'teachers' => collect([$teacher]),
            'academicYear' => AcademicYear::current(),
        ]);
    }
}
