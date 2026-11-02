# Build Progress

Tracking against the master specification's own Phase 1–7 sequence.

## Phase 1 — DONE (this delivery)

- [x] Laravel 11 setup, MySQL config for XAMPP, UTF-8/utf8mb4
- [x] Authentication: login, logout, forgot/reset password, rate limiting,
      forced password change for admin-created/reset accounts
- [x] Roles & permissions: 10 roles seeded via `spatie/laravel-permission`,
      permission table grows module-by-module in later phases
- [x] Layout: sidebar, topbar, breadcrumbs, toasts, confirm-on-destructive
      forms, 404/403/419/500/503 error pages
- [x] Dashboard: every card is a real database query (guarded so it
      doesn't error before Phase 2's tables exist — cards read 0 until
      then, no code change needed when those tables arrive)
- [x] School settings (name, logo, address, contact info, currency,
      timezone, default language)
- [x] User management: create/edit/disable/enable/reset password/assign
      roles, searchable + paginated list
- [x] Audit log (security-relevant actions so far: login, logout, password
      changes/resets, settings updates, user create/update/disable/delete)
- [x] English, Dari and Pashto translations with RTL layout switching
- [x] Feature tests: authentication, permissions, user management

## Phase 2 — DONE

Academic years, classes, sections, subjects, teachers, students (incl.
documents), parents/guardians, enrollment. Dashboard student/teacher/class
cards and the recent-admissions table are now live from real data.

## Phase 3 — DONE

Timetable (shared periods + per-section weekly grid, Sat–Thu display),
daily attendance (per-section roster, teachers scoped to their own
homeroom), exams (per class/year, subjects attached with their own
full/pass marks, publish toggle), grade scale (letter-grade bands), and
marks entry (per exam-subject, teachers scoped to their own subject) with
a per-student results summary. Dashboard's present/absent/late-today and
upcoming-exams cards are now live.

## Phase 4 — DONE

Fee structure setup: fee heads (e.g. Tuition, Admission, Transport) per
academic year, optionally scoped to one class or school-wide, with
amount and frequency (one-time/monthly/term/annual). Fee invoices are
generated in bulk from a fee structure for every actively-enrolled
student in scope (amount snapshotted at generation time, re-running
generation skips students who already have one), with a filterable
invoice list, per-invoice payment recording with auto-numbered printable
receipts, and a per-student outstanding-dues report. Balance/status are
always derived live from payments, never stored. Permissions split
`fees.manage` (structures + bulk generation) from the narrower
`fees.collect` (record a payment, print its receipt) so front-desk staff
can take payments without redefining fees. Dashboard's pending-fees and
today's-payments cards are now live from real data.

## Phase 5 — DONE

Employees (non-teaching staff: accountant, guard, cleaner, receptionist,
etc.) as a basic HR record — name, position, phone, national ID, monthly
salary, joining date, status — auto-numbered "STF-####" like Teacher's
"TCH-####". Payroll: a fixed monthly salary on both Teacher and Employee
profiles, with "Pay Salary" submitting one salary payment per person per
calendar month (a polymorphic `payee` — Teacher or Employee — via a morph
map registered in `AppServiceProvider`; re-paying the same person for a
month they've already been paid is rejected, not stacked). Each payment
gets an auto-numbered printable voucher ("SAL-YYYY-####"). Permissions
split `payroll.manage` (employee records + salary amounts) from
`payroll.pay` (submit a payment) and `payroll.view` (read-only, including
Principal oversight) — Accountant and School Administrator hold all
three; Receptionist holds none, since payroll is back-office, unlike the
front-desk fee-collection role it already has. A new Finance Overview
page shows total fee income vs. total salary expense and the net balance
for a selected calendar month, with a peek at the underlying
transactions. Dashboard's "Total Staff" card is now live (previously
stubbed at 0 pending this phase).

## Shamsi (Afghan Solar Hijri) calendar conversion — IN PROGRESS

A system-wide change requested after Phase 5: every date in the system
moves from the Gregorian calendar to the Shamsi calendar (Hamal through
Hoot), stored as Shamsi in the database, regardless of interface
language. Built and delivered module-by-module, in the same order as the
original phases, so each batch is small enough to verify on its own:

- [x] **Core infrastructure** (this delivery): `App\Support\JalaliDate` -
      a dependency-free Gregorian<->Jalali converter (the same
      astronomical "jalaali" algorithm behind jalaali-js/moment-jalaali),
      verified against known reference dates and an exhaustive
      multi-year round-trip check. Every converted date column stores a
      plain zero-padded "YYYY-MM-DD" Jalali string (no Carbon/'date'
      cast) - see the doc comment on any converted model's `casts()`.
      The `<x-jalali-date>` Blade component renders the three
      day/month/year dropdowns (month shown by its Dari name) used on
      every form with a Shamsi date field, and the
      `App\Http\Requests\Concerns\ValidatesJalaliDates` trait is the
      shared FormRequest-side validation/parsing pattern every
      converted request follows.
- [x] **Students, Teachers, Academic Years, Enrollment** (this delivery):
      date_of_birth/admission_date (students), date_of_birth/joining_date
      (teachers), start_date/end_date (academic years), enrollment_date
      (enrollments). Admission numbers ("STU-####-0001") now number by
      Shamsi year. A migration converts every existing stored Gregorian
      date to its Shamsi equivalent in place - **back up the database
      before running it**.
- [x] **Attendance, Exams** (this delivery): attendance_records.date,
      exams.start_date/end_date, exam_subjects.exam_date. Attendance's
      date filter and the exam start/end dates now use the
      `<x-jalali-date>` dropdowns; attendance can no longer be marked for
      a future Shamsi date, and an exam's end date can no longer be
      before its start date. A migration converts every existing stored
      Gregorian date to its Shamsi equivalent in place - **back up the
      database before running it**.
- [x] **Fee Invoices & Payments** (this delivery): fee_invoices.due_date,
      fee_payments.payment_date. Receipt numbers ("RCPT-####-0007") now
      number by Shamsi year, matching admission numbers. Payment date can
      no longer be set in the future. The Finance Overview page's
      month/year filter stays calendar-based for now (it still compares
      against Payroll, which isn't converted yet) but now converts that
      window to Jalali internally so fee-income totals stay correct. A
      migration converts every existing stored Gregorian date to its
      Shamsi equivalent in place - **back up the database before running
      it**.
- [x] **Employees & Payroll** (this delivery): employees.joining_date,
      salary_payments.payment_date, and salary_payments.salary_month -
      the last one is now a genuine month-only Jalali "YYYY-MM" string
      (no day component, since a Jalali month doesn't line up with a
      Gregorian one) with its own two-part month-name/year picker instead
      of the shared three-part `<x-jalali-date>` component. Voucher
      numbers ("SAL-####-0007") now number by Shamsi year, matching fee
      receipts and admission numbers. The payroll register and Finance
      Overview pages are now fully Jalali (month name + year, no more
      Gregorian calendar-month filter anywhere in the system). A
      migration converts every existing stored Gregorian date to its
      Shamsi equivalent in place - **back up the database before running
      it**.

Every date already built anywhere in Phases 1-5 is now Shamsi. Only the
still-unbuilt Library module (below) remains, and it will be built
directly on Shamsi dates from the start rather than converted afterward.
- [ ] Library module (still not started - will be built directly on
      Shamsi dates rather than converted afterward)

## Shifts (multi-session start times) — DONE

A school that runs classes across more than one daily session (e.g. Grade
1-4 in the morning, Grade 5-6 in the afternoon) needed each session judged
against its own start time, not the single school-wide clock Phase
5/Shamsi work had just added. New master data module: `Shift` (name +
start time + active flag), managed under Academics alongside
Classes/Sections/Subjects (`shifts.*` routes, `academics.manage`/`.view`
permissions). A shift is assigned to a Class - every section of that class
inherits it for student attendance - and, since staff aren't tied to a
single class, directly to each Teacher and Employee. `AttendanceController`
and `StaffAttendanceController` now resolve "late" against
`class → shift` / `person → shift`, falling back to the existing
`Setting::school_start_time` for anyone/anything with no shift assigned,
so nothing already set up needed to change. Covers every place a "late"
status can be produced: the daily roster (student + staff, both now take
an optional Time In per row), the QR scan page (student and staff cards
alike, which now also stamps and judges a staff scan's check-in time -
previously staff scans only ever recorded "present"). Spreadsheet imports
and the offline APK sync are unaffected (they carry no time-in, so status
is still taken exactly as given). A migration adds `shift_id` (nullable,
`SET NULL` on delete) to `classes`, `teachers`, and `employees`, and
`time_in`/`time_out` to `staff_attendance_records` (mirroring the columns
`attendance_records` already had) - no backfill needed since every column
is nullable and behaviour is unchanged until a shift is actually created
and assigned.

The daily roster (student and staff) also flips the Status buttons live,
in the browser, the moment a Time In is typed - present or late, judged
against that same class/person shift start time - instead of only finding
out after the form is saved. This is a display convenience only; the
authoritative check is still `resolveStatus()`/`expectedStartTime()` on
the server, re-applied on save regardless of what the buttons show, so a
JavaScript-disabled browser or a tampered request still gets the correct
status.

## Finance: non-salary expenses, misc income, extended reporting & backup — DONE

Non-salary expenses (مصارف متفرقه) and miscellaneous non-fee income
(دریافت‌های متفرقه): two new category lookup tables (Expense Category /
Income Category — master data modeled directly on Shift: name +
description + active flag, no soft deletes, in-use delete guard) plus the
transactions themselves (Expense / MiscIncome — mirror
SalaryPayment/FeePayment: SoftDeletes, no edit/delete route, a correction
means a fresh separate entry, auto-numbered printable voucher/receipt —
"EXP-YYYY-####" / "MISC-YYYY-####"). New `finance` permission group
(`finance.manage` / `finance.record` / `finance.view`) mirrors the
existing `fees.*`/`payroll.*` three-way split; granted in full to Super
Administrator, School Administrator and Accountant, view-only to
Principal.

The Finance Overview page (previously fee income vs. salary expense only)
now folds both new tables into its existing month/year totals — income
and expense each split into their two sources, plus two more "recent
transactions" tables — rather than a separate report page, per how this
was scoped.

One-click manual backup: a single ZIP download (Settings page,
`settings.manage`) containing a full database dump (hand-rolled over the
raw PDO connection — `SHOW CREATE TABLE` + batched `INSERT`s — no new
Composer dependency and no reliance on the `mysqldump` binary being on
PATH) plus every uploaded-file directory (`storage/app/public`,
`storage/app/secure_documents`). Deliberately manual, one download at a
time — no scheduled/automatic backup.

**Danger Zone (Restore Backup / Reset Database) — DONE.** Two further
irreversible actions, added on the Settings page below the backup
download, gated by a new `settings.restore` permission that — unlike
`settings.manage` — only Super Administrator holds (School Administrator
keeps everyday settings access but not these). Both require typing an
exact confirmation word ("RESTORE"/"RESET") plus the acting user's
current password (`current_password` validation rule), on top of the
permission check.

- *Restore Backup*: uploads a ZIP previously produced by Download Backup
  and replaces the current database and uploaded files with its contents
  - the exact reverse of the backup. The dump is executed with
  `mysqli::multi_query()` over its own dedicated connection rather than
  splitting the SQL text on `;` (a quoted value in the dump can itself
  contain a literal `;\n`, so a naive string-split could cut a statement
  in half; MySQL's own parser doesn't have that problem). Files are
  overlaid onto the existing `storage/app/public`/`storage/app/secure_documents`
  - a file that exists on disk but isn't in the backup is left alone, not
    deleted.
- *Reset Database*: wipes every school-data table (students, attendance,
  exams, fees, payroll, expenses, income, timetable, announcements, …)
  back to empty, for handing the system to a new school or a new academic
  cycle — while deliberately keeping user accounts, roles/permissions,
  school settings and the audit log untouched, so nobody is locked out
  and the reset itself still lands in the audit trail. Implemented as a
  short *keep*-list (`BackupController::PROTECTED_TABLES`) rather than a
  long *wipe*-list of every school table, so a future migration's new
  table is wiped by default — the safer failure mode.

## Performance & offline pass — DONE (2026-11-02)

Full inspection + speed/offline optimization so the app runs fast on any
laptop with no internet at runtime. Details in `PERFORMANCE.md`.

- **Offline by construction**: all CSS/JS/fonts are local (Vite build in
  `public/build/` is committed; QR libs in `public/vendor/`). No CDN,
  Google Fonts, or external API anywhere — verified by scanning views.
- **DB indexes**: new migration adds indexes on all hot non-FK columns
  (attendance date, payment dates, due_date, salary_month, exam
  start_date, status/gender/is_active flags, notifications.read_at, ...).
- **Fewer queries per page**: dashboard card counts collapsed into 2
  aggregate queries (was ~15 COUNTs); users-by-role is one GROUP BY on the
  permission pivots (was: load every user); unused recentAdmissions/
  recentPayments/recentAnnouncements dashboard queries removed; topbar's
  per-page `Schema::hasTable()` replaced by the per-request-memoized
  `App\Support\SchemaInfo`; `Setting::current()` memoized per request
  (container-scoped, so tests stay correct) on top of its cache entry.
- **route:cache unblocked**: the 3 route closures (`/storage/{path}`,
  `/auth/token/{nonce}`, `/migrate-once-9f3k2`) moved into invokable
  controllers (`StorageFileController`, `TokenLoginController`,
  `MigrateOnceController`) — closures break `php artisan route:cache`.
  `optimize.bat` / `optimize.sh` now run migrate + optimize in one step.
- **Smaller pages**: Chart.js (~200 KB) no longer ships in the global
  bundle — it is a dashboard-only entry (`resources/js/dashboard.js`)
  that lazy-loads via dynamic import; unused axios bundle removed;
  `.htaccess` now gzip-compresses text and sets immutable 1-year cache
  headers on hashed build assets (all `IfModule`-guarded).
- **`.env.example`** now recommends `file` session/cache drivers (no extra
  DB tables, fastest on a laptop) and `APP_DEBUG=false` for production.

## Phase 6–7 — not started

Library, inventory, notifications, report cards, promotion, a general
cross-module reports module (the Finance-specific reporting above is
done — see the section just above), import/export, audit log UI, and the
final security/performance/testing pass — in that order, per the master
specification.

## Known follow-ups worth doing early in Phase 2

- Add `barryvdh/laravel-dompdf` and `maatwebsite/excel` to `composer.json`
  once report cards (Phase 3) and import/export (Phase 6) actually need
  them — left out of Phase 1 on purpose to keep this delivery's dependency
  list minimal.
- Consider a request-scoped memoized wrapper around `Setting::current()`
  if profiling ever shows it running more than once per request.
