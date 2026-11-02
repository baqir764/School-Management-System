<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Master spec section 3 (roles) and section 30 (roles & permissions).
 * Permissions are grouped by module; each later phase adds its own
 * module's permissions here without touching what already exists.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public const ROLES = [
        'Super Administrator',
        'School Administrator',
        'Principal',
        'Teacher',
        'Accountant',
        'Librarian',
        'Storekeeper',
        'Receptionist',
        'Attendance Clerk',
        'Parent',
        'Student',
    ];

    /**
     * Permissions implemented so far, grouped by the module that owns them.
     * Phase 2+ will append their own module.permission entries here.
     */
    public const PERMISSIONS = [
        'core' => [
            'dashboard.view',
        ],
        'settings' => [
            'settings.manage',
            // Deliberately separate from settings.manage: Reset Database
            // and Restore Backup are irreversible, whole-system actions
            // (see BackupController), so they are Super Administrator-only
            // - School Administrator keeps settings.manage but never gets
            // this one.
            'settings.restore',
        ],
        'users' => [
            'users.manage',
        ],
        'audit' => [
            'audit.view',
        ],
        // Phase 2: academic structure (years/classes/sections/subjects),
        // teachers, students (incl. documents/enrollment), and
        // parents/guardians.
        'academics' => [
            'academics.view',
            'academics.manage',
        ],
        'teachers' => [
            'teachers.view',
            'teachers.manage',
        ],
        'students' => [
            'students.view',
            'students.manage',
        ],
        'parents' => [
            'parents.view',
            'parents.manage',
        ],
        // Phase 3: timetable, daily attendance, exams and marks entry.
        'timetable' => [
            'timetable.view',
            'timetable.manage',
        ],
        'attendance' => [
            'attendance.view',
            'attendance.mark',
        ],
        'exams' => [
            'exams.view',
            'exams.manage',
        ],
        'marks' => [
            'marks.view',
            'marks.enter',
        ],
        // Phase 4: fee structure setup (what should be charged) plus
        // Phase 5: invoices (generated from a fee structure) and payments.
        // fees.manage covers structures + bulk invoice generation
        // (structural/administrative); fees.collect is the narrower,
        // day-to-day ability to record a payment and print its receipt,
        // so front-desk staff can take payments without being able to
        // redefine what's charged.
        'fees' => [
            'fees.view',
            'fees.manage',
            'fees.collect',
        ],
        // Phase 6: employee (non-teaching staff) HR records and payroll -
        // salary payments to both Teachers and Employees. payroll.manage
        // owns employee records and salary amounts; payroll.pay is the
        // narrower ability to submit one salary payment and print its
        // voucher; payroll.view covers read-only access to staff lists,
        // payroll history, and the Finance Overview page.
        'payroll' => [
            'payroll.view',
            'payroll.manage',
            'payroll.pay',
        ],
        // Announcements: managers/staff broadcast to parents/teachers/students
        // via database notifications (see AnnouncementNotification). view =
        // read own inbox; manage = create/edit/delete/publish; send = a
        // parent writing directly to their own children's teachers only
        // (audience forced server-side - see AnnouncementController).
        'announcements' => [
            'announcements.view',
            'announcements.manage',
            'announcements.send',
        ],
        // Phase 7: non-salary expenses (مصارف متفرقه) and miscellaneous
        // non-fee income (دریافت‌های متفرقه), plus the Finance Overview
        // page. finance.manage owns the two category lookup tables
        // (Expense/Income categories - master data, mirrors Shifts);
        // finance.record is the narrower day-to-day ability to log one
        // expense or receipt and print its voucher (mirrors fees.collect /
        // payroll.pay); finance.view covers read-only history for both
        // plus the Overview page.
        'finance' => [
            'finance.view',
            'finance.manage',
            'finance.record',
        ],
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
        }

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $superAdmin = Role::findByName('Super Administrator');
        $superAdmin->syncPermissions(Permission::all());

        $schoolAdmin = Role::findByName('School Administrator');
        $schoolAdmin->syncPermissions([
            'dashboard.view',
            'users.manage',
            'settings.manage',
            'audit.view',
            'academics.view',
            'academics.manage',
            'teachers.view',
            'teachers.manage',
            'students.view',
            'students.manage',
            'parents.view',
            'parents.manage',
            'timetable.view',
            'timetable.manage',
            'attendance.view',
            'attendance.mark',
            'exams.view',
            'exams.manage',
            'marks.view',
            'marks.enter',
            'fees.view',
            'fees.manage',
            'fees.collect',
            'payroll.view',
            'payroll.manage',
            'payroll.pay',
            'finance.view',
            'finance.manage',
            'finance.record',
            'announcements.view',
            'announcements.manage',
            'announcements.send',
        ]);

        // Every remaining role gets baseline dashboard access; each module's
        // own phase grants the module-specific permissions it needs
        // (e.g. Teacher gets attendance.mark once Phase 3 adds it).
        foreach (array_diff(self::ROLES, ['Super Administrator', 'School Administrator']) as $roleName) {
            Role::findByName($roleName)->syncPermissions(['dashboard.view']);
        }

        // Principal: read-only oversight of every academic/operational
        // module - sees the whole school's picture without being able to
        // change it.
        Role::findByName('Principal')->syncPermissions([
            'dashboard.view',
            'academics.view',
            'teachers.view',
            'students.view',
            'parents.view',
            'timetable.view',
            'attendance.view',
            'exams.view',
            'marks.view',
            'fees.view',
            'payroll.view',
            'finance.view',
            'announcements.view',
            'announcements.manage',
        ]);

        // Accountant: owns the whole fee lifecycle - defines fee
        // structures, generates invoices, and records/collects payments -
        // plus the whole payroll lifecycle: employee HR records, salary
        // amounts, and submitting salary payments to teachers/employees -
        // plus the whole non-salary finance lifecycle: expense/income
        // categories, and logging expenses and misc income.
        Role::findByName('Accountant')->syncPermissions([
            'dashboard.view',
            'fees.view',
            'fees.manage',
            'fees.collect',
            'payroll.view',
            'payroll.manage',
            'payroll.pay',
            'finance.view',
            'finance.manage',
            'finance.record',
            'announcements.view',
            'announcements.manage',
        ]);

        // Receptionist: front-desk staff handle admissions/registration -
        // full manage on students & parents, read-only on the academic
        // structure they enroll students into - plus they take walk-in
        // fee payments and print receipts (fees.collect) without being
        // able to define fee structures or bulk-generate invoices
        // (fees.manage stays Accountant/Admin-only).
        Role::findByName('Receptionist')->syncPermissions([
            'dashboard.view',
            'academics.view',
            'students.view',
            'students.manage',
            'parents.view',
            'parents.manage',
            'fees.view',
            'fees.collect',
            'announcements.view',
            'announcements.manage',
        ]);

        // Attendance Clerk: dedicated front-office staff who mark daily
        // attendance by scanning the QR code printed on student ID cards
        // (see attendance.scan). Read-only everywhere else.
        Role::findByName('Attendance Clerk')->syncPermissions([
            'dashboard.view',
            'attendance.view',
            'attendance.mark',
            'announcements.view',
        ]);

        // Teacher: day-to-day classroom work - mark attendance for their
        // own homeroom section(s) and enter marks for their own subject(s).
        // Controllers scope both down to "own" sections/subjects for this
        // role; School Administrator/Super Administrator (checked
        // separately) see and act on every section/subject regardless.
        // Exam *creation* and the timetable stay admin-only, so a teacher
        // gets timetable.view but not .manage, and exams.view but not
        // .manage.
        Role::findByName('Teacher')->syncPermissions([
            'timetable.view',
            'attendance.view',
            'attendance.mark',
            'exams.view',
            'marks.view',
            'marks.enter',
            'announcements.view',
        ]);

        // Librarian / Storekeeper: non-teaching staff broadcast like
        // managers (spec: "managers and staff can send and receive").
        foreach (['Librarian', 'Storekeeper'] as $staffRole) {
            Role::findByName($staffRole)->givePermissionTo(['announcements.view', 'announcements.manage']);
        }

        // Parent: inbox plus writing directly to their own children's
        // teachers (announcements.send - audience forced server-side).
        // Student: inbox only - receive broadcasts, never author.
        Role::findByName('Parent')->givePermissionTo([
            'announcements.view', 
            'exams.view',
            'announcements.send'
        ]);
        Role::findByName('Student')->givePermissionTo('announcements.view');

        Cache::forget('spatie.permission.cache');
    }
}
