<?php

use App\Presentation\Http\Controller\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function () {
    Route::post('/bulk', [NotificationController::class, 'sendBulk']);
    Route::get('/subscriber/{recipientId}', [NotificationController::class, 'getSubscriberNotifications']);
});
