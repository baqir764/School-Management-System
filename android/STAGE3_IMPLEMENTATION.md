# Stage 3 implementation: core offline management modules

Implemented in `D:\All software\Software\Android APK\SchoolManagementAPK\www`.

The core management screens now use the Stage 1 IndexedDB database instead of placeholder cards.

## Students

The Students module now supports local student creation, editing, deletion, search, and record counts. It stores admission number, full name, phone, gender, and other local profile fields. Duplicate admission numbers are rejected.

## Teachers

The Teachers & Staff module now supports local teacher creation, editing, deletion, search, and record counts. It stores teacher code, name, subject, and phone. Duplicate teacher codes are rejected.

## Academics

The Academics module now provides local tabs for academic years, classes, sections, and subjects. Records can be created and deleted locally and are stored in their corresponding IndexedDB stores.

## Exams and marks

The Exams module now supports local exam creation and deletion. Each exam can open a marks-entry form that selects a student and validates marks against full marks before saving to the local marks store.

## Fees and payments

The Fees & Payments module now supports local invoice creation and deletion, local payment recording, invoice counts, payment counts, student selection, amount validation, and Jalali date fields.

## Source files

- `www/js/stage3-common.js` — shared local CRUD helpers, forms, validation, deletion, and feedback
- `www/js/stage3-ui.js` — Students, Teachers, Academics, Exams, Marks, Fees, Invoices, and Payments UI logic
- `www/index.html` — loads the Stage 3 modules
- `www/css/app.css` — Stage 3 form, tab, list, and action styles

The source checks confirmed all Stage 3 files and script references are present. The APK has not been rebuilt because the packaged Android assets on drive D remain access-protected; the updated `www` source must be synchronized into `android/app/src/main/assets/public` before a new APK is generated.
