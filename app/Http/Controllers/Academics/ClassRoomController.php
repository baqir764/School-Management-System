<?php

namespace App\Http\Controllers\Academics;

use App\Http\Requests\Academics\StoreClassRequest;
use App\Http\Requests\Academics\UpdateClassRequest;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\ExamSubject;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Mark;
use App\Models\Shift;
use App\Models\TimetableEntry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    public function index(): View
    {
        return view('classes.index', [
            'classes' => ClassRoom::query()
                ->withCount(['sections', 'subjects', 'enrollments', 'feeStructures', 'exams'])
                ->with('shift')
                ->ordered()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('classes.create', ['shifts' => Shift::query()->ordered()->get()]);
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        $class = ClassRoom::create($request->validated());

        AuditLogger::log('create', 'classes', $class->id, null, $class->only(['name', 'numeric_level']));

        return redirect()->route('classes.index')->with('status', __('app.class_created'));
    }

    public function show(ClassRoom $class): View
    {
        return view('classes.show', [
            'class' => $class->load(['sections.classTeacher', 'subjects.teacher'])
                ->loadCount(['sections', 'enrollments', 'feeStructures', 'exams']),
        ]);
    }

    public function edit(ClassRoom $class): View
    {
        return view('classes.edit', ['class' => $class, 'shifts' => Shift::query()->ordered()->get()]);
    }

    public function update(UpdateClassRequest $request, ClassRoom $class): RedirectResponse
    {
        $original = $class->only(['name', 'numeric_level', 'is_active']);

        $class->update($request->validated());

        AuditLogger::log('update', 'classes', $class->id, $original, $class->only(['name', 'numeric_level', 'is_active']));

        return redirect()->route('classes.index')->with('status', __('app.class_updated'));
    }

    public function destroy(ClassRoom $class): RedirectResponse
    {
        // Classes no longer soft-delete (see the drop_soft_deletes
        // migration), so delete() below is permanent. fee_structures
        // cascade-deletes on class_id - and cascades further into
        // fee_invoices and then fee_payments - which was previously
        // unchecked here because a soft-deleted class never actually
        // triggered that cascade. Now that it does, deleting a class that
        // still has a fee structure defined would silently wipe out real
        // invoice/payment history, so it's blocked the same way sections
        // and enrollments already were.
        abort_if(
            $class->sections()->exists()
                || $class->enrollments()->exists()
                || $class->feeStructures()->exists()
                || $class->exams()->exists(),
            403,
            __('app.cannot_delete_class_in_use')
        );

        $class->delete();

        AuditLogger::log('delete', 'classes', $class->id);

        return redirect()->route('classes.index')->with('status', __('app.class_deleted'));
    }

    /**
     * FULL force (permanent) delete: removes the class together with
     * EVERYTHING under it - sections (+ their timetable entries and
     * attendance records), enrollments, exams (+ exam subjects + marks)
     * and fee structures (+ invoices + payments) - all via the FK
     * cascades in a single transaction. Subjects are nullOnDelete: they
     * survive, unlinked from any class. A complete snapshot of the wiped
     * counts is kept in the audit log, since this cannot be undone.
     */
    public function forceDestroy(ClassRoom $class): RedirectResponse
    {
        $classId = $class->id;

        $sectionIds = $class->sections()->pluck('id');
        $examIds = $class->exams()->pluck('id');
        $examSubjectIds = ExamSubject::whereIn('exam_id', $examIds)->pluck('id');
        $feeStructureIds = $class->feeStructures()->pluck('id');
        $feeInvoiceIds = FeeInvoice::whereIn('fee_structure_id', $feeStructureIds)->pluck('id');

        $affected = [
            'name' => $class->name,
            'numeric_level' => $class->numeric_level,
            'sections' => $sectionIds->count(),
            'enrollments' => $class->enrollments()->count(),
            'exams' => $examIds->count(),
            'exam_subjects' => $examSubjectIds->count(),
            'marks' => Mark::whereIn('exam_subject_id', $examSubjectIds)->count(),
            'fee_structures' => $feeStructureIds->count(),
            'fee_invoices' => $feeInvoiceIds->count(),
            'fee_payments' => FeePayment::whereIn('fee_invoice_id', $feeInvoiceIds)->count(),
            'timetable_entries' => TimetableEntry::whereIn('section_id', $sectionIds)->count(),
            'attendance_records' => AttendanceRecord::whereIn('section_id', $sectionIds)->count(),
            'subjects_unlinked' => $class->subjects()->count(),
        ];

        DB::transaction(function () use ($class) {
            $class->delete();
        });

        AuditLogger::log('force_delete', 'classes', $classId, $affected);

        return redirect()->route('classes.index')->with('status', __('app.class_force_deleted'));
    }
}
