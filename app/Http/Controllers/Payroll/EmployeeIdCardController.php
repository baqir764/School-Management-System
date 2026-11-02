<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Employee;
use Illuminate\View\View;

/**
 * A two-sided, print-ready employee ID card: front side is the employee's
 * details (employees carry no photo in this system, so a placeholder
 * icon), back side is a scannable QR code carrying
 * {"t":"employee","a":code,"n":name,"c":position}. The attendance scan
 * page recognises the "t" type and identifies the employee instead of
 * treating the card as a student number.
 */
class EmployeeIdCardController extends Controller
{
    /**
     * A single employee's ID card, printed straight from their profile page.
     */
    public function show(Employee $employee): View
    {
        return view('employees.id-card', [
            'employees' => collect([$employee]),
            'academicYear' => AcademicYear::current(),
        ]);
    }
}
