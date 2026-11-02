# Laravel-derived offline Android specification

**Source:** `C:\xampp\htdocs\School-Management-System`  
**Analysis date:** 27 October 2026  
**Purpose:** Authoritative input for rewriting the Laravel application as a genuinely offline Android application.

This document is derived from the Laravel migrations, Eloquent models, Form Requests, seeders, service-provider configuration, and web/API routes. It is not inferred from the earlier prototype APK. The migrations and controllers remain the source of truth if this specification conflicts with prototype code.

## 1. Architectural conclusions

Laravel/PHP sessions, Blade pages, MySQL constraints, Spatie permission middleware, storage disks, queued notifications, and Sanctum cannot be copied into an APK. Android must implement equivalent local behavior using a local relational database, a local authentication/session layer, local file storage, and local authorization checks.

Use a relational Android database with foreign keys and migrations. Room/SQLite is the recommended target because the source has many foreign keys, uniqueness rules, polymorphic records, transactional invariants, and derived financial values. Keep the source's Jalali values as validated text fields rather than converting them to Gregorian dates.

Use a repository/service layer so controllers' cross-record rules are not hidden inside screens. Every mutation should run a transaction, write an audit event where the Laravel app does, and enforce the same permission and ownership scope locally.

## 2. Canonical data model

The following tables/entities are required. `id`, timestamps, and soft-delete behavior are included where the source migration uses them.

### Identity, authorization, and school setup

- **users:** `name`, unique `email`, `phone`, `password`, `is_active`, `must_change_password`, `preferred_locale`, `email_verified_at`, `last_login_at`, `last_login_ip`, remember token, timestamps, soft delete. A disabled account cannot authenticate; historical records remain.
- **roles:** unique role name and guard.
- **permissions:** unique permission name and guard.
- **model_has_roles / model_has_permissions / role_has_permissions:** Spatie polymorphic role and permission pivots. Android may normalize these as `user_roles`, `user_permissions`, and `role_permissions`, but must preserve direct-user permissions and role-derived permissions.
- **settings:** single logical row containing school name, logo path, address, province, district, phone, email, website, principal name, registration number, currency default `AFN`, timezone default `Asia/Kabul`, default locale default `en`, and optional `school_start_time` (`HH:MM`). The app enforces one current settings row.
- **audit_logs:** optional `user_id`, snapshot `user_name`, `action`, `module`, `record_id`, IP when applicable, JSON old/new values, and timestamp. Append-only for security, academic, and financial actions.
- **notifications:** UUID, notifiable type/id, notification type, JSON/text data, read timestamp, timestamps.
- **password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, personal_access_tokens:** server/runtime concerns. Android only needs local session, password-recovery policy, and queued local notification state; it does not need Laravel queue/cache tables.

### Academic structure and people

- **academic_years:** unique `name`, Shamsi `start_date` and `end_date`, `is_active`, remarks, timestamps, soft delete. Exactly one active year is allowed by service logic.
- **shifts:** `name`, `start_time`, `is_active`, timestamps. A shift supplies the expected arrival time.
- **classes:** `name`, unique `numeric_level` 0–100, optional `shift_id`, description, `is_active`, timestamps. The model maps this table to `ClassRoom`; it is a grade definition, not a section.
- **teachers:** optional unique `user_id`, unique `employee_code`, names, father name, gender, Jalali date of birth, national ID, phone/email/address, qualification, specialization, Jalali joining date, employment type (`full_time`, `part_time`, `contract`), optional `shift_id`, optional monthly salary, status (`active`, `inactive`, `terminated`), photo path, timestamps, soft delete.
- **sections:** `class_id`, name unique within class, optional `class_teacher_id`, optional capacity 1–500, room number, timestamps. Class-level and section-level deletions are guarded when dependent data exists; these tables do not retain harmful soft-deleted duplicates after the later migrations.
- **subjects:** name, unique code, optional class, optional default teacher, `full_marks` 1–1000, `pass_marks` 0–full marks, `is_active`, timestamps, soft delete.
- **students:** optional unique `user_id`, unique admission number, names, father/grandfather names, gender, Jalali birth date, place of birth, national ID, nationality default `Afghan`, religion, blood group, address/province/district, phone/email, photo path, Jalali admission date, status (`active`, `inactive`, `graduated`, `transferred`, `expelled`), previous school, medical notes, timestamps, soft delete.
- **parents:** optional unique `user_id`, names, national ID, occupation, required phone, email, address, photo path, timestamps, soft delete.
- **parent_student:** unique parent/student pair, relationship (`Father`, `Mother`, `Guardian`, `Other`), `is_primary_contact`, timestamps.
- **enrollments:** student, academic year, class, section, optional roll number, Jalali enrollment date, status (`active`, `promoted`, `repeated`, `transferred_out`, `withdrawn`), remarks, timestamps, soft delete. Unique student/year; roll number unique within academic year/section when present.
- **student_documents:** student, document type (`birth_certificate`, `national_id`, `transfer_certificate`, `photo`, `other`), title, private local file path, original filename, MIME type, byte size, uploader, timestamps, soft delete.
- **employees:** unique `employee_code`, names, position, optional shift, phone, national ID, monthly salary, optional Jalali joining date, status (`active`, `inactive`, `terminated`), timestamps, soft delete.

### Timetable and attendance

- **periods:** name, `start_time`, `end_time`, sort order, timestamps, soft delete. Ordered by sort order then start time.
- **timetable_entries:** section, period, ISO weekday number 1–7, subject, teacher, timestamps, soft delete. Unique section/period/day slot.
- **attendance_records:** student, section, academic year, Jalali date, status (`present`, `absent`, `late`, `excused`), nullable time in/out (`HH:MM`), remarks, marker, timestamps, soft delete. Unique student/date; a student has one daily result regardless of periods.
- **staff_attendance_records:** polymorphic attendee (`teacher` or `employee`), Jalali date, status (`present`, `absent`, `late`, `excused`), nullable time in/out, marker, timestamps. No soft delete. Unique attendee type/id/date so rescanning updates or confirms the same day.

### Exams and grading

- **exams:** academic year, class, name, term type (`quiz`, `mid_term`, `final`, `other`), Jalali start/end dates, `is_published`, remarks, timestamps, soft delete.
- **exam_subjects:** exam, subject, optional Jalali exam date, full marks 1–1000, pass marks 0–full marks, timestamps. Unique exam/subject; no soft delete after later migration because a removed subject must be addable again and deletion is blocked when marks exist.
- **marks:** exam subject, student, nullable marks obtained with 2 decimals, `is_absent`, remarks, entry user, timestamps, soft delete. Unique exam subject/student. Null means not entered; absent is not the same as zero.
- **grade_scales:** grade name, min/max percentage, description, sort order, timestamps, soft delete. Bands must not overlap; grade lookup finds the band containing a percentage.

### Fees, payroll, and finance

- **fee_structures:** academic year, optional class, name, amount decimal(10,2), frequency (`one_time`, `monthly`, `term`, `annual`), description, `is_active`, timestamps, soft delete. Unique year/class/name. Null class means school-wide.
- **fee_invoices:** student, fee structure, academic year, amount snapshot, discount amount default zero, optional Jalali due date, timestamps, soft delete. Unique student/fee structure. Amount remains the historical snapshot even if the fee structure changes.
- **fee_payments:** invoice, amount decimal(10,2), Jalali payment date, method (`cash`, `bank_transfer`, `other`), unique receipt number, receiver, remarks, timestamps, soft delete.
- **salary_payments:** polymorphic payee (`teacher` or `employee`), amount, discount amount default zero, Jalali salary month, Jalali payment date, method, unique voucher number, payer, remarks, timestamps, soft delete. Unique payee type/id/salary month.
- **expense_categories / income_categories:** unique name, description, active flag, timestamps. Categories are not soft-deleted; deletion is restricted when records use them.
- **expenses:** category, title, amount, Jalali expense date, method (`cash`, `bank_transfer`, `other`), unique voucher number, payer, remarks, timestamps, soft delete.
- **misc_incomes:** income category, title, amount, Jalali received date, method, unique receipt number, receiver, remarks, timestamps, soft delete.

### Announcements

- **announcements:** title max 255, body max 5000, nullable JSON target roles, nullable JSON target user IDs, author, published flag, published timestamp, timestamps, soft delete. Empty audience means broadcast; parent senders are restricted to teachers of their own children.

## 3. Relationship and transaction invariants

1. Only one academic year may be active. Activating one deactivates all others in the same transaction.
2. A class is a grade; a section belongs to one class. A section may have one homeroom teacher and optional capacity/room.
3. Subjects may be class-specific or school-wide. A subject has one default teacher at most.
4. A student is not placed directly on the student row. Enrollment owns class/section/roll placement and preserves year-to-year history.
5. A student has at most one enrollment per academic year. A roll number cannot duplicate within year/section.
6. A parent may have many students and a student may have many parents. The pair is unique and stores relationship plus primary-contact flag.
7. A teacher may have a login, but teacher/staff records do not automatically require a login.
8. A daily student attendance key is student/date, not student/period. Staff attendance is attendee type/id/date.
9. A timetable slot is unique by section/period/day. A day is ISO 1 Monday through 7 Sunday, even if the school week is Saturday–Thursday.
10. An exam subject cannot be duplicated within an exam. A student has at most one mark per exam subject.
11. Marks are nullable until entered. An absent mark produces no percentage and no pass/fail result. Otherwise percentage is `marks_obtained / full_marks * 100`, rounded to two decimals.
12. Grade bands cannot overlap. A percentage with no matching band has no grade.
13. A fee invoice amount is a historical snapshot. Payable amount is `max(0, amount - discount_amount)`.
14. Fee balance is `payable_amount - sum(payments)`, rounded to two decimals. Status is `paid` when balance <= 0, `partial` when any payment exists, otherwise `unpaid`. Never cache balance/status.
15. Fee payment must be positive, cannot exceed live balance, and cannot be recorded against a paid invoice. Payment date cannot be in the future.
16. Salary net amount is `max(0, amount - discount_amount)`. A payee can have only one salary payment per Jalali month.
17. Money must be stored exactly as decimal strings or integer minor units in Android; do not use binary floating-point for persisted balances.
18. Receipt/voucher sequences must remain unique even after soft deletion. Prefixes are `STU-{year}-`, `TCH-`, `STF-`, `RCPT-{jalaliYear}-`, `SAL-{jalaliYear}-`, `EXP-{jalaliYear}-`, and `MISC-{jalaliYear}-`.
19. Destructive operations must preserve auditability. Restore/force-delete/reset is different from ordinary delete and must have a stronger permission.
20. A class/section/exam-subject/category deletion is guarded if dependent records would become inconsistent. Where the Laravel app changed from soft delete to permanent guarded delete, Android must reproduce the user-visible behavior rather than silently retain a conflicting tombstone.

## 4. Date, time, and locale rules

- Store school dates as validated Jalali/Afghan Solar Hijri strings: `YYYY-MM-DD`, zero-padded. This includes academic-year dates, student and teacher dates of birth, joining/admission/enrollment dates, attendance dates, exam dates, due/payment/expense/income dates, and payroll dates.
- Store payroll `salary_month` as Jalali `YYYY-MM`.
- Do not parse Jalali strings using Gregorian date libraries. Implement the Jalali calendar validator, including month lengths and leap-year rules.
- Compare valid zero-padded Jalali strings lexicographically for start/end and future-date checks.
- Attendance and payment dates cannot be in the future.
- Times use `HH:MM` in the UI and `HH:MM:SS` where the source database uses SQL TIME. A time-out requires a time-in and cannot be earlier than time-in.
- Late detection uses the person's assigned shift start time; if no shift exists, use `settings.school_start_time`; if no school start is configured, keep the manually selected status.
- Preserve the user's preferred locale and the configured school locale. The source supports English and localized resources including Persian/Dari-style text; Android must support RTL layout rather than merely translating strings.
- Default timezone is `Asia/Kabul`; currency is `AFN`.

## 5. Authentication and authorization matrix

Seeded roles are: Super Administrator, School Administrator, Principal, Teacher, Accountant, Librarian, Storekeeper, Receptionist, Attendance Clerk, Parent, and Student.

Permissions are:

- Core: `dashboard.view`
- Settings: `settings.manage`, `settings.restore`
- Users/audit: `users.manage`, `audit.view`
- Academics: `academics.view`, `academics.manage`
- Teachers/students/parents: each has `.view` and `.manage`
- Timetable: `.view`, `.manage`
- Attendance: `attendance.view`, `attendance.mark`
- Exams/marks: `exams.view`, `exams.manage`, `marks.view`, `marks.enter`
- Fees: `fees.view`, `fees.manage`, `fees.collect`
- Payroll: `payroll.view`, `payroll.manage`, `payroll.pay`
- Announcements: `announcements.view`, `announcements.manage`, `announcements.send`
- Finance: `finance.view`, `finance.manage`, `finance.record`

Important role scopes:

- Super Administrator: all permissions.
- School Administrator: all normal operational permissions, but not the separate irreversible restore permission.
- Principal: read-only oversight of academic/operational modules, plus announcement management.
- Accountant: fees, payroll, finance, and announcements; no academic editing.
- Receptionist: student/parent management, academic read, fee collection, and announcements.
- Attendance Clerk: attendance view/mark and announcements view.
- Teacher: timetable view, attendance view/mark, exams view, marks view/enter, announcements view. The teacher is limited to their homeroom sections and own subjects.
- Librarian/Storekeeper: announcements view/manage.
- Parent: announcements view/send and exams view. Sending is only to teachers linked to their own children.
- Student: announcements view only.

Authentication must require valid credentials, reject disabled accounts, use a strong password policy equivalent to min 10 characters with mixed case, number, and symbol, throttle repeated failures (source allows five attempts before lockout), support forced password change, and store only a secure password hash. Android must not store plaintext passwords in IndexedDB.

## 6. Request-validation rules to preserve

- Required strings have source-specific maximum lengths: names generally 100–150, announcement title 255, announcement body 5000, remarks commonly 255–1000.
- Gender is `male` or `female`; employment and person statuses use the exact enums above.
- Academic year end must be after start. Exam end must not precede start.
- Class numeric level is integer 0–100 and unique. Section capacity is 1–500. Subject full marks is 1–1000 and pass marks cannot exceed full marks.
- Grade min/max are 0–100 and max >= min; bands cannot overlap.
- Fee/expense/income/payroll amounts are non-negative where configured; collected payment is at least 0.01 and cannot exceed invoice balance. Fee structure amount is capped at 9,999,999.99; payment has the same upper bound.
- File imports are XLS/XLSX and max 5 MB in the server app. Offline Android should validate file type, size, and schema before import.
- Student/teacher/parent/employee code and email uniqueness must account for existing and historical records as the source generators do.
- Attendance marking validates section/year/date/status arrays, section access, valid times, no future date, and time-out ordering.
- Teachers cannot mark an arbitrary section or enter marks for an arbitrary subject. Android must apply the same ownership checks before showing or saving the form.

## 7. Module workflow map

1. **Setup:** create settings, shifts, academic year, classes, sections, teachers, and subjects; activate exactly one academic year.
2. **Registration:** create student; generate admission number; attach parents; create enrollment for active academic year; upload private documents; optionally create a linked login.
3. **Daily operations:** view section roster; mark or scan student attendance; record times; derive late status; mark staff attendance; view history/export.
4. **Timetable:** define shared periods; assign subject/teacher to each section/day/period slot; reject collisions.
5. **Exams:** create exam; attach each subject once; define exam-specific full/pass marks; enter marks per enrolled student; calculate percentage/pass/grade; publish results.
6. **Fees:** define fee structure by year/class; generate one invoice per student/fee head; apply discount; collect installments; compute live balance/status; issue receipt; send reminders/notifications.
7. **Payroll:** maintain teachers/employees and salary; pay each payee once per month; apply discount; issue voucher.
8. **Finance:** maintain categories; record expense or miscellaneous income; issue voucher/receipt; show combined overview with fees/payroll.
9. **Communication:** publish role broadcasts; deliver notification inbox; allow parents to message only their children's teachers.
10. **Administration:** manage users/roles, settings, audit log, backup/restore/reset, locale, and destructive-action confirmations.

## 8. Backup and file-storage requirements

The web app's backup includes database data and public storage files; student documents live on a private secure disk and are served only through authorized controller routes. Android backup must include all local database tables, settings, role mappings, audit records, and private document bytes/metadata in an authenticated archive. Restore must validate schema version, checksum, file paths, and foreign-key consistency before replacing data. Reset and restore are irreversible and require the strongest local permission plus an explicit confirmation phrase.

Do not expose student documents through a public URL. Store them under app-private storage, encrypt the archive, and require the local permission check for viewing/downloading.

## 9. API and synchronization boundary

The existing API is intentionally small: login, attendance sync, and session creation via Sanctum. The Android target requested here is offline-only, so normal startup and all core workflows must not call this API. If synchronization is added later, treat it as a separate conflict-resolution project; do not silently mix local records with server IDs. Each local record should have a stable UUID and a sync state only if a later sync phase is explicitly approved.

## 10. Android implementation order derived from dependencies

1. Room schema and migration tests for the entities above.
2. Jalali date/time and exact-money utility tests.
3. Local authentication, roles, permission evaluator, and audit service.
4. Settings, academic years, shifts, classes, sections, teachers, subjects.
5. Students, parents, parent links, enrollments, documents.
6. Timetable and student/staff attendance with ownership rules.
7. Exams, exam subjects, marks, grade scales, result calculation.
8. Fee structures, invoice generation, discounts, payments, receipts.
9. Employees, salary payments, expense/income categories and transactions.
10. Announcements, local notification inbox, reports, backup/restore, localization, accessibility, and release hardening.

Each step needs repository tests for constraints and service tests for business rules before its screen is considered complete.

## 11. Direct evidence and important corrections

- The Laravel system contains more complete rules than the earlier APK prototype; the prototype's simple `parents`, `timetableEntries`, and `users` records do not match the normalized source relationships.
- The source contains parent/student linking, private documents, exact fee/payroll calculations, role-scoped access, Jalali validation, announcements audiences, backup/reset permission separation, and audit behavior that must be implemented rather than approximated.
- The source's dates are intentionally not Gregorian database dates. Converting them with ordinary Android/Gregorian date parsing would corrupt valid Jalali dates.
- The existing APK's asset-patching workaround is useful only for packaging web-layer experiments. It is not a substitute for implementing this relational model and its rules in a production Android application.
