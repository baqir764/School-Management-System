# Stage 2 implementation: offline UI and navigation

Implemented in `D:\All software\Software\Android APK\SchoolManagementAPK\www`.

The attendance-only shell now has an offline school-management navigation layer with Dashboard, Students, Teachers & Staff, Academics, Attendance, Exams & Marks, Fees & Payments, Finance & Payroll, and Settings screens.

The dashboard shows local counts for students, teachers, attendance records, and fee invoices. It also displays the local database status and quick actions. The Students screen reads local student records from IndexedDB and supports local search. The Fees screen displays local invoice and payment counts. Other module screens now have reusable feature cards and clear empty states ready for the next implementation stages.

A reusable navigation controller was added in `www/js/stage2-ui.js`. It manages screen switching, drawer closing, active navigation state, dashboard rendering, local list rendering, and offline action feedback. The legacy attendance controller hooks were preserved invisibly so QR scanning, attendance recording, and Excel export continue to initialize correctly.

The Live Site entry and server setting are no longer user-facing. A hidden compatibility field remains only so the legacy attendance JavaScript can initialize while the rest of the offline rewrite proceeds.

The updated UI source files are:

- `www/index.html`
- `www/js/stage2-ui.js`
- `www/css/app.css`

The existing packaged Android asset directory on drive D is access-protected, so the APK has not been rebuilt in this stage. The updated source web files must be synchronized into `android/app/src/main/assets/public` before the next Gradle build.
