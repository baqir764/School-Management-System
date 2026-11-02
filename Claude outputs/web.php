<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Academics\AcademicYearController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamSubjectController;
use App\Http\Controllers\Exams\GradeScaleController;
use App\Http\Controllers\Marks\MarksController;
use App\Http\Controllers\Academics\ClassRoomController;
use App\Http\Controllers\Academics\SectionController;
use App\Http\Controllers\Academics\SubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fees\FeeInvoiceController;
use App\Http\Controllers\Fees\FeePaymentController;
use App\Http\Controllers\Fees\FeeStructureController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Parents\ParentController;
use App\Http\Controllers\Payroll\EmployeeController;
use App\Http\Controllers\Payroll\SalaryPaymentController;
use App\Http\Controllers\Settings\SchoolSettingController;
use App\Http\Controllers\Students\EnrollmentController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentDocumentController;
use App\Http\Controllers\Students\StudentIdCardController;
use App\Http\Controllers\Teachers\TeacherController;
use App\Http\Controllers\Timetable\PeriodController;
use App\Http\Controllers\Timetable\TimetableEntryController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Shared hosts without shell access (like this project's InfinityFree
// account) can't create the "public/storage" symlink that
// `php artisan storage:link` normally makes locally. This route serves
// the same files directly instead, so uploaded photos/logos/documents
// under storage/app/public keep working without any symlink at all -
// safely, since the resolved path is checked to still be inside that
// folder before anything is served.
Route::get('/storage/{path}', function (string $path) {
    $root = realpath(storage_path('app/public'));
    $fullPath = $root ? realpath($root.'/'.$path) : false;

    abort_unless($fullPath && str_starts_with($fullPath, $root) && is_file($fullPath), 404);

    return response()->file($fullPath);
})->where('path', '.*')->name('storage.local');

Route::redirect('/', '/dashboard');

Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('account/password', [PasswordController::class, 'update'])->name('account.password.update');
});

Route::middleware(['auth', 'active', 'force_password_change'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
     * Every user's own notification inbox (currently just fee-payment
     * reminders - see FeeInvoiceController::sendReminders() - but stays
     * generic for anything else that later calls ->notify()). No
     * permission gate: a notification belongs to whoever it was sent to,
     * and NotificationController scopes every action through
     * auth()->user()->notifications() rather than a route-bound model.
     */
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SchoolSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SchoolSettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::resource('users', UserController::class)->except(['show']);
    });

    /*
     * Academic structure (years/classes/sections/subjects). Read-only
     * access is split from full management so roles like Principal can see
     * the structure without being able to change it (spec section 30).
     *
     * IMPORTANT ordering note: within each pair below, the "manage" group
     * (which owns the literal GET .../create route) is registered BEFORE
     * the "view" group (which owns the wildcard GET .../{id} show route).
     * Laravel matches routes in registration order, and a wildcard segment
     * like {teacher} matches the literal string "create" just as happily
     * as it matches a real ID. If "show" were registered first, every
     * .../create request would be swallowed by the show route (with the
     * id parameter literally equal to "create"), fail to resolve a model,
     * and 404 - which is exactly what happened before this fix. Resources
     * with no show() method (academic-years, sections, subjects) instead
     * just exclude 'show' entirely, since Route::resource()->except([...])
     * registers a show route by default even without a controller method
     * for it to call.
     */
    Route::middleware('permission:academics.manage')->group(function () {
        Route::patch('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::resource('academic-years', AcademicYearController::class)->except(['index', 'show']);
        Route::resource('classes', ClassRoomController::class)->except(['index', 'show']);
        Route::resource('sections', SectionController::class)->except(['index', 'show']);
        Route::post('subjects/import', [SubjectController::class, 'import'])->name('subjects.import');
        Route::resource('subjects', SubjectController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:academics.view')->group(function () {
        Route::resource('academic-years', AcademicYearController::class)->only(['index']);
        Route::resource('classes', ClassRoomController::class)->only(['index', 'show']);
        Route::resource('sections', SectionController::class)->only(['index']);
        // No ordering hazard here (unlike fee-invoices/dues above):
        // subjects has no show() route at all to collide with, so this
        // literal path is safe wherever it's registered relative to the
        // resource below.
        Route::get('subjects/export', [SubjectController::class, 'export'])->name('subjects.export');
        Route::resource('subjects', SubjectController::class)->only(['index']);
    });

    Route::middleware('permission:teachers.manage')->group(function () {
        Route::post('teachers/import', [TeacherController::class, 'import'])->name('teachers.import');
        Route::resource('teachers', TeacherController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:teachers.view')->group(function () {
        // Registered before the resource's wildcard GET teachers/{teacher}
        // show route for the same reason as subjects.export/fee-invoices.dues
        // elsewhere in this file - a literal "export" segment would
        // otherwise be swallowed by {teacher} and 404 trying to resolve a
        // teacher named "export".
        Route::get('teachers/export', [TeacherController::class, 'export'])->name('teachers.export');
        Route::resource('teachers', TeacherController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:students.manage')->group(function () {
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        Route::resource('students', StudentController::class)->except(['index', 'show']);
        Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
        Route::delete('students/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');
        Route::get('students/{student}/enroll', [EnrollmentController::class, 'create'])->name('students.enroll.create');
        Route::post('students/{student}/enroll', [EnrollmentController::class, 'store'])->name('students.enroll.store');
        Route::delete('students/{student}/enroll/{enrollment}', [EnrollmentController::class, 'destroy'])->name('students.enroll.destroy');
    });

    Route::middleware('permission:students.view')->group(function () {
        // Same wildcard-swallowing hazard as teachers.export above -
        // registered before the resource's GET students/{student} show
        // route.
        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::resource('students', StudentController::class)->only(['index', 'show']);
        // Printable two-sided ID card (front: photo + details, back: QR
        // code - see StudentIdCardController). Sits one segment deeper than
        // the resource's show route above, so it never collides with it.
        Route::get('students/{student}/id-card', [StudentIdCardController::class, 'show'])->name('students.id-card');
        Route::get('students/{student}/documents/{document}/download', [StudentDocumentController::class, 'download'])->name('students.documents.download');
    });

    Route::middleware('permission:parents.manage')->group(function () {
        Route::post('parents/import', [ParentController::class, 'import'])->name('parents.import');
        Route::resource('parents', ParentController::class)->except(['index', 'show']);
        Route::post('parents/{parent}/link-student', [ParentController::class, 'linkStudent'])->name('parents.link-student');
        Route::delete('parents/{parent}/unlink-student/{student}', [ParentController::class, 'unlinkStudent'])->name('parents.unlink-student');
    });

    Route::middleware('permission:parents.view')->group(function () {
        // Same wildcard-swallowing hazard as teachers.export above -
        // registered before the resource's GET parents/{parent} show route.
        Route::get('parents/export', [ParentController::class, 'export'])->name('parents.export');
        Route::resource('parents', ParentController::class)->only(['index', 'show']);
    });

    /*
     * Timetable (spec section 15): a shared bank of daily periods, plus
     * per-section weekly entries assigning a subject/teacher to each
     * period/day slot. Same manage-before-view registration order as the
     * academic structure above, for the same reason. Periods have no
     * show() page (excluded like sections/subjects), and timetable
     * entries are managed entirely through explicit routes rather than
     * Route::resource() since they're edited from the weekly grid
     * (GET timetable) rather than their own index.
     */
    Route::middleware('permission:timetable.manage')->group(function () {
        Route::resource('periods', PeriodController::class)->except(['index', 'show']);
        Route::get('timetable-entries/create', [TimetableEntryController::class, 'create'])->name('timetable-entries.create');
        Route::post('timetable-entries', [TimetableEntryController::class, 'store'])->name('timetable-entries.store');
        Route::get('timetable-entries/{timetable_entry}/edit', [TimetableEntryController::class, 'edit'])->name('timetable-entries.edit');
        Route::put('timetable-entries/{timetable_entry}', [TimetableEntryController::class, 'update'])->name('timetable-entries.update');
        Route::delete('timetable-entries/{timetable_entry}', [TimetableEntryController::class, 'destroy'])->name('timetable-entries.destroy');
    });

    Route::middleware('permission:timetable.view')->group(function () {
        Route::resource('periods', PeriodController::class)->only(['index']);
        Route::get('timetable', [TimetableEntryController::class, 'index'])->name('timetable.index');
    });

    /*
     * Attendance (spec section 16): a daily per-section roster. A Teacher
     * may only mark/view attendance for their own homeroom section(s) -
     * enforced inside AttendanceController/MarkAttendanceRequest, not just
     * via permission, since "attendance.mark" alone doesn't say *which*
     * section. School Administrator/Super Administrator/Principal see and
     * (for the first two) mark every section.
     */
    Route::middleware('permission:attendance.mark')->group(function () {
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('attendance/import', [AttendanceController::class, 'import'])->name('attendance.import');
    });

    Route::middleware('permission:attendance.view')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    });

    /*
     * Exams & grading scale (spec sections 17-18). Exams have a real
     * show() page (subject attachment + publish toggle), so - exactly
     * like teachers/students/parents above - the manage group (owning the
     * literal .../create route) must be registered before the view group
     * (owning the wildcard .../{exam} show route). Grade scale has no
     * show() page and shares the exams.* permissions rather than its own
     * (see StoreGradeScaleRequest).
     */
    Route::middleware('permission:exams.manage')->group(function () {
        Route::patch('exams/{exam}/toggle-publish', [ExamController::class, 'togglePublish'])->name('exams.toggle-publish');
        Route::resource('exams', ExamController::class)->except(['index', 'show']);
        Route::post('exams/{exam}/subjects', [ExamSubjectController::class, 'store'])->name('exams.subjects.store');
        Route::get('exam-subjects/{exam_subject}/edit', [ExamSubjectController::class, 'edit'])->name('exam-subjects.edit');
        Route::put('exam-subjects/{exam_subject}', [ExamSubjectController::class, 'update'])->name('exam-subjects.update');
        Route::delete('exam-subjects/{exam_subject}', [ExamSubjectController::class, 'destroy'])->name('exam-subjects.destroy');
        Route::resource('grade-scales', GradeScaleController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:exams.view')->group(function () {
        Route::resource('exams', ExamController::class)->only(['index', 'show']);
        Route::resource('grade-scales', GradeScaleController::class)->only(['index']);
    });

    /*
     * Marks entry (spec section 18): a grid per exam-subject, plus a
     * per-student results summary across a whole exam. Both routes take
     * an already-scoped model (ExamSubject/Exam), so there's no
     * create/show ordering hazard to worry about here. Subject-level
     * scoping for a Teacher (their own subjects only) is enforced inside
     * MarksController/StoreMarksRequest, the same reasoning as Attendance.
     */
    Route::middleware('permission:marks.enter')->group(function () {
        Route::post('marks/{exam_subject}', [MarksController::class, 'store'])->name('marks.store');
    });

    Route::middleware('permission:marks.view')->group(function () {
        Route::get('marks/{exam_subject}', [MarksController::class, 'index'])->name('marks.index');
        Route::get('exams/{exam}/results', [MarksController::class, 'results'])->name('exams.results');
    });

    /*
     * Fee structure (spec section 19): defines what should be charged -
     * invoices/payments against these fee heads are registered in the
     * next block below. No show() page here, so - like
     * sections/subjects/periods above - the ordering between the manage
     * and view groups doesn't matter.
     */
    Route::middleware('permission:fees.manage')->group(function () {
        Route::resource('fee-structures', FeeStructureController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:fees.view')->group(function () {
        Route::resource('fee-structures', FeeStructureController::class)->only(['index']);
    });

    /*
     * Fee invoices & payments (spec section 20): an invoice snapshots a
     * fee structure's amount for one student; payments accumulate against
     * it and balance/status are always derived live, never stored (see
     * FeeInvoice::balance()/status()). "dues" is a literal path at the
     * same URI depth as fee-invoices/{fee_invoice} - it's registered
     * ahead of the resource's show route within the same (fees.view)
     * group so Laravel's wildcard matching can never swallow it, the same
     * hazard already worked around for teachers/students/parents/exams
     * above. fees.manage owns invoice *generation* (bulk, from a fee
     * structure); fees.collect is the narrower day-to-day ability to
     * record one payment and print its receipt. edit/update (the
     * per-invoice discount screen) sit under the {fee_invoice} wildcard
     * at a different depth/method than the view group's show route below,
     * so there's no ordering hazard for them either.
     */
    Route::middleware('permission:fees.manage')->group(function () {
        Route::get('fee-structures/{fee_structure}/generate-invoices', [FeeInvoiceController::class, 'create'])->name('fee-invoices.create');
        Route::post('fee-structures/{fee_structure}/generate-invoices', [FeeInvoiceController::class, 'store'])->name('fee-invoices.store');
        Route::resource('fee-invoices', FeeInvoiceController::class)->only(['edit', 'update']);
        Route::post('fee-invoices/send-reminders', [FeeInvoiceController::class, 'sendReminders'])->name('fee-invoices.send-reminders');
    });

    Route::middleware('permission:fees.view')->group(function () {
        // "print" is a literal path at the same URI depth as
        // fee-invoices/{fee_invoice} (show) - same wildcard-swallowing
        // hazard as "dues" above it, so it's registered ahead of the
        // resource's show route for the same reason.
        Route::get('fee-invoices/dues', [FeeInvoiceController::class, 'dues'])->name('fee-invoices.dues');
        Route::get('fee-invoices/print', [FeeInvoiceController::class, 'printMany'])->name('fee-invoices.print-many');
        Route::resource('fee-invoices', FeeInvoiceController::class)->only(['index', 'show']);
        Route::get('fee-invoices/{fee_invoice}/print', [FeeInvoiceController::class, 'print'])->name('fee-invoices.print');
        Route::get('fee-payments/{fee_payment}/receipt', [FeePaymentController::class, 'receipt'])->name('fee-payments.receipt');
    });

    Route::middleware('permission:fees.collect')->group(function () {
        Route::post('fee-invoices/{fee_invoice}/payments', [FeePaymentController::class, 'store'])->name('fee-invoices.payments.store');
    });

    /*
     * Employees & Payroll (spec section 21): non-teaching staff HR
     * records plus salary payments to both Teachers and Employees via a
     * polymorphic "payee" (see SalaryPayment::payee() and the morph map
     * in AppServiceProvider). payroll.manage owns employee records and
     * salary amounts; payroll.pay is the narrower day-to-day ability to
     * submit one salary payment and print its voucher; payroll.view
     * covers read-only access to staff lists, payroll history, and the
     * Finance Overview page. Employees has a show() page, so - exactly
     * like teachers/students/parents/exams above - the manage group
     * (owning the literal .../create route) is registered before the
     * view group (owning the wildcard .../{employee} show route).
     * salary-payments has no {id} route at depth 1 at all (only
     * /create, and /{salary_payment}/receipt at a different depth), so
     * there's no such hazard for it.
     */
    Route::middleware('permission:payroll.manage')->group(function () {
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
        Route::resource('employees', EmployeeController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:payroll.view')->group(function () {
        // Same wildcard-swallowing hazard as teachers.export above -
        // registered before the resource's GET employees/{employee} show
        // route.
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::resource('employees', EmployeeController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:payroll.pay')->group(function () {
        Route::get('salary-payments/create', [SalaryPaymentController::class, 'create'])->name('salary-payments.create');
        Route::post('salary-payments', [SalaryPaymentController::class, 'store'])->name('salary-payments.store');
    });

    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('salary-payments', [SalaryPaymentController::class, 'index'])->name('salary-payments.index');
        Route::get('salary-payments/{salary_payment}/receipt', [SalaryPaymentController::class, 'receipt'])->name('salary-payments.receipt');
        Route::get('finance/overview', [FinanceController::class, 'index'])->name('finance.overview');
    });
});

require __DIR__.'/auth.php';
