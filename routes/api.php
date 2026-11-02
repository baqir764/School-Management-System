<?php

use App\Http\Controllers\Api\AttendanceApiController;
use Illuminate\Support\Facades\Route;

/*
 * Token API for the offline attendance APK (Sanctum personal tokens - the
 * web session/cookie auth in routes/web.php never applies here).
 */
Route::post('/login', [AttendanceApiController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/attendance/sync', [AttendanceApiController::class, 'sync'])->name('api.attendance.sync');
    Route::post('/session', [AttendanceApiController::class, 'session'])->name('api.session');
});
