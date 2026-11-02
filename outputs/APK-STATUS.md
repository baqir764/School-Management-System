# APK delivery status

The delivered APK is the existing Capacitor Android build found in `D:\All software\Software\Android APK\SchoolManagementAPK`.

- Package: `com.schoolmanagement.system`
- Version: `1.27` (version code `28`)
- Build type: debug APK
- Minimum Android version: API 24
- Target Android version: API 36
- Size: 4,838,118 bytes
- Verified with Android SDK build-tools 35.0.0 using `aapt2 dump badging`

## Current functionality

The app contains the offline attendance workflow, QR scanning, local storage, attendance Excel export, and a Live Site tab that opens the deployed Laravel site. It is not yet a complete offline replacement for every Laravel module.

The full offline native rewrite remains a phased project. Its implementation plan is in `android/OFFLINE_REWRITE_PLAN.md`.

The APK is a debug build and should not be distributed as a production release until it is signed with a release key and the offline rewrite scope is completed or explicitly accepted.
