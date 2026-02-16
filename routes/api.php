<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes – Anonymous Student Message Wall
|--------------------------------------------------------------------------
|
| Rate limits are applied per-IP via the throttle middleware.
| GET  → 60 requests / minute (general browsing)
| POST → 10 requests / minute (message creation)
| LIKE → 30 requests / minute (reactions)
|
*/

Route::prefix('messages')->group(function () {
    Route::get('/', [MessageController::class, 'index'])
        ->middleware('throttle:60,1');

    Route::post('/', [MessageController::class, 'store'])
        ->middleware('throttle:10,1');



    Route::post('/{id}/like', [MessageController::class, 'like'])
        ->where('id', '[0-9]+')
        ->middleware('throttle:30,1');
});

Route::get('/announcements', [App\Http\Controllers\AnnouncementController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Admin Moderation Routes
|--------------------------------------------------------------------------
|
| POST /api/admin/login  → Authenticate admin, receive Sanctum token
| GET  /api/admin/messages → View all messages (including deleted)
| DELETE /api/admin/messages/{id} → Soft-delete a message
|
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:admin')->group(function () {
        Route::get('/messages', [AdminController::class, 'index']);
        Route::delete('/messages/{id}', [AdminController::class, 'destroy'])
            ->where('id', '[0-9]+');

        Route::post('/announcements', [App\Http\Controllers\AnnouncementController::class, 'store']);
        Route::put('/announcements/{id}', [App\Http\Controllers\AnnouncementController::class, 'update']);
        Route::delete('/announcements/{id}', [App\Http\Controllers\AnnouncementController::class, 'destroy']);
    });
});
