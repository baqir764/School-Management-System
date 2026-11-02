# Offline APK workaround

A new debug APK was produced without Gradle and without downloading any dependencies.

## Method

The existing native Capacitor APK was unpacked locally. The updated Stage 1–5 web assets were copied into `assets/public`. Existing APK signature files were removed, the APK was rebuilt as a ZIP, aligned with Android SDK build-tools 35.0.0 `zipalign`, and signed locally with a generated debug keystore using `apksigner`.

## Validated output

- APK: `school-management-system-offline-stages1-5-debug.apk`
- Package: `com.schoolmanagement.system`
- Version: `1.27` / version code `28`
- Minimum Android version: API 24
- Target Android version: API 36
- Signature: valid v2 and v3 APK signature
- Contents verified: `offline-db.js`, `stage3-ui.js`, `stage4-ui.js`, `stage5-ui.js`, and updated `index.html`

## Limitation

This is an offline asset-patched APK, not a clean Gradle recompilation. Native Java/Kotlin code and native dependencies were not changed or recompiled. It is appropriate because the current stages change the Capacitor web application layer. Any future native Android source change requires a real Gradle build.
