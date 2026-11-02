<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\AccessibleSections;
use App\Services\AttendanceRowImporter;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Token API for the offline attendance APK (see C:\SchoolManagementAPK).
 *
 * The APK records attendance with no internet, then pushes the batch here
 * with a "Send" button. Auth is Sanctum personal tokens (NOT web sessions):
 * POST /api/login issues one, POST /api/attendance/sync consumes it.
 * Every row goes through AttendanceRowImporter - the exact same rules as
 * the web Excel import - and the marking user is scoped to their own
 * accessible sections just like on the web.
 */
class AttendanceApiController extends Controller
{
    /**
     * Exchange server-account credentials for a sync token. Only active
     * accounts holding attendance.mark (clerks, admins, homeroom teachers)
     * get one - everyone else gets 401/403 with no token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        if (! $user->can('attendance.mark')) {
            return response()->json(['message' => 'Account may not mark attendance.'], 403);
        }

        $token = $user
            ->createToken($data['device_name'] ?? 'attendance-apk', ['attendance:sync'])
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->primaryRoleLabel(),
        ]);
    }

    /**
     * Store one batch of offline records. Always answers 200 with per-row
     * results (the APK clears only the rows the server accepted); 422 is
     * reserved for a malformed payload, 401/403 for bad tokens or a user
     * who lost access since logging in.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan('attendance:sync') || ! $user->isActive() || ! $user->can('attendance.mark')) {
            return response()->json(['message' => 'Not authorized to sync attendance.'], 403);
        }

        $data = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.admission_number' => ['required', 'string', 'max:50'],
            'records.*.date' => ['required', 'string', 'max:20'],
            'records.*.status' => ['required', 'string', 'max:50'],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $importer = new AttendanceRowImporter(
            $user,
            AccessibleSections::for($user)->pluck('id'),
            AcademicYear::current()
        );

        $imported = 0;
        $errors = [];

        foreach ($data['records'] as $index => $row) {
            $result = $importer->process(
                $row['admission_number'],
                $row['date'],
                $row['status'],
                $row['remarks'] ?? null
            );

            if ($result['ok']) {
                $imported++;

                continue;
            }

            if ($result['code'] === AttendanceRowImporter::EMPTY) {
                continue;
            }

            // Both a machine-readable code (the APK maps it to its own
            // fa/ps/en strings) and a server-side default message.
            $errors[] = [
                'index' => $index,
                'admission_number' => $row['admission_number'],
                'code' => $result['code'],
                'message' => $this->errorMessage($result['code']),
            ];
        }

        AuditLogger::log('api-sync', 'attendance_records', null, null, [
            'imported' => $imported,
            'errors' => count($errors),
        ]);

        return response()->json([
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * One-login bridge for the APK's Live tab: the app is already
     * token-authenticated, but the live site (full-window navigation) runs
     * on cookie sessions. This mints a single-use, 2-minute nonce URL -
     * the app navigates to it instead of the plain site root and lands
     * logged in, with no second login screen. The token itself never
     * appears in any URL or log; only the disposable nonce does.
     */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan('attendance:sync') || ! $user->isActive() || ! $user->can('attendance.mark')) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $nonce = Str::random(40);
        Cache::put('sso:'.$nonce, $user->id, 120);

        return response()->json(['url' => url('/auth/token/'.$nonce)]);
    }

    private function errorMessage(string $code): string
    {
        return match ($code) {
            AttendanceRowImporter::STUDENT_NOT_FOUND => 'Student not found.',
            AttendanceRowImporter::NOT_ENROLLED => 'Student has no active enrollment this year.',
            AttendanceRowImporter::FORBIDDEN_SECTION => 'Student is not in your section.',
            AttendanceRowImporter::INVALID_DATE => 'Invalid Jalali date (YYYY-MM-DD).',
            AttendanceRowImporter::FUTURE_DATE => 'Date is in the future.',
            AttendanceRowImporter::INVALID_TIME => 'Invalid time (HH:MM).',
            AttendanceRowImporter::TIME_OUT_BEFORE_IN => 'Time out must be after time in.',
            default => 'Invalid status (present, absent, late, excused).',
        };
    }
}
