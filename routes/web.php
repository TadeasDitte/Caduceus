<?php

use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/redirect', [AuthentikController::class, 'redirect'])
    ->name('authentik.redirect');

Route::get('/sso/callback', [AuthentikController::class, 'callback'])
    ->name('authentik.callback');

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/mailboxes', [MailboxController::class, 'index'])->name('mailboxes.index');
});

require __DIR__.'/settings.php';
