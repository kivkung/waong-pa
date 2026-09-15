<?php

use App\Http\Controllers\Waongpa\DownloadMeeting;
use App\Livewire\Waongpa\Catalogue;
use App\Livewire\Waongpa\CreateRoom;
use App\Livewire\Waongpa\RoomBoard;
use App\Livewire\Waongpa\RoundBoard;
use App\Livewire\Waongpa\Schedule;
use Illuminate\Support\Facades\Route;

Route::prefix('waongpa')->name('waongpa.')->group(function () {
    Route::get('/', Catalogue::class)->name('index');
    Route::middleware('auth')->group(function () {
        Route::get('/schedule', Schedule::class)->name('schedule');
        Route::get('/rooms/create', CreateRoom::class)->name('rooms.create');
        Route::get('/rounds/{roundId}/calendar', DownloadMeeting::class)->whereNumber('roundId')->name('rounds.ics');
    });
    Route::get('/rooms/{roomId}', RoomBoard::class)->whereNumber('roomId')->name('rooms.show');
    Route::get('/rounds/{roundId}', RoundBoard::class)->whereNumber('roundId')->name('rounds.show');
});
