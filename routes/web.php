<?php

use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDeletionLogController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\PortalNotificationController;
use App\Http\Controllers\PublicAnnouncementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicAnnouncementController::class, 'index'])->name('home');
Route::get('/portal', [PublicAnnouncementController::class, 'index'])->name('portal');
Route::get('/pengumuman/{announcement}', [PublicAnnouncementController::class, 'show'])->name('announcements.show');
Route::get('/foto/{photo}', [PublicAnnouncementController::class, 'photo'])->name('photos.show');

Route::get('/notifikasi/feed', [PortalNotificationController::class, 'feed'])->name('notifications.feed');
Route::get('/notifikasi/item/{notification}', [PortalNotificationController::class, 'item'])->whereNumber('notification')->name('notifications.item');
Route::post('/notifikasi/langganan', [PortalNotificationController::class, 'subscribe'])->middleware('throttle:300,1')->name('notifications.subscribe');
Route::delete('/notifikasi/langganan', [PortalNotificationController::class, 'unsubscribe'])->middleware('throttle:300,1')->name('notifications.unsubscribe');
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.announcements.index'))->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    Route::get('deletion-logs', [AdminDeletionLogController::class, 'index'])->name('deletion-logs.index');
    Route::resource('users', AdminUserController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware('manage-users');
    Route::resource('announcements', AdminAnnouncementController::class)->except('show');
});
