<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    DashboardController, VideosController, ViolationsController, 
    ViolationDetailController, ZonesRulesController, UsersRolesController, AuthController
};

// ---------------------------------------------------------
// 1. PUBLIC ROUTES (GUEST)
// ---------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// ---------------------------------------------------------
// 2. PROTECTED ROUTES (AUTH)
// ---------------------------------------------------------
Route::middleware('auth')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // --- QUYỀN TRUY CẬP CHUNG: Cả 3 quyền (Admin, Supervisor, Viewer) ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/violations', [ViolationsController::class, 'index'])->name('violations.index');
    Route::get('/violations/export', [ViolationsController::class, 'export'])->name('violations.export');

    // --- QUYỀN GIÁM SÁT & ADMIN: (Admin, Supervisor) ---
    // Được phép chạy job xử lý video và xử lý vi phạm
    Route::middleware(['role:admin,supervisor'])->group(function () {
        // Trang xử lý Video (Chạy Job)
        Route::get('/videos', [VideosController::class, 'index'])->name('videos.index');
        
        // Xử lý vi phạm
        Route::get('/violations/{id}', [ViolationDetailController::class, 'show'])->name('violations.show');
        Route::post('/violations/{id}/update', [ViolationDetailController::class, 'update'])->name('violations.update');
    });

    // --- QUYỀN QUẢN TRỊ HỆ THỐNG: (Chỉ Admin) ---
    // Quản lý Zone và Người dùng
    Route::middleware(['role:admin'])->group(function () {
        
        // Quản lý User
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UsersRolesController::class, 'index'])->name('index');
            Route::get('/create', [UsersRolesController::class, 'create'])->name('create');
            Route::post('/', [UsersRolesController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UsersRolesController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UsersRolesController::class, 'update'])->name('update');
            Route::delete('/{user}', [UsersRolesController::class, 'destroy'])->name('destroy');
        });

        // Quản lý Zones & Rules
        Route::prefix('zones-rules')->name('zones-rules.')->group(function () {
            Route::get('/', [ZonesRulesController::class, 'index'])->name('index');
            Route::get('/create', [ZonesRulesController::class, 'create'])->name('create');
            Route::post('/', [ZonesRulesController::class, 'store'])->name('store');
            Route::get('/{zone}/edit', [ZonesRulesController::class, 'edit'])->name('edit');
            Route::put('/{zone}', [ZonesRulesController::class, 'update'])->name('update');
            Route::delete('/{zone}', [ZonesRulesController::class, 'destroy'])->name('destroy');
        });
    });
});