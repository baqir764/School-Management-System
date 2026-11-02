<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\View\View;

/**
 * A two-sided, print-ready student ID card: front side is the student's
 * photo + details, back side is a scannable QR code (see
 * resources/views/students/_id-card-front.blade.php and
 * ..._id-card-back.blade.php). Kept as its own controller rather than a
 * method on StudentController, since it's a distinct print/report concern
 * (like FeeInvoiceController::print()) rather than CRUD.
 */
class StudentIdCardController extends Controller
{
    /**
     * A single student's ID card, printed straight from their profile page.
     */
    public function show(Student $student): View
    {
        $student->load([
            'enrollments' => fn ($q) => $q->active()->with(['classRoom', 'section']),
            'parents',
        ]);

        return view('students.id-card', [
            'students' => collect([$student]),
            'academicYear' => AcademicYear::current(),
        ]);
    }
}
