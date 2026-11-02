# School Management System — Offline Android Rewrite

## Scope

This is a native Android rewrite of the existing Laravel school-management system. It is not a WebView wrapper and it must work without PHP, MySQL, or network access after installation.

The local app will use an on-device SQLite database, repository classes, and role/permission checks. A later synchronization layer can be added without making the core workflows depend on the server.

## Current source inventory

- 31 Laravel domain models
- 53 database migrations
- 44 controllers
- 10 roles and database-backed permissions
- English, Dari, and Pashto translations with RTL support
- Shamsi/Jalali dates used across the current school data
- Library module is not yet implemented in the source system

## Target Android architecture

- Kotlin Android application with Jetpack Compose UI
- Room over SQLite for local persistence
- A single local database with migration tests
- Repository/use-case layer between UI and persistence
- ViewModels exposing immutable screen state
- Android Keystore-backed local session protection
- App-level role and permission policy matching the Laravel permission names
- WorkManager reserved for optional future sync/export jobs; offline operation must not wait for it
- PDF/CSV export and Android document picker for backups

## Planned local modules

1. Authentication and local user/session management
2. Dashboard and notifications
3. Academic years, classes, sections, shifts, and subjects
4. Teachers and students
5. Parents/guardians and enrollments
6. Student documents and ID cards
7. Timetable and periods
8. Student and staff attendance, including QR scanning
9. Exams, exam subjects, grade scales, marks, and result summaries
10. Fee structures, invoices, payments, receipts, and dues
11. Employees, payroll, and salary vouchers
12. Finance: categories, expenses, miscellaneous income, and overview
13. Announcements and settings
14. Audit log and local backup/restore
15. Localization and RTL layouts

## Delivery phases

### Phase A — foundation

Create the Android project, database module, secure local session, localization shell, navigation, design system, and automated database migration tests.

### Phase B — identity and academic master data

Implement users/roles/permissions, settings, academic years, classes, sections, shifts, subjects, teachers, students, parents, and enrollments.

### Phase C — school operations

Implement timetable, attendance, staff attendance, exams, grade scales, marks, and result summaries. Add Jalali date utilities and validation tests before date-bearing screens.

### Phase D — money and HR

Implement fee structures, invoice generation, payments, receipts, employees, payroll, expenses, miscellaneous income, and finance reports.

### Phase E — documents and resilience

Implement file/document storage, ID-card and voucher exports, announcements, audit logs, encrypted backup/restore, and accessibility/RTL review.

### Phase F — release hardening

Run migration tests, permission-matrix tests, offline cold-start tests, backup/restore tests, screen-size tests, and signed release APK generation.

## Important data decisions

- Dates remain stored as Jalali `YYYY-MM-DD` strings to match the current project.
- Money is stored as integer minor units or exact decimal strings; floating-point arithmetic is not allowed for balances.
- Derived balances and dashboard totals are calculated from source records rather than duplicated mutable totals.
- Destructive operations require the same explicit confirmation and permission checks as the web system.
- The first offline release will use local accounts. Server account import/synchronization is a separate later phase and must not compromise offline startup.

## Build prerequisite status

The computer currently has Java 21 and PHP, but no usable Gradle, Android SDK command-line tools, `adb`, Kotlin compiler, or Flutter SDK. A secure download of the Android SDK command-line tools failed during TLS negotiation. Certificate verification must not be disabled for SDK installation.

Once Android Studio/SDK is installed through a working secure package path, set `ANDROID_HOME` to the SDK directory, install an Android platform and build-tools version, then generate and validate the signed APK from the Android project.
