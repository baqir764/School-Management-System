# Stage 1 implementation: local offline database foundation

Implemented in the existing Capacitor project at `D:\All software\Software\Android APK\SchoolManagementAPK`.

## Implemented

`www/js/offline-db.js` now creates an IndexedDB database named `school_management_offline` at schema version 1. It defines stores for authentication, roles, permissions, academic master data, students, teachers, parents, enrollments, timetable, student/staff attendance, exams, marks, fees, payroll, finance, announcements, notifications, audit logs, roster data, and local files.

The database module exposes asynchronous helpers for initialization, reads, writes, bulk writes, deletes, clears, attendance mirroring, and full database export. It also migrates the existing attendance records, roster, and language setting from the old `localStorage` keys once.

`www/js/store.js` remains compatible with the current attendance screen while mirroring attendance records and roster entries into IndexedDB. Language changes are also saved into the new settings store.

`www/index.html` loads the database foundation before the compatibility store and application code.

## Verification

The schema/file checks passed and confirmed that the new module is loaded and includes the core stores. The source web files were updated successfully.

The Android packaged assets could not be overwritten from this process because the existing `android/app/src/main/assets/public` files are access-protected on drive D:. The next Android sync/build must copy the updated `www` files into those packaged assets using a process with write access, then run the Gradle wrapper.

The existing debug APK has not been replaced by this Stage 1 change. It remains the previously built attendance APK until the asset sync and Android build are completed.
