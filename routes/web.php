<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VirtualOfficeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\AttendanceController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('login');

Route::get('/virtual-office', [VirtualOfficeController::class, 'index'])->middleware(['auth', 'auto.logout'])->name('virtual-office');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'auto.logout'])->name('dashboard');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/assign', [WorkspaceController::class, 'assignUser'])->name('workspaces.assign');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/comment', [TaskController::class, 'comment'])->name('tasks.comment');
    Route::post('/tasks/{task}/attach', [TaskController::class, 'attach'])->name('tasks.attach');

    Route::get('/calendar', [GoogleCalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/connect', [GoogleCalendarController::class, 'redirect'])->name('calendar.connect');
    Route::get('/calendar/callback', [GoogleCalendarController::class, 'callback'])->name('calendar.callback');
    Route::post('/calendar/events', [GoogleCalendarController::class, 'store'])->name('calendar.events.store');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/punch-in', [AttendanceController::class, 'punchIn'])->name('attendance.punchin');
    Route::post('/attendance/punch-out', [AttendanceController::class, 'punchOut'])->name('attendance.punchout');
    Route::post('/attendance/lunch-start', [AttendanceController::class, 'lunchStart'])->name('attendance.lunchstart');
    Route::post('/attendance/lunch-end', [AttendanceController::class, 'lunchEnd'])->name('attendance.lunchend');
});
