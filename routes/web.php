<?php

use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/mailboxes', [MailboxController::class, 'index'])->name('mailboxes.index');
});

require __DIR__.'/settings.php';
