<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('login');

Route::get('/dashboard', function () {
    return view('dashboard.index');
})->middleware(['auth', 'auto.logout'])->name('dashboard');

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

    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/assign', [WorkspaceController::class, 'assignUser'])->name('workspaces.assign');
});
