# School Management System (AAIWO)

A production-grade School Management System built on Laravel, Blade and
Bootstrap 5. See `INSTALL.md` for setup instructions and `PROGRESS.md` for
what is implemented so far and what's coming in the next phase.

## Phase 1 (this delivery)

- Laravel 11 application skeleton
- MySQL database configuration for XAMPP
- Authentication (login, logout, forgot/reset password, forced password
  change for admin-created accounts)
- Roles & permissions (Super Administrator, School Administrator,
  Principal, Teacher, Accountant, Librarian, Storekeeper, Receptionist,
  Parent, Student) via `spatie/laravel-permission`
- Responsive sidebar/topbar layout with breadcrumbs, toasts and a
  language switcher (English / Dari / Pashto, RTL-aware)
- Database-driven dashboard (student/teacher/attendance/fee cards are
  wired up and will populate automatically once Phase 2 adds their
  tables)
- School settings module (name, logo, address, contact info, currency,
  timezone, default language)
- User management (create, edit, disable/enable, reset password, assign
  roles)
- Audit log of security-relevant actions
- Feature tests for authentication and permissions

## Stack

Laravel 11, PHP 8.2+, MySQL 5.7+/MariaDB, Blade, Bootstrap 5, DataTables,
Chart.js, Vite.
