<?php

use App\Http\Controllers\AttendanceComplaintController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSummaryController;
use App\Http\Controllers\BusinessTripController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SickLeaveController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['guest'])->group(function () {
    Route::get('register', [RegistrationController::class, 'create'])->name('register');
    Route::post('register', [RegistrationController::class, 'store'])->name('register.store');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/recap', [AttendanceController::class, 'recap'])->name('attendance.recap');
    Route::get('attendance/summary', [AttendanceSummaryController::class, 'index'])->name('attendance.summary');
    Route::get('attendance/summary/export', [AttendanceSummaryController::class, 'export'])->name('attendance.summary.export');
    Route::get('attendance/recap/export/excel', [AttendanceController::class, 'exportExcel'])->name('attendance.recap.export.excel');
    Route::get('attendance/recap/export/pdf', [AttendanceController::class, 'exportPdf'])->name('attendance.recap.export.pdf');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.checkIn');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.checkOut');

    Route::resource('users', UserController::class)->only([
        'index',
        'create',
        'store',
        'show',
        'edit',
        'update',
        'destroy',
    ]);

    Route::resource('permissions', PermissionController::class)->only([
        'index',
        'create',
        'store',
    ]);

    Route::post('permissions/{permission}/cancel', [PermissionController::class, 'cancel'])->name('permissions.cancel');
    Route::post('permissions/{permission}/approve', [PermissionController::class, 'approve'])->name('permissions.approve');
    Route::post('permissions/{permission}/reject', [PermissionController::class, 'reject'])->name('permissions.reject');

    Route::resource('leaves', LeaveController::class)->only([
        'index',
        'create',
        'store',
    ]);

    Route::post('leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');

    Route::resource('sick-leaves', SickLeaveController::class)->only([
        'index',
        'create',
        'store',
    ]);

    Route::post('sick-leaves/{sick_leave}/cancel', [SickLeaveController::class, 'cancel'])->name('sick-leaves.cancel');
    Route::post('sick-leaves/{sick_leave}/approve', [SickLeaveController::class, 'approve'])->name('sick-leaves.approve');
    Route::post('sick-leaves/{sick_leave}/reject', [SickLeaveController::class, 'reject'])->name('sick-leaves.reject');

    Route::resource('business-trips', BusinessTripController::class)->only([
        'index',
        'create',
        'store',
    ]);

    Route::post('business-trips/{business_trip}/cancel', [BusinessTripController::class, 'cancel'])->name('business-trips.cancel');
    Route::post('business-trips/{business_trip}/approve', [BusinessTripController::class, 'approve'])->name('business-trips.approve');
    Route::post('business-trips/{business_trip}/reject', [BusinessTripController::class, 'reject'])->name('business-trips.reject');

    Route::resource('attendance-complaints', AttendanceComplaintController::class)->only([
        'index',
        'create',
        'store',
    ]);

    Route::post('attendance-complaints/{complaint}/cancel', [AttendanceComplaintController::class, 'cancel'])->name('attendance-complaints.cancel');
    Route::post('attendance-complaints/{complaint}/approve', [AttendanceComplaintController::class, 'approve'])->name('attendance-complaints.approve');
    Route::post('attendance-complaints/{complaint}/reject', [AttendanceComplaintController::class, 'reject'])->name('attendance-complaints.reject');

    Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
});

require __DIR__.'/settings.php';
