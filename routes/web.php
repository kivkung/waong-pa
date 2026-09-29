<?php

use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomMemberController;
use App\Http\Controllers\RoundController;
use Illuminate\Support\Facades\Route;

// Public pages: guests do not need to log in.
Route::get('/', [PublicDashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [PublicDashboardController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');

    Route::get('/rooms/join', [RoomController::class, 'joinForm'])->name('rooms.join');
    Route::post('/rooms/join', [RoomController::class, 'join'])
        ->middleware('throttle:10,1')->name('rooms.join.store');

    Route::get('/rooms/{room}/members/{member}/edit', [RoomMemberController::class, 'edit'])
        ->whereNumber('member')->name('rooms.members.edit');
    Route::patch('/rooms/{room}/members/{member}', [RoomMemberController::class, 'update'])
        ->whereNumber('member')->name('rooms.members.update');

    Route::post('/rooms/{room}/members/left/{member}', [RoomMemberController::class, 'left_members'])
        ->whereNumber('member')->name('room.members.left');

    Route::get('/rooms/{room}/rounds/create', [RoundController::class, 'create'])->name('rooms.rounds.create');
    Route::post('/rooms/{room}/rounds', [RoundController::class, 'store'])->name('rooms.rounds.store');

});

// The controller checks permission again when opening a private room.
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');

// get round
Route::get('/rooms/{room}/rounds/{round}', [RoundController::class, 'show'])
    ->whereNumber('round')
    ->name('rooms.rounds.show');

require __DIR__.'/settings.php';
