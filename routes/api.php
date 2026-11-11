<?php

use App\Http\Controllers\NoteController;
use App\Http\Middleware\ValidateNoteId;
use Illuminate\Support\Facades\Route;

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::middleware(ValidateNoteId::class)->group(function () {
    Route::get('/notes/{id}', [NoteController::class, 'show']);
    Route::match(['put', 'patch'], '/notes/{id}', [NoteController::class, 'update']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);
});
