<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get(
        '/attendance-records',
        [AttendanceRecordController::class, 'index']
    );

    Route::get(
        '/attendance-records/{attendanceRecord}',
        [AttendanceRecordController::class, 'show']
    );

    Route::middleware('auth:sanctum')->group(function () {
        Route::post(
            '/attendance-records',
            [AttendanceRecordController::class, 'store']
        );

        Route::match(
            ['put', 'patch'],
            '/attendance-records/{attendanceRecord}',
            [AttendanceRecordController::class, 'update']
        );

        Route::delete(
            '/attendance-records/{attendanceRecord}',
            [AttendanceRecordController::class, 'destroy']
        );
    });
});