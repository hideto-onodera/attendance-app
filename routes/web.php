<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', function () {
        return view('user.register');
    })->name('register');

    Route::post('/register', [RegisterController::class, 'store'])
        ->name('register.store');

    Route::get('/admin/login', function () {
        return view('admin.admin-login');
    })->name('admin.login');

    Route::post('/admin/login', [AdminLoginController::class, 'store'])
        ->name('admin.login.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance');

    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store');

    Route::get('/attendance/list', [AttendanceController::class, 'list'])
        ->name('attendance.list');

    Route::get('/attendance/report', [AttendanceController::class, 'report'])
        ->name('attendance.report');

    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])
        ->name('attendance.detail');

    Route::post('/attendance/{id}', [AttendanceController::class, 'requestCorrection'])
        ->name('attendance.correction');

    Route::get('/stamp_correction_request/list', [AttendanceController::class, 'applicationList'])
        ->name('stamp-correction-request.list');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{id}', [AdminAttendanceController::class, 'applicationDetail'])
        ->name('admin.stamp-correction-request.detail');

    Route::post('/stamp_correction_request/approve/{id}', [AdminAttendanceController::class, 'approveApplication'])
        ->name('admin.stamp-correction-request.approve');

    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])
        ->name('admin.attendance.list');

    Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendanceList'])
        ->name('admin.attendance.staff');

    Route::get('/admin/attendance/staff/{id}/csv', [AdminAttendanceController::class, 'exportStaffAttendanceCsv'])
        ->name('admin.attendance.staff.csv');

    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])
        ->name('admin.attendance.detail');

    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'update'])
        ->name('admin.attendance.update');

    Route::get('/admin/staff/list', [AdminAttendanceController::class, 'staffList'])
        ->name('admin.staff.list');

    Route::post('/admin/logout', [AdminLoginController::class, 'destroy'])
        ->name('admin.logout');
});
