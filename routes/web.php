<?php

use Illuminate\Support\Facades\Route;

// FORCE BLADE DASHBOARD. Vue/app.blade ochilmaydi.
Route::get('/', function () {
    return view('dashboard');
});

Route::fallback(function () {
    return view('dashboard');
});
